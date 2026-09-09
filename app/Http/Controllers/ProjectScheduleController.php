<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Services\WorkflowScheduleCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Terminberechnung für die Workflow-Schritte eines Projekts (Vietto-Vorbild:
 * ajax_workflow_edittermine.php). Eigenständiges globales Modal (siehe
 * layouts/app.blade.php), lädt seinen Inhalt selbst per fetch(), gleiches
 * Muster wie Illustrationsaufträge/Workflow-Schritt-aktivieren.
 *
 * Bewusst NUR für Schritte, die an der Vorlage has_due_date=true haben -
 * freistehende "nur Termin, kein WFS"-Zusatztermine (Vietto: valWFStepID=-1)
 * sind fürs erste nicht gebaut (seltene Randnutzung, siehe Ralf-Rücksprache:
 * 38 von tausenden Vietto-Projekten).
 */
class ProjectScheduleController extends Controller
{
    public function form(Project $project): View
    {
        return view('projekte.partials.schedule-body', [
            'project' => $project,
            'steps' => $this->scheduleStepsFor($project),
            'proposal' => null,
            'referenceStepId' => null,
        ]);
    }

    /**
     * "Neu berechnen" (Vietto: mode=2) - berechnet nur eine Vorschau,
     * speichert noch nichts.
     */
    public function recalculate(Request $request, Project $project): View
    {
        $validated = $request->validate(['reference_step_id' => ['required', 'integer']]);

        $steps = $this->scheduleStepsFor($project);
        $reference = $steps->firstWhere('id', $validated['reference_step_id']);
        abort_if($reference === null, 422, __('Referenz-Schritt gehört nicht zu diesem Projekt oder hat keinen Termin.'));
        abort_if($reference->due_date === null, 422, __('Referenz-Schritt hat kein gültiges Datum.'));

        $proposal = (new WorkflowScheduleCalculator())->recalculate($steps, $reference, $reference->due_date);

        return view('projekte.partials.schedule-body', [
            'project' => $project,
            'steps' => $steps,
            'proposal' => $proposal,
            'referenceStepId' => $reference->id,
        ]);
    }

    /**
     * Vorschlag übernehmen - entweder ein einzelner Schritt (Vietto:
     * "übernehmen", bleibt danach in der Vorschau) oder alle auf einmal
     * (Vietto: "alle übernehmen und schließen"). Berechnet bewusst
     * server-seitig NEU statt den vom Client mitgeschickten Terminen zu
     * vertrauen.
     */
    public function apply(Request $request, Project $project): View
    {
        $validated = $request->validate([
            'reference_step_id' => ['required', 'integer'],
            'apply_step_id' => ['nullable', 'integer'],
        ]);

        $steps = $this->scheduleStepsFor($project);
        $reference = $steps->firstWhere('id', $validated['reference_step_id']);
        abort_if($reference === null || $reference->due_date === null, 422);

        $proposal = (new WorkflowScheduleCalculator())->recalculate($steps, $reference, $reference->due_date);

        // (int)-Cast noetig: $stepId aus $proposal ist ein echtes int (Model-
        // Key), $validated['apply_step_id'] eine numerische Zeichenkette aus
        // dem Request - striktes !== haette sonst JEDE Zeile uebersprungen.
        $toApply = isset($validated['apply_step_id']) ? (int) $validated['apply_step_id'] : null;
        foreach ($proposal as $stepId => $date) {
            if ($toApply !== null && $stepId !== $toApply) {
                continue;
            }
            if ($stepId === $reference->id) {
                continue; // Referenz-Schritt selbst ändert sich nicht.
            }
            ProjectWorkflowStep::query()->whereKey($stepId)->update(['due_date' => $date]);
        }

        // Einzelübernahme: in der Vorschau bleiben (weitere Zeilen prüfen/übernehmen).
        // Alle übernehmen: zurück zur normalen Ansicht (Vietto: Dialog schließt).
        $steps = $this->scheduleStepsFor($project);
        if ($toApply !== null) {
            $reference = $steps->firstWhere('id', $reference->id);
            $proposal = (new WorkflowScheduleCalculator())->recalculate($steps, $reference, $reference->due_date);

            return view('projekte.partials.schedule-body', [
                'project' => $project,
                'steps' => $steps,
                'proposal' => $proposal,
                'referenceStepId' => $reference->id,
            ]);
        }

        return view('projekte.partials.schedule-body', [
            'project' => $project,
            'steps' => $steps,
            'proposal' => null,
            'referenceStepId' => null,
        ]);
    }

    /**
     * Kleinteilige Inline-Felder (Terminname/Dauer/Datum) - gleiches Muster
     * wie ProjectWorkflowStepController::updateDueDate(), nur generisch für
     * mehrere Felder statt nur due_date.
     */
    public function updateField(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate([
            'milestone_title' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
        ]);

        $projectWorkflowStep->update($validated);

        return response()->json([
            'milestone_title' => $projectWorkflowStep->effectiveMilestoneTitle(),
            'duration_days' => $projectWorkflowStep->effectiveDurationDays(),
            'due_date' => $projectWorkflowStep->due_date?->format('Y-m-d'),
        ]);
    }

    /**
     * Start-/Enddatum-Markierung - pro Projekt max. ein Schritt je Richtung
     * (Radio-Verhalten wie in Vietto), deshalb bei allen anderen Schritten
     * des Projekts zuerst zurückgesetzt.
     */
    public function setStartEnd(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);
        abort_unless($request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate(['type' => ['required', 'in:start,end']]);
        $field = $validated['type'] === 'start' ? 'is_start' : 'is_end';

        // Nur die Schritte der AKTUELL zugewiesenen Workflow-Generation
        // zurücksetzen, nicht projectWorkflowSteps() insgesamt - ein Projekt
        // behält Zeilen älterer Workflow-Generationen (siehe Kommentar bei
        // detail.blade.php currentSteps), die sollen unberührt bleiben.
        ProjectWorkflowStep::query()
            ->where('project_id', $project->id)
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_id', $project->workflow_id))
            ->update([$field => false]);
        $projectWorkflowStep->update([$field => true]);

        return response()->json([$field => true]);
    }

    /**
     * @return Collection<int, ProjectWorkflowStep>
     */
    private function scheduleStepsFor(Project $project): Collection
    {
        return $project->projectWorkflowSteps()
            ->with('workflowStep')
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_id', $project->workflow_id)->where('has_due_date', true))
            ->get()
            ->sortBy(fn (ProjectWorkflowStep $pws) => $pws->workflowStep->sort)
            ->values();
    }
}
