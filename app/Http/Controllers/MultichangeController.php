<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\GraphicOrderStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectNote;
use App\Models\ProjectTypeSub;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowStep;
use App\Support\CurrentTenant;
use App\Support\MultichangeFieldCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

/**
 * "Multichange" (Ralf, 2026-09-13, nach Vietto-Analyse - volle Herleitung
 * und Konzept in der Backlog-Memory). Zielmenge ist BEWUSST ausschließlich
 * eine bestehende Projektgruppe (Ralf: "damit man das nicht leichtfertig
 * macht. Das ist ein sehr gefährliches Feature in den falschen Händen!") -
 * kein direkter Weg über den aktuellen Projektfilter, das ist eine bewusste
 * Reibungsbremse, kein Bequemlichkeits-Detail. Nicht nachträglich ändern,
 * ohne das explizit mit Ralf neu abzustimmen.
 *
 * Sicherheitsprinzipien, direkt aus den in Vietto gefundenen
 * Zuverlässigkeitslücken abgeleitet: (1) die Zielmenge wird bei jedem
 * Schritt (Vorschau UND Anwenden) frisch aus der DB gelesen, nie aus
 * Client-Zustand übernommen. (2) Anwenden läuft in einer Transaktion -
 * alles oder nichts. (3) übersprungene Projekte werden explizit mit PN
 * gelistet, nie stillschweigend ignoriert. (4) jede Änderung bekommt einen
 * echten Activity-Log-Eintrag am Projekt.
 */
class MultichangeController extends Controller
{
    /**
     * Ralf, 2026-09-13: "Das Gruppieren soll losgelöst sein davon, quasi
     * die Grundlage. Das macht man auch nicht ständig... Multichange kann
     * immer mal wieder dazwischen vorkommen." Multichange ist deshalb ein
     * eigener, immer sichtbarer Button in der Übersicht (nicht mehr im
     * Gruppieren-Panel versteckt) und wählt seine Zielgruppe selbst -
     * unabhängig davon, ob/welche Gruppe im Gruppieren-Store gerade aktiv
     * ist. Gruppen-Auswahl ist deshalb Teil DIESES Formulars, nicht mehr
     * Teil der Route (kein {group}-Routenparameter mehr).
     */
    public function form(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        return response()->view('projekte.partials.multichange-body', [
            'groups' => $this->availableGroups(),
            'fields' => MultichangeFieldCatalog::available(CurrentTenant::id()),
            'selectedGroupId' => $request->integer('group_id') ?: '',
            // Ralf: "Wenn ich auf Zurück klicke, werde ich bestraft und muss
            // nochmal von vorne beginnen" - Feld+Wert bleiben beim
            // Zurück-Klick jetzt erhalten (siehe "Zurück"-Button in
            // multichange-body.blade.php), nicht nur die Gruppe.
            'selectedField' => (string) $request->string('field'),
            'selectedValue' => (string) $request->string('value'),
            'selectedOverwriteDifferentWorkflow' => $request->boolean('overwrite_different_workflow'),
        ]);
    }

    public function preview(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        $group = $this->resolveGroup($request);
        if ($group === null) {
            return $this->invalidInputResponse($request, new MessageBag(['group' => [__('Bitte eine Gruppe auswählen.')]]));
        }

        $validation = $this->validateInput($request);
        if ($validation['errors']) {
            return $this->invalidInputResponse($request, $validation['errors'], $group, $validation['field']);
        }
        [$field, $value] = [$validation['field'], $validation['value']];
        $this->authorizeFieldValue($request, $field, $value);
        $overwriteDifferentWorkflow = $request->boolean('overwrite_different_workflow');
        $preview = $this->buildPreview($group, $field, $value, $overwriteDifferentWorkflow);
        $changeRows = $this->describeChangeRows($preview, $field, $value);

        return response()->view('projekte.partials.multichange-body', [
            'groups' => $this->availableGroups(),
            'fields' => MultichangeFieldCatalog::available(CurrentTenant::id()),
            'group' => $group,
            'preview' => $preview,
            'field' => $field,
            'value' => $value,
            'overwriteDifferentWorkflow' => $overwriteDifferentWorkflow,
            'actionText' => $this->describeAction($field, $value),
            'skipReason' => $this->describeSkipReason($field, $preview['skipped']->count()),
            'unchangedNote' => $this->describeUnchangedNote($field, $preview['unchanged']->count()),
            'changeRows' => $changeRows,
            // Ralf, 2026-09-14: "eine gute Möglichkeit, solche Infos ins
            // Clipboard zu übertragen, damit der Bearbeiter die offenen
            // Dinge gezielt nachbearbeiten kann" - eine Zeile je Bemerkung
            // (Ausschlussgrund ODER Warnung wie offene Illustrationsaufträge),
            // nicht nur die übersprungenen Projekte wie zuvor.
            'changeRowsNoteCopyText' => collect($changeRows)
                ->filter(fn (array $row) => ! empty($row['note']))
                ->map(fn (array $row) => $row['pn'].' – '.$row['note'])
                ->implode(PHP_EOL),
        ]);
    }

    public function apply(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        $group = $this->resolveGroup($request);
        if ($group === null) {
            return $this->invalidInputResponse($request, new MessageBag(['group' => [__('Bitte eine Gruppe auswählen.')]]));
        }

        $validation = $this->validateInput($request);
        if ($validation['errors']) {
            return $this->invalidInputResponse($request, $validation['errors'], $group, $validation['field']);
        }
        [$field, $value] = [$validation['field'], $validation['value']];
        $this->authorizeFieldValue($request, $field, $value);
        $overwriteDifferentWorkflow = $request->boolean('overwrite_different_workflow');
        $preview = $this->buildPreview($group, $field, $value, $overwriteDifferentWorkflow);

        $applied = DB::transaction(function () use ($preview, $field, $value) {
            foreach ($preview['applicable'] as $project) {
                $this->applyValue($project, $field, $value);
                $project->save();

                Activity::log($project, ActivityType::ProjectMultichanged, $this->describeChange($field, $value));
            }

            return $preview['applicable']->count();
        });

        return response()->view('projekte.partials.multichange-body', [
            'group' => $group,
            'fields' => MultichangeFieldCatalog::available(CurrentTenant::id()),
            'result' => [
                'applied' => $applied,
                'skipped' => $preview['skipped'],
                'resultText' => $this->describeResult($field, $applied),
                'skipReason' => $this->describeSkipReason($field, $preview['skipped']->count()),
                'unchangedNote' => $this->describeUnchangedNote($field, $preview['unchanged']->count()),
            ],
        ]);
    }

    /**
     * Zusätzliches Recht für bestimmte Feld+Wert-Kombinationen, analog zum
     * einzelnen Aktivieren-Button (ProjectWorkflowStepController::activate()):
     * Wechsel zu einem Beenden/Verworfen-Schritt (lifecycle_status 3/4)
     * braucht project.complete, nicht nur project.multichange - sonst ließe
     * sich diese Sperre über Multichange umgehen.
     */
    private function authorizeFieldValue(Request $request, array $field, mixed $value): void
    {
        if ($field['key'] !== 'workflow_step_id') {
            return;
        }

        $targetStep = WorkflowStep::find($value);
        if ($targetStep && in_array($targetStep->lifecycle_status, [3, 4], true)) {
            abort_unless($request->user()->can('project.complete'), 403);
        }
    }

    /**
     * Bewusst KEIN ->validate() (das würde bei einem Fehler per Redirect
     * auf die vorherige Seite umleiten - für einen fetch()-Aufruf aus dem
     * Modal heraus landet die komplette umgeleitete Seite dann roh im
     * Modal-Inhalt, siehe Bug-Fund beim Testen). Fehler werden stattdessen
     * selbst behandelt und als normales Formular-Fragment mit Fehlertext
     * zurückgegeben (siehe invalidInputResponse()).
     *
     * @return array{field: ?array, value: mixed, errors: ?MessageBag}
     */
    private function validateInput(Request $request): array
    {
        $field = MultichangeFieldCatalog::find(CurrentTenant::id(), (string) $request->string('field'));
        if ($field === null) {
            return ['field' => null, 'value' => null, 'errors' => new MessageBag(['field' => [__('Bitte ein Feld auswählen.')]])];
        }

        $rules = match ($field['type']) {
            'text' => ($field['required'] ?? false) ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'textarea' => ($field['required'] ?? false) ? ['required', 'string', 'max:2000'] : ['nullable', 'string', 'max:2000'],
            'date' => ['nullable', 'date'],
            'select', 'workflow_step' => ['required', Rule::in(array_keys($field['options']))],
        };

        $validator = Validator::make($request->all(), ['value' => $rules], [], ['value' => __('Neuer Wert')]);
        if ($validator->fails()) {
            return ['field' => $field, 'value' => null, 'errors' => $validator->errors()];
        }

        $value = $validator->validated()['value'] ?? null;
        if (in_array($field['type'], ['select', 'workflow_step'], true) && $value !== null) {
            $value = (int) $value;
        }

        return ['field' => $field, 'value' => $value, 'errors' => null];
    }

    private function invalidInputResponse(Request $request, MessageBag $errors, ?ProjectGroup $group = null, ?array $field = null): Response
    {
        return response()
            ->view('projekte.partials.multichange-body', [
                'groups' => $this->availableGroups(),
                'fields' => MultichangeFieldCatalog::available(CurrentTenant::id()),
                'formErrors' => $errors,
                'selectedGroupId' => $group?->id ?? '',
                'selectedField' => $field['key'] ?? '',
                // Eingegebener Wert bleibt auch bei einem Validierungsfehler
                // erhalten, gleicher Grund wie beim "Zurück"-Button.
                'selectedValue' => (string) $request->string('value'),
                'selectedOverwriteDifferentWorkflow' => $request->boolean('overwrite_different_workflow'),
            ])
            ->setStatusCode(422);
    }

    /**
     * @return Collection<int, ProjectGroup>
     */
    private function availableGroups(): Collection
    {
        return Auth::user()->projectGroups()->withCount('projects')->orderBy('name')->get();
    }

    /**
     * Liest+prüft die Zielgruppe aus dem Request statt aus einem
     * Routen-Parameter (siehe Klassen-Docblock: Gruppen-Auswahl ist Teil
     * des Formulars, damit der Multichange-Button unabhängig vom
     * Gruppieren-Panel funktioniert).
     */
    private function resolveGroup(Request $request): ?ProjectGroup
    {
        $groupId = $request->integer('group_id');
        if (! $groupId) {
            return null;
        }

        $group = ProjectGroup::query()->find($groupId);
        if ($group === null) {
            return null;
        }

        $group->authorizeViewer();

        return $group;
    }

    /**
     * @return array{applicable: Collection<int, Project>, skipped: Collection<int, Project>, unchanged: Collection<int, Project>}
     */
    private function buildPreview(ProjectGroup $group, array $field, mixed $value, bool $overwriteDifferentWorkflow = false): array
    {
        // Frisch aus der DB, nicht aus irgendeinem Client-Zustand übernommen -
        // die Gruppen-Mitgliedschaft kann sich zwischen Vorschau und Anwenden
        // ändern, das soll sich dann auch auswirken (Vietto-Lektion).
        // 'workflow' + 'projectWorkflowSteps.workflowStep' mitgeladen für
        // die "Alter Wert"-Spalte der Änderungs-Tabelle (describeOldValue())
        // - vermeidet N+1 bei workflow_id/workflow_step_id.
        $projects = $group->projects()->with(['projectWorkflowSteps.workflowStep', 'workflow', 'projectTypeSub.main'])->get();
        $unchanged = collect();

        if ($field['key'] === 'status') {
            $applicable = $projects->reject(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
            $skipped = $projects->filter(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
        } elseif ($field['key'] === 'workflow_step_id') {
            // Ralf, 2026-09-14, drei Situationen (siehe MultichangeFieldCatalog
            // für die volle Herleitung): (1) derselbe Workflow + Ziel-Schritt
            // schon aktuell -> unverändert, (2) derselbe Workflow + anderer
            // aktueller Schritt -> wird geändert, (3) kein/anderer Workflow ->
            // übersprungen (Workflow muss zuerst per eigenem Feld angepasst
            // werden - kein Häkchen/Override hier, anders als bei workflow_id).
            $targetStep = WorkflowStep::find($value);
            $targetWorkflowId = $targetStep?->workflow_id;

            $sameWorkflow = $projects->filter(fn (Project $project) => $project->workflow_id === $targetWorkflowId);
            $skipped = $projects->reject(fn (Project $project) => $project->workflow_id === $targetWorkflowId)->values();

            $unchanged = $sameWorkflow->filter(function (Project $project) use ($value) {
                $currentStep = $project->projectWorkflowSteps->firstWhere('is_current', true);

                return $currentStep && $currentStep->workflow_step_id === (int) $value;
            })->values();

            $applicable = $sameWorkflow->reject(fn (Project $project) => $unchanged->contains('id', $project->id))->values();
        } elseif ($field['key'] === 'workflow_id') {
            // Ralf, 2026-09-14, drei Fälle statt einfachem Set (siehe
            // MultichangeFieldCatalog::available() für die volle Herleitung):
            // (1) kein Workflow -> zuweisen, (2) hat GENAU diesen Workflow
            // schon -> unverändert, nichts tun, (3) hat einen ANDEREN
            // Workflow -> je nach Häkchen entweder überspringen oder
            // überschreiben.
            $unchanged = $projects->filter(fn (Project $project) => $project->workflow_id === (int) $value)->values();
            $remaining = $projects->reject(fn (Project $project) => $project->workflow_id === (int) $value);
            $hasOtherWorkflow = fn (Project $project) => $project->workflow_id !== null;

            if ($overwriteDifferentWorkflow) {
                $applicable = $remaining->values();
                $skipped = collect();
            } else {
                $applicable = $remaining->reject($hasOtherWorkflow)->values();
                $skipped = $remaining->filter($hasOtherWorkflow)->values();
            }
        } else {
            $applicable = $projects;
            $skipped = collect();
        }

        return ['applicable' => $applicable, 'skipped' => $skipped, 'unchanged' => $unchanged];
    }

    /**
     * Die meisten Felder hier sind echte projects-Spalten, aber "Initiator"
     * ist ein normales Zusatzfeld (siehe MultichangeFieldCatalog) - dessen
     * Wert lebt im attributes-JSON, nicht in einer eigenen Spalte.
     */
    private function applyValue(Project $project, array $field, mixed $value): void
    {
        if (($field['storage'] ?? 'column') === 'note') {
            ProjectNote::query()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'type' => $field['note_type'],
                'text' => $value,
                'created_by_user_id' => Auth::id(),
                'created_at' => now(),
            ]);

            return;
        }

        if (($field['storage'] ?? 'column') === 'attribute') {
            $attributes = $project->attributes ?? [];
            // Gleiche Konvention wie beim normalen Zusatzfeld-Speichern
            // (ProjectController::update()): leerer Wert entfernt den
            // Schlüssel komplett statt einen leeren String zu speichern.
            if ($value === null || $value === '') {
                unset($attributes[$field['key']]);
            } else {
                $attributes[$field['key']] = $value;
            }
            $project->attributes = $attributes;

            return;
        }

        if ($field['key'] === 'workflow_id') {
            $this->applyWorkflow($project, (int) $value);

            return;
        }

        if ($field['key'] === 'workflow_step_id') {
            $this->applyWorkflowStep($project, (int) $value);

            return;
        }

        if ($field['key'] === 'project_type_sub_id') {
            // Gleiche Ableitung wie ProjectController::update() -
            // project_type_main_id hat kein eigenes Formularfeld, sondern
            // wird immer aus der gewählten Unterkategorie übernommen.
            $project->project_type_sub_id = $value;
            $project->project_type_main_id = ProjectTypeSub::query()->where('tenant_id', $project->tenant_id)->find($value)?->project_type_main_id;

            return;
        }

        $project->{$field['key']} = $value;
    }

    /**
     * Gleiche Mechanik wie ProjectWorkflowStepController::activate() (der
     * einzelne "Aktivieren"-Button) - bewusst 1:1 übernommen statt eigener
     * vereinfachter Logik, inkl. Vorwärts/Rückwärts-Unterscheidung (nur
     * beim Vorwärts-Wechsel gilt der bisherige Schritt als erledigt,
     * completed_at gesetzt - bei einem Rücksprung/Korrekturschleife bleibt
     * er unangetastet). Anders als beim Einzelschritt-Button: kein
     * E-Mail-Versand, keine Bestätigungs-Nachfrage pro Projekt (Ralf,
     * 2026-09-14: "keine E-Mail-Flut auslösen, aber eine Erinnerung an den
     * Anwender" - siehe MultichangeFieldCatalog-Hint + describeResult()).
     */
    private function applyWorkflowStep(Project $project, int $targetWorkflowStepId): void
    {
        $target = $project->projectWorkflowSteps->firstWhere('workflow_step_id', $targetWorkflowStepId);
        if (! $target) {
            // Kann bei korrekt gefilterter "applicable"-Liste nicht
            // vorkommen (buildPreview() lässt nur Projekte mit demselben
            // Workflow durch) - defensiv statt eine Annahme zu erzwingen.
            return;
        }

        $currentStep = $project->projectWorkflowSteps->firstWhere('is_current', true);
        $person = Auth::user()->person;

        if ($currentStep && $currentStep->id !== $target->id) {
            $movingForward = $target->sort > $currentStep->sort;

            $currentStep->update([
                'is_current' => false,
                'completed_at' => $movingForward ? now() : $currentStep->completed_at,
                'completed_by_person_id' => $movingForward ? $person?->id : $currentStep->completed_by_person_id,
            ]);
        }

        $target->update([
            'is_current' => true,
            'started_at' => now(),
            'completed_at' => null,
            'completed_by_person_id' => null,
        ]);

        $project->status = $target->workflowStep->lifecycle_status - 1;
    }

    /**
     * Gleiches Muster wie ProjectCopyController::store() ("Workflow mit
     * kopieren"): Schritt-Vorlagen als projekteigene Instanzen kopieren,
     * den Schritt mit lifecycle_status=Geplant automatisch aktivieren.
     * Zusätzlich (nur hier nötig, da Multichange auch Projekte MIT
     * bestehendem Workflow überschreiben kann, siehe buildPreview()): einen
     * eventuell noch aktiven Schritt eines VORHERIGEN Workflows explizit
     * deaktivieren - sonst bliebe er als Karteileiche mit is_current=true
     * stehen (betrifft dann z.B. die Skip-Prüfung beim Bearbeitungsstatus-
     * Feld, die nicht nach Workflow filtert).
     */
    private function applyWorkflow(Project $project, int $workflowId): void
    {
        $project->projectWorkflowSteps()->where('is_current', true)->update(['is_current' => false]);

        $project->workflow_id = $workflowId;

        WorkflowStep::query()->where('workflow_id', $workflowId)->get()
            ->each(fn (WorkflowStep $step) => ProjectWorkflowStep::query()->firstOrCreate(
                ['project_id' => $project->id, 'workflow_step_id' => $step->id],
                ['tenant_id' => $project->tenant_id, 'sort' => $step->sort]
            ));

        $plannedStep = $project->projectWorkflowSteps()
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_id', $workflowId)->where('lifecycle_status', 1))
            ->first();

        if ($plannedStep) {
            $plannedStep->update(['is_current' => true, 'started_at' => now()]);
            $project->status = 0;
        }
    }

    /**
     * Satz für die Vorschau/den Bestätigungsdialog (Gegenwart: "wird
     * gesetzt"/"wird hinzugefügt"). "Bemerkungen" hat Anhängen- statt
     * Set-Semantik (siehe applyValue()), braucht deshalb eine eigene
     * Formulierung statt der generischen "Feld wird auf Wert gesetzt.".
     */
    private function describeAction(array $field, mixed $value): string
    {
        if (($field['storage'] ?? 'column') === 'note') {
            // Bewusst ohne Artikel ("Neue Bemerkung"/"Neuer Eintrag") -
            // unterschiedliches Genus je note_label ließe sich sonst nicht
            // generisch formulieren, ohne für jedes Feld eine eigene
            // Artikel-Form mitzugeben.
            return __(':noteLabel wird hinzugefügt: „:value"', ['noteLabel' => $field['note_label'], 'value' => $value]);
        }

        return __(':field wird auf „:value" gesetzt.', ['field' => $field['label'], 'value' => $this->describeValue($field, $value)]);
    }

    private function describeResult(array $field, int $count): string
    {
        if (($field['storage'] ?? 'column') === 'note') {
            return trans_choice(
                ':noteLabel bei :count Projekt hinzugefügt.|:noteLabel bei :count Projekten hinzugefügt.',
                $count,
                ['count' => $count, 'noteLabel' => $field['note_label']]
            );
        }

        $text = __(':field: :count Projekt(e) erfolgreich geändert.', ['field' => $field['label'], 'count' => $count]);

        // Ralf, 2026-09-14: "keine E-Mail-Flut auslösen, aber eine
        // Erinnerung an den Anwender, dass er das ggf. selbst veranlassen
        // muss" - Multichange verschickt bewusst keine Mails (anders als
        // der einzelne "Aktivieren"-Button), also expliziter Hinweis statt
        // stillschweigend nichts zu tun.
        if ($field['key'] === 'workflow_step_id' && $count > 0) {
            $text .= ' '.__('Es wurden keine automatischen E-Mails an Zuständige verschickt - bei Bedarf bitte selbst informieren.');
        }

        return $text;
    }

    /**
     * Gleicher Satz für den Activity-Log-Eintrag, Vergangenheitsform.
     */
    private function describeChange(array $field, mixed $value): string
    {
        if (($field['storage'] ?? 'column') === 'note') {
            return __(':noteLabel per Multichange hinzugefügt: „:value"', ['noteLabel' => $field['note_label'], 'value' => $value]);
        }

        return __(':field per Multichange auf „:value" gesetzt.', ['field' => $field['label'], 'value' => $this->describeValue($field, $value)]);
    }

    /**
     * Überschrift des übersprungen-Kastens in der Vorschau/im Ergebnis -
     * je Feld unterschiedlicher Grund, siehe buildPreview().
     */
    private function describeSkipReason(array $field, int $count): string
    {
        if ($field['key'] === 'workflow_id') {
            return trans_choice(
                ':count Projekt hat einen anderen Workflow und wird übersprungen:|:count Projekte haben einen anderen Workflow und werden übersprungen:',
                $count,
                ['count' => $count]
            );
        }

        if ($field['key'] === 'workflow_step_id') {
            return trans_choice(
                ':count Projekt hat keinen oder einen anderen Workflow und wird übersprungen (Workflow zuerst per Feld "Workflow" anpassen):|:count Projekte haben keinen oder einen anderen Workflow und werden übersprungen (Workflow zuerst per Feld "Workflow" anpassen):',
                $count,
                ['count' => $count]
            );
        }

        if ($field['key'] === 'status') {
            return __(':count Projekt(e) werden übersprungen (aktueller Workflow-Schritt bestimmt den Status):', ['count' => $count]);
        }

        return trans_choice(':count Projekt wird übersprungen:|:count Projekte werden übersprungen:', $count, ['count' => $count]);
    }

    /**
     * Nur für Felder mit einem echten "unverändert"-Fall (workflow_id,
     * workflow_step_id - siehe buildPreview()) - null unterdrückt den Block
     * in multichange-body.blade.php komplett.
     */
    private function describeUnchangedNote(array $field, int $count): ?string
    {
        if ($count === 0 || ! in_array($field['key'], ['workflow_id', 'workflow_step_id'], true)) {
            return null;
        }

        if ($field['key'] === 'workflow_step_id') {
            return trans_choice(
                ':count Projekt hat diesen Workflow-Schritt bereits als aktuellen Schritt - bleibt unverändert.|:count Projekte haben diesen Workflow-Schritt bereits als aktuellen Schritt - bleiben unverändert.',
                $count,
                ['count' => $count]
            );
        }

        return trans_choice(
            ':count Projekt hat diesen Workflow bereits - bleibt unverändert.|:count Projekte haben diesen Workflow bereits - bleiben unverändert.',
            $count,
            ['count' => $count]
        );
    }

    private function describeValue(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('entfernt');
        }

        return match ($field['type']) {
            'select', 'workflow_step' => $field['options'][$value] ?? (string) $value,
            'date' => Carbon::parse($value)->format('d.m.Y'),
            default => (string) $value,
        };
    }

    /**
     * Ralf, 2026-09-14: "kleine Tabelle... 1. Spalte Projektnr. + Bezeichnung,
     * 2. Alter Wert, 3. Neuer Wert" - für ALLE Multichange-Felder (nicht nur
     * Workflow). Ralf, gleicher Tag, Nachtrag: "Nimm die Projekte, die von
     * einer Änderung ausgeschlossen sind, mit in die Tabelle rein... mit
     * einer klaren Kennzeichnung + Bemerkung, dass das Projekt nicht
     * geändert wird, weil..." - deshalb jetzt ALLE drei Gruppen
     * (applicable/unchanged/skipped) als Zeilen, nicht nur applicable; die
     * gesammelten Kurz-Hinweise (skipReason/unchangedNote) bleiben ZUSÄTZLICH
     * bestehen (siehe preview()/apply()), nur die frühere Bullet-Liste
     * innerhalb dieser Boxen entfällt in multichange-body.blade.php, weil
     * die Tabelle das jetzt mit mehr Kontext (Alter/Neuer Wert) abdeckt.
     * 4. Spalte "Bemerkungen": bei ausgeschlossenen Zeilen der Grund, bei
     * betroffenen Zeilen aktuell nur für workflow_step_id genutzt (offene
     * Illustrationsaufträge bei Wechsel zu Beenden/Verwerfen).
     *
     * @param  array{applicable: Collection<int, Project>, skipped: Collection<int, Project>, unchanged: Collection<int, Project>}  $preview
     * @return list<array{pn: string, title: string, status: string, old: string, new: string, note: ?string}>
     */
    private function describeChangeRows(array $preview, array $field, mixed $value): array
    {
        $rows = [];

        foreach ($preview['applicable'] as $project) {
            $rows[] = [
                'pn' => $project->source_pn,
                'title' => $project->title,
                'status' => 'applicable',
                'old' => $this->describeOldValue($project, $field),
                'new' => $this->describeNewValue($field, $value),
                'note' => $this->describeRowNote($project, $field, $value),
            ];
        }

        foreach ($preview['unchanged'] as $project) {
            $rows[] = [
                'pn' => $project->source_pn,
                'title' => $project->title,
                'status' => 'unchanged',
                'old' => $this->describeOldValue($project, $field),
                'new' => '–',
                'note' => $this->describeExclusionNote($field, 'unchanged'),
            ];
        }

        foreach ($preview['skipped'] as $project) {
            $rows[] = [
                'pn' => $project->source_pn,
                'title' => $project->title,
                'status' => 'skipped',
                'old' => $this->describeOldValue($project, $field),
                'new' => '–',
                'note' => $this->describeExclusionNote($field, 'skipped'),
            ];
        }

        return $rows;
    }

    /**
     * Bemerkung je Zeile für ausgeschlossene ("unchanged"/"skipped")
     * Projekte - Einzelsatz ohne Anzahl-Präfix (anders als
     * describeSkipReason()/describeUnchangedNote(), die die einleitenden
     * Sammel-Kästen darüber beschriften), da hier pro Projekt einzeln
     * daneben steht.
     */
    private function describeExclusionNote(array $field, string $status): string
    {
        if ($status === 'unchanged') {
            return match ($field['key']) {
                'workflow_id' => __('Wird nicht geändert: hat diesen Workflow bereits.'),
                'workflow_step_id' => __('Wird nicht geändert: hat diesen Workflow-Schritt bereits als aktuellen Schritt.'),
                default => __('Wird nicht geändert: hat diesen Wert bereits.'),
            };
        }

        return match ($field['key']) {
            'workflow_id' => __('Wird nicht geändert: hat einen anderen Workflow.'),
            'workflow_step_id' => __('Wird nicht geändert: hat keinen oder einen anderen Workflow - Workflow zuerst per Feld "Workflow" anpassen.'),
            'status' => __('Wird nicht geändert: aktueller Workflow-Schritt bestimmt den Status.'),
            default => __('Wird nicht geändert.'),
        };
    }

    /**
     * Bemerkung für tatsächlich betroffene ("applicable") Zeilen - aktuell
     * nur für workflow_step_id genutzt: weiche Warnung bei offenen
     * Illustrationsaufträgen, wenn der Ziel-Schritt Beenden/Verwerfen ist
     * (lifecycle_status 3/4), analog der Warnung beim einzelnen
     * "Aktivieren"-Button (dort ein Popup, hier Teil der Tabelle statt X
     * Popups bei X Projekten). null bei keiner Warnung - Zelle bleibt leer.
     */
    private function describeRowNote(Project $project, array $field, mixed $value): ?string
    {
        if ($field['key'] !== 'workflow_step_id') {
            return null;
        }

        $targetStep = WorkflowStep::find($value);
        if (! $targetStep || ! in_array($targetStep->lifecycle_status, [3, 4], true)) {
            return null;
        }

        $openStatusValues = array_map(
            fn ($status) => $status->value,
            array_filter(GraphicOrderStatus::cases(), fn ($status) => $status->isOpen())
        );
        $openCount = $project->graphicOrders()->whereIn('graphic_order_status_id', $openStatusValues)->count();

        if ($openCount === 0) {
            return null;
        }

        return trans_choice(':count offener Illustrationsauftrag|:count offene Illustrationsaufträge', $openCount, ['count' => $openCount]);
    }

    /**
     * Aktueller Wert eines Projekts für die "Alter Wert"-Spalte - nutzt wo
     * möglich Project::columnValue() (schon korrekt formatiert, z.B. Status/
     * Erstellungsstatus als Label statt Zahl, Daten als d.m.Y), statt die
     * Formatierung hier zu duplizieren.
     */
    private function describeOldValue(Project $project, array $field): string
    {
        if (($field['storage'] ?? 'column') === 'note') {
            // Anhängen statt Ersetzen (siehe applyValue()) - "alter Wert"
            // ergibt hier konzeptionell keinen Sinn, es gibt keinen einen.
            return '–';
        }

        if ($field['key'] === 'workflow_id') {
            return $project->workflow?->name ?? '–';
        }

        if ($field['key'] === 'workflow_step_id') {
            $currentStep = $project->projectWorkflowSteps->firstWhere('is_current', true);

            return $currentStep ? ($field['options'][$currentStep->workflow_step_id] ?? $currentStep->workflowStep?->title ?? '–') : '–';
        }

        // Project::columnValue() kennt nur den Anzeige-Key 'project_type'
        // (Kategorie+Art als String, siehe rows.blade.php-Spalte), nicht
        // den hier verwendeten Formular-/Spalten-Key 'project_type_sub_id' -
        // ohne diesen Sonderfall käme die rohe ID statt eines Labels raus.
        if ($field['key'] === 'project_type_sub_id') {
            $sub = $project->projectTypeSub;

            return $sub ? $sub->main->name.': '.$sub->name : '–';
        }

        $raw = ($field['storage'] ?? 'column') === 'attribute'
            ? ($project->attributes[$field['key']] ?? null)
            : $project->columnValue($field['key']);

        // Ralf-Bug-Report, 2026-09-14: "entfernt" klang hier falsch - das
        // Feld wurde ja nicht entfernt, es war nie gesetzt. "entfernt" bleibt
        // korrekt für den NEUEN Wert (describeValue(), z.B. "Datum wird auf
        // entfernt gesetzt"), aber für den bisherigen Stand passt nur ein
        // neutrales "kein Wert vorhanden" - "–", gleiche Konvention wie
        // überall sonst in der Übersicht.
        return $raw !== null && $raw !== '' ? (string) $raw : '–';
    }

    /**
     * Neuer Wert für die Tabelle - bei Anhängen-Feldern (Bemerkungen/
     * Änderungsprotokoll) einfach der neue Eintragstext, sonst dieselbe
     * Formatierung wie im Bestätigungstext (describeValue()).
     */
    private function describeNewValue(array $field, mixed $value): string
    {
        if (($field['storage'] ?? 'column') === 'note') {
            return (string) $value;
        }

        return $this->describeValue($field, $value);
    }
}
