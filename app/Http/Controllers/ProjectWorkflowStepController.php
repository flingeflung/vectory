<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Mail\WorkflowStepActivatedMail;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $count = $project->graphicOrders()->whereHas('status', fn ($query) => $query->where('is_open', true))->count();
            $openGraphicOrdersCount = $count > 0 ? $count : null;
        }

        return response()->json(['open_graphic_orders_count' => $openGraphicOrdersCount]);
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
