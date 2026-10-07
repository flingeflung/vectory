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
    /**
     * Aufschlüsselung des Zeitbedarfs für die Anzeige (Ralf, 2026-10-06): jeder Schritt "In Bearbeitung" mit seiner Dauer in Arbeitstagen
     * (Wert am Projekt, sonst Standard am Schritt, mindestens 1) - dieselbe Rechnung wie forProject().
     *
     * @return array{steps: list<array{id: int, title: string, days: int, default_used: bool}>, sum: int}|null null = kein Workflow oder keine Arbeitsschritte
     */
    public function breakdown(Project $project): ?array
    {
        if (! $project->workflow_id) {
            return null;
        }
        $steps = WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $project->workflow_id)
            ->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->orderBy('sort')->get(['id', 'title', 'duration_days', 'duration_locked']);
        if ($steps->isEmpty()) {
            return null;
        }

        $overrides = $this->projectDurations($project->id);
        $rows = [];
        foreach ($steps as $step) {
            $hasOverride = array_key_exists($step->id, $overrides) && $overrides[$step->id] !== null;
            $raw = $hasOverride ? $overrides[$step->id] : $step->duration_days;
            $days = max(self::MIN_STEP_DAYS, (int) $raw);
            $rows[] = ['id' => (int) $step->id, 'title' => (string) $step->title, 'days' => $days, 'default_used' => (int) $raw < self::MIN_STEP_DAYS, 'locked' => (bool) $step->duration_locked];
        }

        return ['steps' => $rows, 'sum' => array_sum(array_column($rows, 'days'))];
    }

    /**
     * Dauern der Schritte "In Bearbeitung" auf den Projektzeitraum umgerechnet (Ralf, 2026-10-06): jede Dauer mal Faktor als ganze Tage,
     * mindestens 1 Tag je Schritt, in der Summe möglichst genau der Zeitraum (Rundung nach dem größten Rest).
     * Schritte ohne eingetragene Dauer (0) zählen in der Rechnung 1 Tag, werden aber NICHT umgerechnet und nicht zurückgegeben -
     * ihre 0 bleibt stehen. Passen schon die Mindestwerte nicht in den Zeitraum, bleibt es bei 1 Tag je Schritt.
     *
     * @return array<int, int>|null Schritt-ID => neue Dauer (nur Schritte mit eingetragener Dauer); null = kein Workflow oder Zeitraum
     */
    public function scaledDurations(Project $project, int $availableDays): ?array
    {
        $steps = $this->breakdownById($project);
        if ($steps === null || $availableDays <= 0) {
            return null;
        }

        // Ohne Dauer (zählt 1 Tag) oder gesperrt: bleibt, wie er ist, und wird beim Umrechnen nicht angefasst
        $keptDays = array_sum(array_map(fn ($step) => $step['days'], array_filter($steps, fn ($step) => $step['fixed'] || $step['locked'])));
        $weights = array_map(fn ($step) => $step['days'], array_filter($steps, fn ($step) => ! $step['fixed'] && ! $step['locked']));
        if ($weights === []) {
            return [];
        }

        $target = max(count($weights), $availableDays - $keptDays);
        $sum = array_sum($weights);
        $scaled = [];
        $ints = [];
        foreach ($weights as $stepId => $days) {
            $scaled[$stepId] = $days * $target / $sum;
            $ints[$stepId] = max(self::MIN_STEP_DAYS, (int) floor($scaled[$stepId]));
        }

        $diff = $target - array_sum($ints);
        while ($diff > 0) {
            // +1 beim Schritt mit dem größten Rest
            uksort($ints, fn ($a, $b) => ($scaled[$b] - $ints[$b]) <=> ($scaled[$a] - $ints[$a]));
            $ints[array_key_first($ints)]++;
            $diff--;
        }
        while ($diff < 0) {
            $candidates = array_filter($ints, fn ($days) => $days > self::MIN_STEP_DAYS);
            if ($candidates === []) {
                break;
            }
            // -1 beim längsten Schritt
            arsort($candidates);
            $ints[array_key_first($candidates)]--;
            $diff++;
        }

        ksort($ints);

        return $ints;
    }

    /** @return array<int, int>|null Schritt-ID => zählende Arbeitstage der Schritte "In Bearbeitung" in Ablaufreihenfolge (mindestens 1) */
    public function stepDays(Project $project): ?array
    {
        $steps = $this->breakdownById($project);

        return $steps === null ? null : array_map(fn ($step) => $step['days'], $steps);
    }

    /** @return array<int, array{days: int, fixed: bool, locked: bool}>|null Schritt-ID => Dauer (Wert am Projekt, sonst Standard, mindestens 1) und ob keine Dauer eingetragen ist */
    private function breakdownById(Project $project): ?array
    {
        if (! $project->workflow_id) {
            return null;
        }
        $steps = $this->workflow((int) $project->workflow_id)['steps'];
        if ($steps === []) {
            return null;
        }
        $overrides = $this->projectDurations($project->id);
        $result = [];
        foreach ($steps as $step) {
            $raw = array_key_exists($step->id, $overrides) && $overrides[$step->id] !== null ? (int) $overrides[$step->id] : (int) $step->duration_days;
            $result[$step->id] = ['days' => max(self::MIN_STEP_DAYS, $raw), 'fixed' => $raw < self::MIN_STEP_DAYS, 'locked' => (bool) $step->duration_locked];
        }

        return $result;
    }

    private function workflow(int $workflowId): array
    {
        return $this->workflowCache[$workflowId] ??= [
            'steps' => WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $workflowId)
                ->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->orderBy('sort')->get(['id', 'duration_days', 'duration_locked'])->all(),
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
