<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

/**
 * Terminrechnung des neuen Ablaufplans (Ralf, 2026-10-09; docs/ablaufplan-konzept.md, Anhang A): Aus Projektstart, den
 * Dauern der Phasen, den Feiertagen der Organisation und den Meilenstein-Definitionen ergeben sich alle Termine. Es gibt
 * genau diesen einen Schreiber (nur Projekte mit schedule_model = 2); die Ergebnisse stehen als Zwischenspeicher in den
 * vorhandenen Spalten, damit alle Verbraucher (Kritische Projekte, Kalender, Listen) unverändert weiterlaufen.
 *
 * Regeln: Eine Phase beginnt am nächsten Arbeitstag nach dem Ende der vorigen; die Dauer zählt inklusive des Endtags
 * (1 AT = Start und Ende am selben Tag). Meilensteine hängen an Phasen, nie umgekehrt, und dürfen vor dem Start
 * oder nach dem Ende liegen.
 */
class ProjectScheduler
{
    public const MIN_PHASE_DAYS = 1;

    private bool $running = false;

    /** Schreibt alle berechneten Termine des Projekts; tut nichts ohne Projektstart oder Workflow. */
    public function recalculate(Project $project): void
    {
        if ((int) $project->schedule_model !== 2 || ! $project->workflow_id || ! $project->start_date || $this->running) {
            return;
        }

        // Das Anlegen fehlender Zeilen löst Beobachter aus, die wieder hierher führen würden.
        $this->running = true;
        try {
            $this->recalculateNow($project);
        } finally {
            $this->running = false;
        }
    }

    private function recalculateNow(Project $project): void
    {
        $this->ensureStepRows($project);
        $this->syncMilestones($project);

        $plan = $this->plan($project);
        if ($plan === null) {
            return;
        }

        foreach ($plan['phases'] as $phase) {
            $this->writeDueDate($project, $phase['step_id'], $phase['end']);
        }

        // Status-Schritte: "Geplant" = Projektstart, "Beendet" = Projektende (Ende der letzten Phase)
        if ($plan['phases'] !== []) {
            $this->writeStatusStepDates($project, $plan['end']);
        }

        $endDate = $plan['end']?->toDateString();
        if ($endDate !== null && $project->end_date?->toDateString() !== $endDate) {
            DB::table('projects')->where('id', $project->id)->update(['end_date' => $endDate]);
            $project->setAttribute('end_date', $endDate);
            $project->syncOriginalAttribute('end_date');
        }

        foreach ($plan['milestones'] as $milestone) {
            ProjectMilestone::query()->withoutGlobalScopes()->whereKey($milestone['id'])
                ->update(['date' => $milestone['date']?->toDateString()]);
        }
    }

    /**
     * Rechnet, ohne zu schreiben (Grundlage für Diagramm und Tabelle).
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable|null, total: int, phases: list<array{step_id: int, title: string, days: int, start_idx: int, end_idx: int, start: CarbonImmutable, end: CarbonImmutable}>, milestones: list<array{id: int, name: string, idx: int|null, date: CarbonImmutable|null, is_market_launch: bool}>}|null
     */
    public function plan(Project $project, ?CarbonInterface $startOverride = null): ?array
    {
        $startDate = $startOverride ?? $project->start_date;
        if (! $project->workflow_id || ! $startDate) {
            return null;
        }

        $calendar = $this->calendarFor($project);
        $start = $calendar->onOrAfter($startDate);
        $structure = $this->structure($project);

        $phases = [];
        foreach ($structure['phases'] as $phase) {
            $phases[] = [
                ...$phase,
                'start' => $calendar->shift($start, $phase['start_idx']),
                'end' => $calendar->shift($start, $phase['end_idx']),
            ];
        }

        $milestones = [];
        foreach ($structure['milestones'] as $milestone) {
            $date = $milestone['idx'] !== null
                ? $calendar->shift($start, $milestone['idx'])
                : ($milestone['fixed_date'] ? CarbonImmutable::parse($milestone['fixed_date']) : null);
            $milestones[] = [...$milestone, 'date' => $date];
        }

        return [
            'start' => $start,
            'end' => $phases === [] ? null : end($phases)['end'],
            'total' => $structure['total'],
            'phases' => $phases,
            'milestones' => $milestones,
        ];
    }

    /**
     * Fixpunkt: Welcher Projektstart lässt $point auf $date fallen?
     *
     * @param  string  $point  workflow_start | workflow_end | step_start:{id} | step_end:{id} | milestone:{id}
     */
    public function startDateFor(Project $project, string $point, CarbonInterface $date): CarbonImmutable
    {
        $structure = $this->structure($project);
        $idx = $this->pointIndex($structure, $point);
        if ($idx === null) {
            throw new InvalidArgumentException('Unbekannter oder fest datierter Bezugspunkt: '.$point);
        }

        $calendar = $this->calendarFor($project);

        return $calendar->shift($calendar->onOrBefore($date), -$idx);
    }

    /** Arbeitstag-Index (ab Projektstart = 0) eines Bezugspunkts; null bei fest datierten Meilensteinen. */
    private function pointIndex(array $structure, string $point): ?int
    {
        if ($point === 'workflow_start') {
            return 0;
        }
        if ($point === 'workflow_end') {
            return max(0, $structure['total'] - 1);
        }
        [$kind, $id] = array_pad(explode(':', $point, 2), 2, null);
        $id = (int) $id;
        if ($kind === 'milestone') {
            foreach ($structure['milestones'] as $milestone) {
                if ($milestone['id'] === $id) {
                    return $milestone['idx'];
                }
            }

            return null;
        }
        foreach ($structure['phases'] as $phase) {
            if ($phase['step_id'] === $id) {
                return $kind === 'step_start' ? $phase['start_idx'] : ($kind === 'step_end' ? $phase['end_idx'] : null);
            }
        }

        return null;
    }

    /**
     * Phasen und Meilensteine als Arbeitstag-Indizes (unabhängig vom Kalender und vom Startdatum).
     *
     * @return array{total: int, phases: list<array{step_id: int, title: string, days: int, start_idx: int, end_idx: int}>, milestones: list<array{id: int, name: string, idx: int|null, fixed_date: string|null, is_market_launch: bool}>}
     */
    private function structure(Project $project): array
    {
        $steps = WorkflowStep::query()->withoutGlobalScopes()
            ->where('workflow_id', $project->workflow_id)->where('lifecycle_status', 2)->orderBy('sort')->get();
        $overrides = ProjectWorkflowStep::query()->withoutGlobalScopes()
            ->where('project_id', $project->id)->pluck('duration_days', 'workflow_step_id');

        $phases = [];
        $cursor = 0;
        foreach ($steps as $step) {
            $override = $overrides[$step->id] ?? null;
            $days = max(self::MIN_PHASE_DAYS, (int) ($override ?? $step->duration_days));
            $phases[] = ['step_id' => (int) $step->id, 'title' => $step->title, 'days' => $days, 'start_idx' => $cursor, 'end_idx' => $cursor + $days - 1];
            $cursor += $days;
        }
        $total = $cursor;
        $byStep = [];
        foreach ($phases as $phase) {
            $byStep[$phase['step_id']] = $phase;
        }

        $milestones = [];
        $rows = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->orderBy('sort')->orderBy('id')->get();
        foreach ($rows as $row) {
            $idx = match ($row->anchor_type) {
                WorkflowMilestone::ANCHOR_WORKFLOW_START => 0,
                WorkflowMilestone::ANCHOR_WORKFLOW_END => $total > 0 ? $total - 1 : null,
                WorkflowMilestone::ANCHOR_STEP_START => isset($byStep[$row->anchor_workflow_step_id]) ? $byStep[$row->anchor_workflow_step_id]['start_idx'] : null,
                WorkflowMilestone::ANCHOR_STEP_END => isset($byStep[$row->anchor_workflow_step_id]) ? $byStep[$row->anchor_workflow_step_id]['end_idx'] : null,
                default => null,
            };
            $milestones[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'idx' => $idx === null ? null : $idx + (int) $row->offset_days,
                'fixed_date' => $row->anchor_type === WorkflowMilestone::ANCHOR_FIXED ? $row->fixed_date?->toDateString() : null,
                'is_market_launch' => (bool) $row->is_market_launch,
            ];
        }

        return ['total' => $total, 'phases' => $phases, 'milestones' => $milestones];
    }

    private function calendarFor(Project $project): WorkdayCalendar
    {
        return new WorkdayCalendar(
            Holiday::query()->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)->where('active', true)->pluck('date')
        );
    }

    /** Projekte tragen nicht für jeden Schritt eine Zeile; fehlende legt die Rechnung an. */
    private function ensureStepRows(Project $project): void
    {
        WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->get()
            ->each(fn (WorkflowStep $step) => ProjectWorkflowStep::query()->withoutGlobalScopes()->firstOrCreate(
                ['project_id' => $project->id, 'workflow_step_id' => $step->id],
                ['tenant_id' => $project->tenant_id, 'sort' => $step->sort]
            ));
    }

    /** Vorlagen-Meilensteine des aktuellen Workflows ans Projekt kopieren (ohne Doppelung), Reste anderer Workflows entfernen. */
    private function syncMilestones(Project $project): void
    {
        $templates = WorkflowMilestone::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->get();

        ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)
            ->whereNotNull('workflow_milestone_id')
            ->whereNotIn('workflow_milestone_id', $templates->pluck('id'))
            ->delete();

        $existing = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)
            ->whereNotNull('workflow_milestone_id')->pluck('workflow_milestone_id')->all();

        foreach ($templates as $template) {
            if (in_array($template->id, $existing, true)) {
                continue;
            }
            ProjectMilestone::query()->withoutGlobalScopes()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'workflow_milestone_id' => $template->id,
                'name' => $template->name,
                'sort' => $template->sort,
                'anchor_type' => $template->anchor_type,
                'anchor_workflow_step_id' => $template->anchor_workflow_step_id,
                'offset_days' => $template->offset_days,
                'is_market_launch' => $template->is_market_launch,
                'check_direction' => $template->check_direction,
            ]);
        }
    }

    private function writeDueDate(Project $project, int $stepId, CarbonInterface $date): void
    {
        DB::table('project_workflow_steps')->where('project_id', $project->id)->where('workflow_step_id', $stepId)
            ->update(['due_date' => $date->toDateString()]);
    }

    private function writeStatusStepDates(Project $project, ?CarbonInterface $end): void
    {
        $steps = WorkflowStep::query()->withoutGlobalScopes()
            ->where('workflow_id', $project->workflow_id)->whereIn('lifecycle_status', [1, 3])->get();
        foreach ($steps as $step) {
            $date = (int) $step->lifecycle_status === 1 ? $project->start_date : $end;
            if ($date !== null) {
                $this->writeDueDate($project, (int) $step->id, $date);
            }
        }
    }
}
