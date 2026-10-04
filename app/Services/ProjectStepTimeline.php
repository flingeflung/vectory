<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowGroupWindow;
use App\Models\WorkflowStep;

/**
 * Grobe Zeitleiste der Arbeitsschritte eines Projekts (Ralf, 2026-10-04), Grundlage für den Einsatzplan:
 * Aus Projektstart bis -ende und den Dauern der Schritte wird näherungsweise bestimmt, in welchem Zeitraum
 * welcher Schritt läuft.
 *
 * Regeln (mit Ralf abgestimmt):
 * - Nur Schritte "In Bearbeitung" (lifecycle_status 2) haben einen Zeitanteil.
 * - Gewicht eines Schritts = seine Dauer in Arbeitstagen (Wert am Projekt, sonst Standard am Schritt);
 *   fehlt die Dauer, zählt 1 Tag.
 * - Die Summe der Gewichte wird auf die Arbeitstage des Projekts gestreckt oder verdichtet.
 *   Wurde verdichtet (Summe größer als der Zeitraum), meldet das compressed.
 * - Echte Termine an den Schritten bleiben bewusst unberücksichtigt (erster Wurf).
 *
 * Maße sind Arbeitstage ab Projektstart (Dezimalwerte), nicht Kalendertage.
 */
class ProjectStepTimeline
{
    public const MIN_STEP_DAYS = 1;

    /** @var array<int, array{steps: list<WorkflowStep>, windows: \Illuminate\Support\Collection}> */
    private array $workflowCache = [];

    /** @var array<int, array<int, int|null>> */
    private array $projectDurationCache = [];

    /**
     * @return array{offsets: array<int, array{0: float, 1: float}>, compressed: bool, sum: float, available: int, factor: float}|null
     *                                                                                                                                null = keine Zeitleiste möglich (kein Workflow, keine Arbeitsschritte, keine Arbeitstage).
     */
    public function forProject(Project $project, int $eligibleDays): ?array
    {
        if (! $project->workflow_id || $eligibleDays <= 0) {
            return null;
        }
        $steps = $this->workflow((int) $project->workflow_id)['steps'];
        if ($steps === []) {
            return null;
        }

        $overrides = $this->projectDurations($project->id);
        $weights = [];
        foreach ($steps as $step) {
            $days = array_key_exists($step->id, $overrides) && $overrides[$step->id] !== null ? $overrides[$step->id] : $step->duration_days;
            $weights[$step->id] = max(self::MIN_STEP_DAYS, (int) $days);
        }

        $sum = (float) array_sum($weights);
        $factor = $eligibleDays / $sum;
        $offsets = [];
        $cursor = 0.0;
        foreach ($weights as $stepId => $weight) {
            $offsets[$stepId] = [$cursor, $cursor + $weight * $factor];
            $cursor += $weight * $factor;
        }

        return [
            'offsets' => $offsets,
            'compressed' => $sum > $eligibleDays + 0.0001,
            'sum' => $sum,
            'available' => $eligibleDays,
            'factor' => $factor,
        ];
    }

    /**
     * Einsatzzeitraum einer Funktionsgruppe als [von, bis] in Arbeitstagen ab Projektstart. Ohne Eintrag im
     * Einsatzplan (oder bei ungültigem Verweis) gilt die ganze Breite.
     *
     * @param  array{offsets: array<int, array{0: float, 1: float}>}  $timeline
     * @return array{0: float, 1: float}
     */
    public function windowFor(array $timeline, Project $project, ?int $functionGroupId, int $eligibleDays): array
    {
        $full = [0.0, (float) $eligibleDays];
        if ($functionGroupId === null) {
            return $full;
        }
        $window = $this->workflow((int) $project->workflow_id)['windows']->get($functionGroupId);
        if (! $window) {
            return $full;
        }

        $from = $window->from_step_id !== null ? ($timeline['offsets'][$window->from_step_id][0] ?? null) : 0.0;
        $to = $window->to_step_id !== null ? ($timeline['offsets'][$window->to_step_id][1] ?? null) : (float) $eligibleDays;
        if ($from === null || $to === null || $to <= $from) {
            return $full;
        }

        return [$from, $to];
    }

    /** @return array{steps: list<WorkflowStep>, windows: \Illuminate\Support\Collection} */
    private function workflow(int $workflowId): array
    {
        return $this->workflowCache[$workflowId] ??= [
            'steps' => WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $workflowId)
                ->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->orderBy('sort')->get(['id', 'duration_days'])->all(),
            'windows' => WorkflowGroupWindow::query()->withoutGlobalScope('tenant')->where('workflow_id', $workflowId)->get()->keyBy('function_group_id'),
        ];
    }

    /** @return array<int, int|null> Schritt-ID => am Projekt hinterlegte Dauer */
    private function projectDurations(int $projectId): array
    {
        return $this->projectDurationCache[$projectId] ??= ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
            ->where('project_id', $projectId)->pluck('duration_days', 'workflow_step_id')->all();
    }
}
