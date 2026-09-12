<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Mail\WorkflowStepActivatedMail;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\ProjectWorkflowStepPerson;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ProjectWorkflowStepController extends Controller
{
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
        abort_unless($projectWorkflowStep->is_current, 422);

        $granted = $projectWorkflowStep->milestone_done_at === null;
        $projectWorkflowStep->update(['milestone_done_at' => $granted ? now() : null]);

        Activity::log(
            $project,
            ActivityType::WorkflowStepActivated,
            $granted
                ? __('Freigabe für ":title" erteilt.', ['title' => $projectWorkflowStep->workflowStep->title])
                : __('Freigabe für ":title" zurückgenommen.', ['title' => $projectWorkflowStep->workflowStep->title])
        );

        return response()->json(['milestone_done_at' => $projectWorkflowStep->milestone_done_at?->toIso8601String()]);
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

        return view('projekte.partials.activate-workflow-step-body', [
            'project' => $project,
            'projectWorkflowStep' => $projectWorkflowStep,
            'recipients' => $this->recipientsFor($projectWorkflowStep),
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
        $currentStep = $project->projectWorkflowSteps->firstWhere('is_current', true);
        $person = $request->user()->person;

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

        // Status automatisch aus der Kastenfarbe des neuen Schritts ableiten
        // (lifecycle_status 1=Geplant..4=Verworfen -> Status 0=Geplant..3=Verworfen).
        $project->update(['status' => $target->workflowStep->lifecycle_status - 1]);

        Activity::log($project, ActivityType::WorkflowStepActivated, __('Workflow-Schritt ":title" aktiviert.', ['title' => $target->workflowStep->title]));

        if ($request->boolean('send_email')) {
            $recipientEmails = $this->recipientsFor($target)->pluck('email')->filter()->all();

            if (! empty($recipientEmails)) {
                $mail = Mail::to($recipientEmails);
                if ($request->boolean('send_copy_to_self') && $request->user()->email) {
                    $mail->cc($request->user()->email);
                }
                $mail->send(new WorkflowStepActivatedMail($target, $person, $validated['message'] ?? null));
            }
        }

        // Weiche Warnung: offene Illustrationsaufträge bei Beenden/Verwerfen (lifecycle_status 3/4).
        $openGraphicOrdersCount = null;
        if (in_array($target->workflowStep->lifecycle_status, [3, 4], true)) {
            $openStatusValues = array_map(fn ($status) => $status->value, array_filter(\App\Enums\GraphicOrderStatus::cases(), fn ($status) => $status->isOpen()));
            $count = $project->graphicOrders()->whereIn('graphic_order_status_id', $openStatusValues)->count();
            $openGraphicOrdersCount = $count > 0 ? $count : null;
        }

        return response()->json(['open_graphic_orders_count' => $openGraphicOrdersCount]);
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

    /**
     * Wer für diesen Schritt zuständig ist, über alle seine Funktionsgruppen
     * hinweg (Override pro Schritt hat Vorrang, sonst projektweite
     * Zuweisung) - reine Wiederverwendung von Task::assignedPeopleFor().
     *
     * @return Collection<int, \App\Models\Person>
     */
    private function recipientsFor(ProjectWorkflowStep $step): Collection
    {
        return $step->workflowStep->functionGroups
            ->flatMap(fn (FunctionGroup $group) => Task::assignedPeopleFor($step, $group))
            ->unique('id')
            ->values();
    }
}
