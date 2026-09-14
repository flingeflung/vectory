<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectNote;
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
        $overwriteDifferentWorkflow = $request->boolean('overwrite_different_workflow');
        $preview = $this->buildPreview($group, $field, $value, $overwriteDifferentWorkflow);

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
            'changeRows' => $this->describeChangeRows($preview['applicable'], $field, $value),
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
            'select' => ['required', Rule::in(array_keys($field['options']))],
        };

        $validator = Validator::make($request->all(), ['value' => $rules], [], ['value' => __('Neuer Wert')]);
        if ($validator->fails()) {
            return ['field' => $field, 'value' => null, 'errors' => $validator->errors()];
        }

        $value = $validator->validated()['value'] ?? null;
        if ($field['type'] === 'select' && $value !== null) {
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
        // 'workflow' mitgeladen für die "Alter Wert"-Spalte der Änderungs-
        // Tabelle (describeOldValue()) - vermeidet N+1 beim Feld workflow_id.
        $projects = $group->projects()->with(['projectWorkflowSteps', 'workflow'])->get();
        $unchanged = collect();

        if ($field['key'] === 'status') {
            $applicable = $projects->reject(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
            $skipped = $projects->filter(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
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

        $project->{$field['key']} = $value;
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

        return __(':field: :count Projekt(e) erfolgreich geändert.', ['field' => $field['label'], 'count' => $count]);
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

        if ($field['key'] === 'status') {
            return __(':count Projekt(e) werden übersprungen (aktueller Workflow-Schritt bestimmt den Status):', ['count' => $count]);
        }

        return trans_choice(':count Projekt wird übersprungen:|:count Projekte werden übersprungen:', $count, ['count' => $count]);
    }

    /**
     * Nur für Felder mit einem echten "unverändert"-Fall (aktuell nur
     * workflow_id, siehe buildPreview()) - null unterdrückt den Block in
     * multichange-body.blade.php komplett.
     */
    private function describeUnchangedNote(array $field, int $count): ?string
    {
        if ($field['key'] !== 'workflow_id' || $count === 0) {
            return null;
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
            'select' => $field['options'][$value] ?? (string) $value,
            'date' => Carbon::parse($value)->format('d.m.Y'),
            default => (string) $value,
        };
    }

    /**
     * Ralf, 2026-09-14: "kleine Tabelle... 1. Spalte Projektnr. + Bezeichnung,
     * 2. Alter Wert, 3. Neuer Wert" - für ALLE Multichange-Felder (nicht nur
     * Workflow), damit vor dem unwiderruflichen Anwenden genau sichtbar ist,
     * was sich je Projekt ändert. Nur für die tatsächlich betroffenen
     * ("applicable") Projekte - skipped/unchanged haben schon eigene Blöcke
     * mit Begründung.
     *
     * @param  Collection<int, Project>  $applicable
     * @return list<array{pn: string, title: string, old: string, new: string}>
     */
    private function describeChangeRows(Collection $applicable, array $field, mixed $value): array
    {
        return $applicable->map(fn (Project $project) => [
            'pn' => $project->source_pn,
            'title' => $project->title,
            'old' => $this->describeOldValue($project, $field),
            'new' => $this->describeNewValue($field, $value),
        ])->all();
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
            return $project->workflow?->name ?? __('entfernt');
        }

        $raw = ($field['storage'] ?? 'column') === 'attribute'
            ? ($project->attributes[$field['key']] ?? null)
            : $project->columnValue($field['key']);

        return $raw !== null && $raw !== '' ? (string) $raw : __('entfernt');
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
