<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\ProjectWorkflowStepPerson;
use App\Mail\WorkflowStepFreigabeMail;
use App\Models\Task;
use App\Models\WorkflowStepFreigabeRequest;
use App\Services\ProjectDirectoryLocator;
use App\Services\WorkflowStepActivator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectWorkflowStepController extends Controller
{
    public function __construct(
        private readonly WorkflowStepActivator $activator,
        private readonly ProjectDirectoryLocator $locator,
    ) {}

    /**
     * Termin (due_date) einer einzelnen WFS-Instanz ändern - eigenständiges
     * Inline-Feld im Workflow-Schritte-Tab, unabhängig vom großen
     * Projekt-Detail-Formular speicherbar.
     */
    public function updateDueDate(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate(['due_date' => ['nullable', 'date']]);

        $projectWorkflowStep->update(['due_date' => $validated['due_date'] ?? null]);

        return response()->json(['due_date' => $projectWorkflowStep->due_date?->format('Y-m-d')]);
    }

    /**
     * "Freigabe erteilen/zurücknehmen" - Sonderbutton js_function=wfs_freigabe
     * (Vietto: wffkt_getbuttontag(), case "wfs_freigabe"). Nutzt milestone_done_at
     * (Vietto: blnMSdone) als einfaches Gesetzt/Ungesetzt-Flag, kein eigenes
     * "wer hat freigegeben"-Feld - hatte Vietto an dieser Stelle auch nicht.
     * Nur am aktuell aktiven Schritt bedienbar (gleiche Einschränkung wie in
     * Vietto: dort nur ein reiner Status-Text ohne Button, wenn nicht
     * iscurrentwfs).
     */
    public function toggleFreigabe(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($request->user()->can('workflow_step.activate'), 403);

        // Veraltete Ansicht (Ralf, 2026-09-26): z.B. wurde die Freigabe inzwischen per
        // Mail-Link erteilt. Nicht still umschalten, sondern melden - besonders wichtig
        // beim Umschalten, denn sonst würde ein "Erteilen" auf altem Stand zum Zurücknehmen.
        $staleMessage = __('Der Stand hat sich inzwischen geändert (z. B. wurde die Freigabe bereits per E-Mail erteilt). Die Ansicht wird aktualisiert.');
        if (! $projectWorkflowStep->is_current) {
            return response()->json(['message' => $staleMessage], 409);
        }

        $granted = $projectWorkflowStep->milestone_done_at === null;
        if ($request->has('grant') && $request->boolean('grant') !== $granted) {
            return response()->json(['message' => $staleMessage], 409);
        }
        $nextStepTitle = null;

        if ($granted) {
            // Freigabe per Button und per externem Mail-Link müssen
            // identisch wirken (Ralf, 2026-09-26) - beide über denselben
            // Service-Aufruf, inkl. automatischem Folge-WFS. Nur beim
            // ERTEILEN, nicht beim Zurücknehmen (ein bereits ausgelöster
            // Folge-WFS wird nicht rückgängig gemacht).
            $nextStepTitle = $this->activator->grantFreigabe($project, $projectWorkflowStep, $request->user()->person);
        } else {
            $projectWorkflowStep->update(['milestone_done_at' => null]);
            Activity::log($project, ActivityType::WorkflowStepActivated, __('Freigabe für ":title" zurückgenommen.', ['title' => $projectWorkflowStep->workflowStep->title]));
        }

        return response()->json([
            'milestone_done_at' => $projectWorkflowStep->milestone_done_at?->toIso8601String(),
            'next_step_title' => $nextStepTitle,
        ]);
    }

    /**
     * Bestätigungs-Dialog vor dem Aktivieren eines Schritts (Empfänger,
     * E-Mail-Optionen) - eigenständiges globales Modal (siehe
     * layouts/app.blade.php), lädt seinen Inhalt selbst per fetch(), analog
     * zum Illustrationsaufträge-Modal.
     */
    public function activateForm(Project $project, ProjectWorkflowStep $projectWorkflowStep): View
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);

        $projectWorkflowStep->loadMissing('workflowStep.functionGroups');

        // Freigabe-WFS: Quelle/Ziel werden aus dem lokalen Arbeitsverzeichnis
        // des Projekts gewählt (siehe WorkflowStepFreigabeRequest).
        $freigabe = null;
        if ($projectWorkflowStep->workflowStep->isFreigabeStep()) {
            $projectPath = $this->locator->arbeitsverzeichnisProjectPath($project);
            $targetOptions = $projectPath ? $this->locator->flatOptions($projectPath, dirsOnly: true) : [];
            $freigabe = [
                'available' => $projectPath !== null,
                'sourceOptions' => $projectPath ? $this->locator->flatOptions($projectPath) : [],
                'targetOptions' => $targetOptions,
                'defaultTarget' => $this->lastKorrekturTarget($projectWorkflowStep, array_column($targetOptions, 'path')),
            ];
        }

        return view('projekte.partials.activate-workflow-step-body', [
            'project' => $project,
            'projectWorkflowStep' => $projectWorkflowStep,
            'recipients' => Task::recipientsFor($projectWorkflowStep),
            'freigabe' => $freigabe,
        ]);
    }

    /**
     * Schritt aktivieren - Vietto-Vorbild: ajax_workflow_set_step.php (nur
     * das neue System, intTyp=2). Macht mehrere zusammengehörige Dinge auf
     * einmal:
     * - is_current umsetzen, vorherigen Schritt bei Vorwärtsbewegung als
     *   erledigt markieren (bei Rücksprung/Korrekturschleife NICHT - der war
     *   ja gerade nicht fertig, deshalb der Rücksprung).
     * - Projekt-Status automatisch aus der Kastenfarbe des neuen Schritts
     *   ableiten (lifecycle_status 1-4 -> Status 0-3, identische Reihenfolge).
     * - Aufgaben-Rebuild passiert automatisch über ProjectWorkflowStepObserver.
     * - Vorgang loggen, optional E-Mail an Zuständige (siehe FileLogTransport
     *   für den Testbetrieb ohne echten SMTP).
     * - Weiche Warnung (kein Blocker) bei offenen Illustrationsaufträgen,
     *   wenn zu einem Beenden/Verwerfen-Schritt gewechselt wird.
     */
    public function activate(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($request->user()->can('workflow_step.activate'), 403);

        $validated = $request->validate([
            'send_email' => ['boolean'],
            'send_copy_to_self' => ['boolean'],
            'message' => ['nullable', 'string'],
        ]);

        $projectWorkflowStep->loadMissing('workflowStep.functionGroups');
        $target = $projectWorkflowStep;

        // Beenden/Verwerfen (lifecycle_status 3/4) braucht zusätzlich
        // project.complete - workflow_step.activate oben deckt nur das
        // normale Aktivieren/Zurückspringen ab.
        if (in_array($target->workflowStep->lifecycle_status, [3, 4], true)) {
            abort_unless($request->user()->can('project.complete'), 403);
        }
        $person = $request->user()->person;
        $ccEmail = $request->boolean('send_copy_to_self') ? $request->user()->email : null;

        // Freigabe-WFS mit "E-Mail senden": statt der normalen WFS-Mail geht
        // die Freigabe-Mail mit den signierten Links raus. Alle Prüfungen
        // VOR dem Aktivieren, damit bei einem Fehler nichts halb passiert.
        $freigabeData = null;
        if ($request->boolean('send_email') && $target->workflowStep->isFreigabeStep()) {
            $freigabeData = $this->validateFreigabeRequest($request, $project, $target);
        }

        $result = $this->activator->activate(
            $project,
            $target,
            $person,
            sendEmail: $request->boolean('send_email') && ! $freigabeData,
            ccEmail: $ccEmail,
            message: $validated['message'] ?? null,
        );

        if ($freigabeData) {
            // Erst NACH der Aktivierung anlegen: sie beendet alte offene Mail-Anfragen dieses
            // Schritts und würde sonst auch die neue beenden.
            $freigabeRequest = WorkflowStepFreigabeRequest::create($freigabeData + [
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'project_workflow_step_id' => $target->id,
                'triggered_by_person_id' => $person?->id,
            ]);

            $mail = Mail::to(Task::recipientsFor($target)->pluck('email')->filter()->all());
            if ($ccEmail) {
                $mail->cc($ccEmail);
            }
            $mail->send(new WorkflowStepFreigabeMail($freigabeRequest, $validated['message'] ?? null));
        }

        return response()->json($result);
    }

    /**
     * Vorauswahl fürs Ziel-Verzeichnis (Ralf, 2026-09-26): zuletzt genommenes
     * Ziel - erst für denselben Schritt im selben Projekt, dann für denselben
     * Workflow-Schritt in irgendeinem Projekt des Kunden. Nur ein Ordner, den
     * es im Arbeitsverzeichnis dieses Projekts auch gibt.
     *
     * @param  list<string>  $existingPaths
     */
    private function lastKorrekturTarget(ProjectWorkflowStep $pws, array $existingPaths): ?string
    {
        $tiers = [
            WorkflowStepFreigabeRequest::query()->where('project_workflow_step_id', $pws->id),
            WorkflowStepFreigabeRequest::query()->whereHas('projectWorkflowStep', fn ($query) => $query->where('workflow_step_id', $pws->workflow_step_id)),
        ];

        foreach ($tiers as $query) {
            $paths = $query->latest('id')->limit(10)->pluck('korrektur_target_path');
            $match = $paths->first(fn ($path) => in_array($path, $existingPaths, true));
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Prüft Quelle/Ziel gegen das Arbeitsverzeichnis des Projekts und legt
     * die Angaben für den Freigabe-Request (Ralf, 2026-09-26: Quelle
     * optional, Ziel-Ordner Pflicht, jeweils aussagekräftige Fehlermeldung
     * statt stillem Fehlschlag). Angelegt wird der Request erst nach der
     * Aktivierung, siehe activate().
     *
     * @return array{source_path: ?string, korrektur_target_path: string}
     */
    private function validateFreigabeRequest(Request $request, Project $project, ProjectWorkflowStep $target): array
    {
        $step = $target->workflowStep;

        if (! $step->after_freigabe_workflow_step_id) {
            throw ValidationException::withMessages(['send_email' => __('Für den Schritt „:title“ ist kein Folge-Schritt nach der Freigabe festgelegt - die Freigabe-Mail kann nicht verschickt werden. Bitte im Workflow ergänzen.', ['title' => $step->title])]);
        }

        if (! Task::recipientsFor($target)->contains(fn ($recipient) => ! empty($recipient->email))) {
            throw ValidationException::withMessages(['send_email' => __('Für niemanden der Zuständigen ist eine E-Mail-Adresse hinterlegt - Versand nicht möglich.')]);
        }

        $projectPath = $this->locator->arbeitsverzeichnisProjectPath($project);
        if ($projectPath === null) {
            throw ValidationException::withMessages(['freigabe_target_path' => __('Im Arbeitsverzeichnis gibt es keinen Ordner, der mit :pn beginnt. Die Daten gelangen erst durch Auschecken aus dem gesperrten Verzeichnis ins Arbeitsverzeichnis.', ['pn' => $project->source_pn])]);
        }

        $targetPath = trim((string) $request->input('freigabe_target_path'));
        if ($targetPath === '') {
            throw ValidationException::withMessages(['freigabe_target_path' => __('Bitte wählen Sie das Ziel-Verzeichnis für die Korrektur aus.')]);
        }
        $targetAbsolute = $this->locator->resolveRelative($projectPath, $targetPath);
        if ($targetAbsolute === null || ! is_dir($targetAbsolute)) {
            throw ValidationException::withMessages(['freigabe_target_path' => __('Erwartetes Verzeichnis: :dir nicht gefunden.', ['dir' => $targetPath])]);
        }

        $sourcePath = trim((string) $request->input('freigabe_source_path')) ?: null;
        if ($sourcePath !== null) {
            $sourceAbsolute = $this->locator->resolveRelative($projectPath, $sourcePath);
            if ($sourceAbsolute === null || ! file_exists($sourceAbsolute)) {
                throw ValidationException::withMessages(['freigabe_source_path' => __('Die gewählte Quelle „:path“ wurde nicht gefunden.', ['path' => $sourcePath])]);
            }
        }

        return ['source_path' => $sourcePath, 'korrektur_target_path' => $targetPath];
    }

    /**
     * Checkbox-Liste aller Mitglieder EINER Funktionsgruppe für EINEN
     * Schritt (Ralf, 2026-09-12: "analog zu Vietto" - dort öffnet ein
     * Klick auf die Funktionsgruppe im WFS genau so eine Liste, siehe
     * ajax_workflow_getfktpers.php). Angeboten werden alle Mitglieder der
     * Funktionsgruppe (nicht nur schon projektweit zugewiesene). Vorangehakt
     * ist, wer AKTUELL zuständig ist (Override falls vorhanden, sonst der
     * projektweite Fallback, siehe Task::assignedPeopleFor()) - Ralfs
     * Bug-Report zur Vorversion: eine leere Checkliste trotz sichtbar
     * zuständiger Person wirkte wie ein Fehler. Speichern (updatePeople())
     * übernimmt immer den KOMPLETTEN angehakten Zustand als neue
     * Schritt-Ausnahme, nicht einzelne Häkchen - sonst würde das erste
     * Anhaken einer zusätzlichen Person die vorbelegten (nur über den
     * Fallback zuständigen) Personen stillschweigend rauswerfen.
     */
    public function peopleForm(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep, FunctionGroup $functionGroup): View
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($functionGroup->tenant_id === $project->tenant_id, 404);

        $projectWorkflowStep->loadMissing('workflowStep.functionGroups', 'people');
        abort_unless($projectWorkflowStep->workflowStep->functionGroups->contains('id', $functionGroup->id), 404);

        // withoutGlobalScope('tenant') + visibleInTenant(): sonst wären per
        // Kundenzugriff freigegebene Mitglieder (anderer Heimat-Mandant)
        // unsichtbar - gleiches Muster wie ProjectController::detailData()
        // (allFunctionGroups), sonst identischer Bug an zweiter Stelle.
        $members = $functionGroup->members()
            ->withoutGlobalScope('tenant')
            ->visibleInTenant($project->tenant_id)
            ->visibleToRole($request->user()->role)
            ->get();

        return view('projekte.partials.workflow-step-people-picker', [
            'project' => $project,
            'pws' => $projectWorkflowStep,
            'group' => $functionGroup,
            'members' => $members,
            'currentPersonIds' => Task::assignedPeopleFor($projectWorkflowStep, $functionGroup)->pluck('id'),
            // Erstansprechpartner (★) ist ein projektweites Konzept
            // (project_people.is_primary) - auch hier zeigen, damit beim
            // Zuweisen sichtbar ist, wer das aktuell ist (Ralf, 2026-09-12).
            'primaryPersonId' => ProjectPerson::query()
                ->where('project_id', $project->id)
                ->where('function_group_id', $functionGroup->id)
                ->where('is_primary', true)
                ->value('person_id'),
        ]);
    }

    /**
     * Speichert den KOMPLETTEN angehakten Zustand als neue Schritt-Ausnahme
     * (project_workflow_step_people) - kein Einzel-Toggle mehr (siehe
     * peopleForm()). Gibt nur den aktualisierten Zuständigkeits-Block
     * dieses EINEN Schritts zurück (workflow-step-people.blade.php), nicht
     * das ganze Projekt neu.
     */
    public function updatePeople(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep, FunctionGroup $functionGroup): Response
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($functionGroup->tenant_id === $project->tenant_id, 404);
        abort_unless($request->user()->can('project.people.manage'), 403);

        $personIds = collect($request->array('person_ids'))->map(fn ($id) => (int) $id);
        // withoutGlobalScope + visibleInTenant: sonst würden per
        // Kundenzugriff freigegebene Personen beim Speichern stillschweigend
        // wieder rausfallen (angehakt, aber nicht übernommen) - gleicher
        // Bug wie bei FunctionGroupController::updateMembers() vorher.
        $validIds = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($project->tenant_id)->whereIn('id', $personIds)->pluck('id');

        // Vor der Änderung merken, wer HIER effektiv zuständig war (Override
        // ODER Fallback) - Ralf, 2026-09-12: "wenn ich bei 260001 die beiden
        // PM komplett beim WFS rausnehme, bleiben sie drin stehen [in
        // Projektbeteiligte Personen]". Ursache: waren nur über den Fallback
        // zuständig (kein eigener Override), ihr Rausnehmen hier löschte
        // also gar keine Override-Zeile - project_people blieb unberührt.
        $previouslyEffectiveIds = Task::assignedPeopleFor($projectWorkflowStep, $functionGroup)->pluck('id');

        ProjectWorkflowStepPerson::query()
            ->where('project_workflow_step_id', $projectWorkflowStep->id)
            ->where('function_group_id', $functionGroup->id)
            ->whereNotIn('person_id', $validIds)
            ->delete();

        foreach ($validIds as $personId) {
            ProjectWorkflowStepPerson::query()->firstOrCreate([
                'project_workflow_step_id' => $projectWorkflowStep->id,
                'function_group_id' => $functionGroup->id,
                'person_id' => $personId,
            ], ['tenant_id' => $project->tenant_id]);

            // Wechselwirkung (Ralf, 2026-09-12): wer einem WFS hinzugefügt
            // wird, muss auch unter "Projektbeteiligte Personen" auftauchen
            // - sonst wäre die Person am Projekt beteiligt, ohne dass das an
            // der zentralen Stelle sichtbar ist. is_primary bleibt
            // unangetastet, falls die Zeile schon existiert.
            ProjectPerson::query()->firstOrCreate([
                'project_id' => $project->id,
                'function_group_id' => $functionGroup->id,
                'person_id' => $personId,
            ], ['tenant_id' => $project->tenant_id]);
        }

        // Gleiche Wechselwirkung in die andere Richtung: wer hier abgehakt
        // wurde und für diese Funktionsgruppe an KEINEM anderen Schritt
        // dieses Projekts mehr per Override auftaucht, fällt auch aus
        // "Projektbeteiligte Personen" raus - sonst bliebe die Person dort
        // trotz "rausgenommen" stehen (Vietto macht das identisch, siehe
        // ajax_workflow_editperson.php). Einzeln statt Bulk-delete(), damit
        // ProjectPersonObserver (Aufgaben-Rebuild) pro Zeile feuert.
        foreach ($previouslyEffectiveIds->diff($validIds) as $removedPersonId) {
            $stillNeededElsewhere = ProjectWorkflowStepPerson::query()
                ->where('function_group_id', $functionGroup->id)
                ->where('person_id', $removedPersonId)
                ->whereHas('projectWorkflowStep', fn ($query) => $query->where('project_id', $project->id))
                ->exists();

            if (! $stillNeededElsewhere) {
                ProjectPerson::query()
                    ->where('project_id', $project->id)
                    ->where('function_group_id', $functionGroup->id)
                    ->where('person_id', $removedPersonId)
                    ->get()
                    ->each->delete();
            }
        }

        $html = view('projekte.partials.workflow-step-people', [
            'project' => $project,
            'pws' => $projectWorkflowStep->fresh(['workflowStep.functionGroups', 'people']),
        ])->render();

        return response($html);
    }

    /**
     * Lädt NUR die Zuständigkeits-Box EINES Schritts neu (Ralf, 2026-09-12:
     * Schritt 2 zeigte "–", obwohl Schritt 4 (per Override) Paul/Peter
     * zuständig hatte und die dabei ausgelöste Wechselwirkung die beiden
     * korrekt in project_people aufgenommen hatte - Schritt 2 hätte sie
     * über den Fallback also längst zeigen müssen, tat es aber nicht, weil
     * nur die EINE bearbeitete Schritt-Box und "Projektbeteiligte Personen"
     * selbst neu geladen wurden, nicht die ANDEREN, nur über den Fallback
     * betroffenen Schritte). Jede Schritt-Box hört jetzt selbst auf
     * "project-people-changed" (siehe workflow-step-people.blade.php) und
     * ruft sich darüber neu ab.
     */
    public function peopleSummary(Project $project, ProjectWorkflowStep $projectWorkflowStep): Response
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);

        $html = view('projekte.partials.workflow-step-people', [
            'project' => $project,
            'pws' => $projectWorkflowStep->fresh(['workflowStep.functionGroups', 'people']),
        ])->render();

        return response($html);
    }
}
