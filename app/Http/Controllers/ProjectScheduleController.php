<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
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
    public function form(Request $request, Project $project): View
    {
        // Optionale Vorauswahl: Klick auf das kleine "Termine berechnen"-Icon
        // direkt neben einem Terminfeld markiert diesen Schritt schon als
        // Referenz (Ralf: "kleine schicke Symbole direkt neben die
        // Terminfelder" statt einem einzelnen, "verloren" wirkenden Button).
        $steps = $this->scheduleStepsFor($project);
        $referenceStepId = $request->integer('reference_step_id') ?: null;
        if ($referenceStepId !== null && ! $steps->contains('id', $referenceStepId)) {
            $referenceStepId = null;
        }

        return view('projekte.partials.schedule-body', [
            'project' => $project,
            'steps' => $steps,
            'proposal' => null,
            'referenceStepId' => $referenceStepId,
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

        $proposal = $this->calculatorFor($project)->recalculate($steps, $reference, $reference->due_date);

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

        $proposal = $this->calculatorFor($project)->recalculate($steps, $reference, $reference->due_date);

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

            // Ralf-Bug-Report, 2026-09-14: Übersicht zeigte nach "Termin
            // berechnen" noch das alte Start-Datum. Root Cause: der Query-
            // Builder-update() hier lief direkt auf der DB-Zeile und feuerte
            // dadurch NIE ProjectWorkflowStepObserver::saved() - der als
            // Start/Ende markierte Schritt (effectiveIsStart/-End) bekam
            // zwar sein neues Datum, project.start_date/end_date blieben
            // aber auf dem alten Stand stehen. Jetzt über das schon
            // geladene Model-Objekt (fürs Observer-Event nötig), nicht mehr
            // per whereKey()-Bulk-Update.
            $steps->firstWhere('id', $stepId)?->update(['due_date' => $date]);
        }

        // Einzelübernahme: in der Vorschau bleiben (weitere Zeilen prüfen/übernehmen).
        // Alle übernehmen: zurück zur normalen Ansicht (Vietto: Dialog schließt).
        $steps = $this->scheduleStepsFor($project);
        if ($toApply !== null) {
            $reference = $steps->firstWhere('id', $reference->id);
            $proposal = $this->calculatorFor($project)->recalculate($steps, $reference, $reference->due_date);

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
    /**
     * Dauern der Schritte dieses Projekts an den Projektzeitraum anpassen (Ralf, 2026-10-06): die im Workflow hinterlegten Standarddauern sind
     * nur Richtwerte; hier werden sie mit dem Faktor Projektzeitraum / Zeitbedarf umgerechnet und als Dauern AM PROJEKT gespeichert
     * (der Workflow selbst bleibt unverändert). Termine werden dabei nicht verändert - dafür gibt es "Termine berechnen".
     */
    public function adjustDurations(Request $request, Project $project, \App\Services\ProjectPlanningCalculator $calculator): JsonResponse
    {
        abort_unless($request->user()->can('project.view') && $request->user()->can('workflow_step.due_date'), 403);

        $durations = $calculator->scaledDurations($project);
        abort_if($durations === null, 422, __('Für dieses Projekt lassen sich die Dauern nicht anpassen: Es fehlt ein Workflow oder ein vollständiger Projektzeitraum.'));

        foreach ($durations as $stepId => $days) {
            $row = ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
                ->where('project_id', $project->id)->where('workflow_step_id', $stepId)->first();
            if ($row === null) {
                $row = new ProjectWorkflowStep(['tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'workflow_step_id' => $stepId]);
            }
            $row->duration_days = $days;
            $row->save();
        }

        return response()->json(['durations' => $durations]);
    }

    /**
     * Dauern aus dem Zeitraum-Diagramm speichern (Ralf, 2026-10-07): je Schritt "In Bearbeitung" eine Dauer in Arbeitstagen am Projekt.
     * Die Termine der Schritte bleiben unberührt - die setzt "Termine berechnen" auf ausdrücklichen Wunsch.
     */
    public function saveDurations(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->can('project.view') && $request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate(['durations' => ['required', 'array', 'min:1'], 'durations.*' => ['integer', 'min:1', 'max:3650']]);
        $allowed = \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')
            ->where('workflow_id', $project->workflow_id)
            ->where('lifecycle_status', \App\Models\WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_if(array_diff(array_map('intval', array_keys($validated['durations'])), $allowed) !== [], 422, __('Mindestens ein Schritt gehört nicht zum Workflow des Projekts.'));

        foreach ($validated['durations'] as $stepId => $days) {
            $row = ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
                ->where('project_id', $project->id)->where('workflow_step_id', $stepId)->first()
                ?? new ProjectWorkflowStep(['tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'workflow_step_id' => $stepId]);
            $row->duration_days = (int) $days;
            $row->save();
        }

        return response()->json(['saved' => count($validated['durations'])]);
    }

    /**
     * Sperre der Dauer eines Schritts am Projekt setzen (Ralf, 2026-10-07). Entspricht der Wert der Voreinstellung am Workflow-Schritt,
     * wird nichts Abweichendes gespeichert (null = Voreinstellung gilt).
     */
    public function setDurationLock(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->can('project.view') && $request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate(['step_id' => ['required', 'integer'], 'locked' => ['required', 'boolean']]);
        $step = \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')
            ->where('workflow_id', $project->workflow_id)->where('id', $validated['step_id'])->first();
        abort_if($step === null, 422, __('Der Schritt gehört nicht zum Workflow des Projekts.'));

        $row = ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
            ->where('project_id', $project->id)->where('workflow_step_id', $step->id)->first()
            ?? new ProjectWorkflowStep(['tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'workflow_step_id' => $step->id]);
        $locked = (bool) $validated['locked'];
        $row->duration_locked = $locked === (bool) $step->duration_locked ? null : $locked;
        $row->save();

        return response()->json(['locked' => $locked]);
    }

    /**
     * Projektstart oder -ende setzen (Ralf, 2026-10-07): Zeitraum an die Dauern des Workflows anpassen. Ist ein Workflow-Schritt als
     * Start bzw. Ende markiert, ist DER die Quelle des Projektdatums (ProjectWorkflowStepObserver) - dann wird dessen Termin gesetzt
     * und das Projektdatum folgt; sonst wird das Projektdatum direkt gesetzt.
     */
    public function setPeriod(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->can('project.view') && $request->user()->can('workflow_step.due_date'), 403);

        $validated = $request->validate(['side' => ['required', 'in:start,end'], 'date' => ['required', 'date']]);
        $isStart = $validated['side'] === 'start';
        $date = \Carbon\CarbonImmutable::parse($validated['date']);
        $other = $isStart ? $project->end_date : $project->start_date;
        abort_if($other !== null && ($isStart ? $date->gt($other) : $date->lt($other)), 422, __('Das Datum passt nicht zum anderen Rand des Projektzeitraums.'));

        $marker = $project->projectWorkflowSteps()->get()->first(
            fn (ProjectWorkflowStep $step) => $step->isScheduleStepForCurrentWorkflow() && ($isStart ? $step->effectiveIsStart() : $step->effectiveIsEnd())
        );
        if ($marker) {
            $marker->update(['due_date' => $date->toDateString()]);
        } else {
            $project->update([$isStart ? 'start_date' : 'end_date' => $date->toDateString()]);
        }

        return response()->json(['date' => $date->toDateString()]);
    }

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

    /** Rechner mit den aktiven Feiertagen der Organisation des Projekts. */
    private function calculatorFor(Project $project): WorkflowScheduleCalculator
    {
        return new WorkflowScheduleCalculator(
            Holiday::query()->withoutGlobalScope('tenant')->where('tenant_id', $project->tenant_id)->where('active', true)->pluck('date')
        );
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
