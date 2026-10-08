<?php

namespace App\Services;

use App\Models\CalendarEntry;
use App\Models\Holiday;
use App\Models\Person;
use App\Models\PlanningBaseLoad;
use App\Models\PlanningPersonBaseLoad;
use App\Models\ProjectPerson;
use App\Support\Workdays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProjectPlanningCalculator
{
    /** @var array<string, Collection> */
    private array $holidayCache = [];

    /** @var array<string, Collection> */
    private array $baseLoadCache = [];

    /** @var array<string, Collection> */
    private array $personBaseLoadCache = [];

    private ?ProjectStepTimeline $stepTimeline = null;

    public function prime(Collection $people, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $tenantIds = $people->pluck('tenant_id')->unique()->values();
        $personIds = $people->pluck('id')->values();
        $years = collect(range($start->year, $end->year));

        $baseLoads = PlanningBaseLoad::query()->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $tenantIds)->whereIn('year', $years)->get()->groupBy(fn ($row) => $row->tenant_id.'-'.$row->year);
        $personLoads = PlanningPersonBaseLoad::query()->withoutGlobalScope('tenant')
            ->whereIn('person_id', $personIds)->whereIn('year', $years)->get()->groupBy(fn ($row) => $row->person_id.'-'.$row->year);
        foreach ($people as $person) {
            foreach ($years as $year) {
                $this->baseLoadCache[$person->tenant_id.'-'.$year] = $baseLoads->get($person->tenant_id.'-'.$year, collect());
                $this->personBaseLoadCache[$person->id.'-'.$year] = $personLoads->get($person->id.'-'.$year, collect());
                $this->holidays($person->tenant_id, $year, $year);
            }
        }
    }

    /**
     * "holiday" = Stunden, die an einem Feiertag regulär anfielen (für die Darstellung; "work" ist dort 0).
     *
     * @return Collection<string, array{work: float, holiday: float, absence: float, base_load: float, available: float}>
     */
    public function capacityByDay(Person $person, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $person->loadMissing(['weeklyHours', 'calendarEntries']);
        $result = collect();

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $work = $this->workHoursForDay($person, $date);
            $holidayHours = $work > 0 ? 0.0 : $this->holidayHoursForDay($person, $date);
            $absence = $work > 0 && $person->calendarEntries->contains(fn (CalendarEntry $entry) => $entry->type === CalendarEntry::TYPE_ABSENCE
                && $entry->starts_on->toImmutable()->startOfDay()->lessThanOrEqualTo($date)
                && $entry->ends_on->toImmutable()->startOfDay()->greaterThanOrEqualTo($date)) ? $work : 0.0;
            $baseLoad = $work > 0 ? $this->baseLoadForDay($person, $date) : 0.0;
            $result->put($date->toDateString(), [
                'work' => $work,
                'holiday' => $holidayHours,
                'absence' => $absence,
                'base_load' => $baseLoad,
                'available' => max(0, $work - $absence - $baseLoad),
            ]);
        }

        return $result;
    }

    /** @return Collection<string, float> */
    public function plannedHoursByDay(ProjectPerson $assignment, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Collection
    {
        $project = $assignment->project;
        if ($assignment->planned_hours === null || ! $project?->start_date || ! $project?->end_date || $project->start_date->gt($project->end_date)) {
            return collect();
        }

        $projectStart = CarbonImmutable::parse($project->start_date->toDateString());
        $projectEnd = CarbonImmutable::parse($project->end_date->toDateString());
        $holidays = $this->holidays($assignment->person->tenant_id, $projectStart->year, $projectEnd->year);

        // Arbeitstage des Projekts (ohne Wochenenden und Feiertage), in Reihenfolge.
        $eligible = [];
        for ($date = $projectStart; $date->lessThanOrEqualTo($projectEnd); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $eligible[] = $date->toDateString();
            }
        }
        $eligibleDays = count($eligible);
        if ($eligibleDays === 0) {
            return collect();
        }

        // Gewicht je Arbeitstag: ohne Einsatzplan gleichmäßig über das ganze Projekt, mit Einsatzplan nur im Zeitraum
        // der Funktionsgruppe (Tage am Rand zählen anteilig).
        $weights = array_fill(0, $eligibleDays, 1.0);
        $timeline = $this->stepTimeline()->forProject($project, $eligibleDays);
        if ($timeline !== null) {
            [$from, $to] = $this->stepTimeline()->windowFor($timeline, $project, $assignment->function_group_id, $eligibleDays);
            foreach ($weights as $index => $_) {
                $weights[$index] = max(0.0, min($index + 1, $to) - max($index, $from));
            }
        }
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            return collect();
        }

        $result = collect();
        foreach ($eligible as $index => $day) {
            $date = CarbonImmutable::parse($day);
            if ($weights[$index] > 0 && $date->greaterThanOrEqualTo($rangeStart) && $date->lessThanOrEqualTo($rangeEnd)) {
                $result->put($day, (float) $assignment->planned_hours * $weights[$index] / $totalWeight);
            }
        }

        return $result;
    }

    /**
     * Hinweis für die Projektansicht: Haben die Standarddauern der Schritte nicht in den Projektzeitraum gepasst,
     * wurden sie verdichtet (Ralf, 2026-10-04).
     *
     * @return array{sum: float, available: int, factor: float}|null null = nicht verdichtet bzw. keine Zeitleiste
     */
    public function compressionNotice(\App\Models\Project $project): ?array
    {
        $days = $this->projectWorkdays($project);
        if ($days === null) {
            return null;
        }
        $timeline = $this->stepTimeline()->forProject($project, $days);

        return $timeline !== null && $timeline['compressed']
            ? ['sum' => $timeline['sum'], 'available' => $timeline['available'], 'factor' => $timeline['factor']]
            : null;
    }

    /** Arbeitstage zwischen Projektstart und -ende (ohne Wochenenden und Feiertage der Organisation); null bei unvollständigem Zeitraum. */
    public function projectWorkdays(\App\Models\Project $project): ?int
    {
        $list = $this->projectWorkdayList($project);

        return $list === null ? null : count($list);
    }

    /** @return list<string>|null Arbeitstage (Y-m-d) von Projektstart bis -ende in Reihenfolge; null bei unvollständigem Zeitraum */
    public function projectWorkdayList(\App\Models\Project $project): ?array
    {
        if (! $project->start_date || ! $project->end_date || $project->start_date->gt($project->end_date)) {
            return null;
        }
        $start = CarbonImmutable::parse($project->start_date->toDateString());
        $end = CarbonImmutable::parse($project->end_date->toDateString());
        $holidays = $this->holidays((int) $project->tenant_id, $start->year, $end->year);
        $days = [];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $days[] = $date->toDateString();
            }
        }

        return $days;
    }

    /**
     * Daten für das Zeitraum-Diagramm in Planung > Planstunden (Ralf, 2026-10-06): die Arbeitstage des Projektzeitraums und die Schritte
     * "In Bearbeitung" mit ihren Dauern in AT. Entspricht der Bedarf dem Zeitraum, gelten die eingetragenen Dauern; sonst die auf den
     * Zeitraum umgerechneten (wie beim Knopf "Dauern an Projektzeitraum anpassen"), damit die Balken die Breite genau füllen.
     * Schritte ohne eingetragene Dauer zählen 1 Tag.
     *
     * @return array{pre: int, milestones: list<array{step_id: int, title: string, date: string}>, groups: list<array{id: int, name: string, from: int, to: int}>, workdays: list<string>, calendar: list<string>, holidays: object, steps: list<array{id: int, title: string, days: int, workflow_days: int, fixed: bool, locked: bool, scaled_days: int}>, sum: int, available: int, period: array<string, mixed>}|null
     */
    public function periodChart(\App\Models\Project $project): ?array
    {
        $workdays = $this->projectWorkdayList($project);
        $breakdown = $this->stepTimeline()->breakdown($project);
        if ($workdays === null || $workdays === [] || $breakdown === null) {
            return null;
        }

        $available = count($workdays);
        $need = (int) $breakdown['sum'];
        $scaled = $need === $available ? [] : ($this->stepTimeline()->scaledDurations($project, $available) ?? []);
        // Termin-Zeilen der Schritte des aktuellen Workflows (Phasenende-Name und Termin)
        $stepRows = \App\Models\ProjectWorkflowStep::query()->withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->whereHas('workflowStep', fn ($query) => $query->withoutGlobalScopes()->where('workflow_id', $project->workflow_id))
            ->with(['workflowStep' => fn ($query) => $query->withoutGlobalScopes()])->get()->keyBy('workflow_step_id');
        $steps = [];
        foreach ($breakdown['steps'] as $row) {
            $steps[] = [
                'id' => $row['id'], 'title' => $row['title'], 'days' => $row['days'], 'workflow_days' => $row['days'], 'fixed' => $row['default_used'],
                'locked' => $row['locked'], 'scaled_days' => $scaled[$row['id']] ?? $row['days'],
                'end_name' => (string) ($stepRows->get($row['id'])?->effectiveMilestoneTitle() ?? ''),
            ];
        }

        // Kalender von Projektstart bis -ende mit allen Tagen (auch Wochenenden, Feiertage) für das Raster im Hintergrund.
        // Braucht der Workflow mehr Arbeitstage als der Zeitraum hat, wird Arbeitstag für Arbeitstag über das Projektende hinaus verlängert.
        $start = CarbonImmutable::parse($project->start_date->toDateString());
        $end = CarbonImmutable::parse($project->end_date->toDateString());
        $holidays = $this->holidays((int) $project->tenant_id, $start->year - 1, $end->year + 3);
        $isFree = fn (CarbonImmutable $date) => $date->isWeekend() || $holidays->contains($date->toDateString());
        $calendar = [];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $calendar[] = $date->toDateString();
        }
        $chartEnd = $end;
        for ($missing = $need - $available; $missing > 0; $chartEnd = $chartEnd->addDay()) {
            $next = $chartEnd->addDay();
            $calendar[] = $next->toDateString();
            if (! $isFree($next)) {
                $workdays[] = $next->toDateString();
                $missing--;
            }
        }

        // Vorschläge für "Zeitraum anpassen": neues Ende bei festem Start, neuer Start bei festem Ende
        $newEnd = $need > 0 ? ($workdays[$need - 1] ?? null) : null;
        $newStart = null;
        $prependWorkdays = [];
        if ($need > 0 && $need <= $available) {
            $newStart = $workdays[$available - $need];
        } elseif ($need > $available) {
            $cursor = CarbonImmutable::parse($workdays[0]);
            for ($back = $need - $available; $back > 0;) {
                $cursor = $cursor->subDay();
                if (! $isFree($cursor)) {
                    $back--;
                }
            }
            $newStart = $cursor->toDateString();
        }

        // Ist der Zeitraum zu knapp und läge der frühere Start nicht in der Vergangenheit, wird die Achse auch nach links verlängert,
        // damit die Vorschau "Start früher" die Balken dorthin schieben kann (Ralf, 2026-10-07)
        $pre = 0;
        if ($need > $available && $newStart !== null && $newStart >= CarbonImmutable::today()->toDateString()) {
            $prependCalendar = [];
            for ($date = CarbonImmutable::parse($newStart); $date->lessThan($start); $date = $date->addDay()) {
                $prependCalendar[] = $date->toDateString();
                if (! $isFree($date)) {
                    $prependWorkdays[] = $date->toDateString();
                    $pre++;
                }
            }
            $workdays = [...array_slice($prependWorkdays, 0), ...$workdays];
            $calendar = [...$prependCalendar, ...$calendar];
        }
        $period = [
            'mode' => $need === $available ? 'match' : ($need < $available ? 'buffer' : 'overflow'),
            'need' => $need,
            'available' => $available,
            'diff' => abs($need - $available),
            'project_start' => $start->toDateString(),
            'project_end' => $end->toDateString(),
            'new_end' => $need === $available ? null : $newEnd,
            'new_start' => $need === $available ? null : $newStart,
        ];

        $holidayNames = Holiday::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $project->tenant_id)->where('active', true)
            ->whereBetween('date', [$calendar[0], $chartEnd->toDateString()])
            ->get(['date', 'name'])
            ->mapWithKeys(fn (Holiday $holiday) => [$holiday->date->toDateString() => (string) $holiday->name])
            ->all();

        // Einsatzplan des Workflows: je zuständiger Funktionsgruppe von/bis als Position in $steps (0-basiert); ohne Eintrag die ganze Breite
        $workSteps = \App\Models\WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $project->workflow_id)
            ->where('lifecycle_status', \App\Models\WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->orderBy('sort')->with('functionGroups')->get();
        $position = $workSteps->pluck('id')->flip();
        $windows = \App\Models\WorkflowGroupWindow::query()->withoutGlobalScope('tenant')->where('workflow_id', $project->workflow_id)->get()->keyBy('function_group_id');
        $last = max(0, $workSteps->count() - 1);
        $groups = $workSteps->flatMap->functionGroups->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ->map(function ($group) use ($windows, $position, $last) {
                $window = $windows->get($group->id);
                $from = $window?->from_step_id !== null && $position->has($window->from_step_id) ? $position[$window->from_step_id] : 0;
                $to = $window?->to_step_id !== null && $position->has($window->to_step_id) ? $position[$window->to_step_id] : $last;

                return ['id' => (int) $group->id, 'name' => (string) $group->name, 'from' => min($from, $to), 'to' => max($from, $to)];
            })->all();

        // Marken im Diagramm: Altmodell = Schritte mit eingetragenem Termin; neues Modell (schedule_model 2) = benannte Phasenenden
        // und die Meilensteine des Projekts (docs/ablaufplan-konzept.md)
        $isNewModel = (int) $project->schedule_model === 2;
        $milestones = $stepRows->filter(fn (\App\Models\ProjectWorkflowStep $row) => $row->due_date !== null
                && (! $isNewModel || trim((string) $row->effectiveMilestoneTitle()) !== ''))
            ->map(fn (\App\Models\ProjectWorkflowStep $row) => [
                'key' => 'step-'.$row->workflow_step_id,
                'kind' => 'phase_end',
                'step_id' => (int) $row->workflow_step_id,
                'title' => (string) ($row->effectiveMilestoneTitle() ?: $row->workflowStep->title),
                'date' => $row->due_date->toDateString(),
                'rule' => null,
            ])->values()->all();
        if ($isNewModel) {
            $stepTitles = $stepRows->mapWithKeys(fn ($row) => [$row->workflow_step_id => $row->workflowStep->title]);
            $own = \App\Models\ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->orderBy('sort')->get();
            foreach ($own as $row) {
                $milestones[] = [
                    'key' => 'ms-'.$row->id,
                    'kind' => 'milestone',
                    'step_id' => null,
                    'title' => (string) $row->name,
                    'date' => $row->date->toDateString(),
                    'rule' => $this->milestoneRule($row, $stepTitles->all()),
                    'id' => (int) $row->id,
                    'anchor_type' => $row->anchor_type,
                    'anchor_step_id' => $row->anchor_workflow_step_id ? (int) $row->anchor_workflow_step_id : null,
                    'offset_days' => (int) $row->offset_days,
                    'fixed_date' => $row->fixed_date?->toDateString(),
                ];
            }
        }
        usort($milestones, fn (array $x, array $y) => [$x['date'], $x['kind'] === 'milestone'] <=> [$y['date'], $y['kind'] === 'milestone']);

        return [
            'milestones' => $milestones,
            'groups' => $groups,
            'workdays' => $workdays,
            'calendar' => $calendar,
            'holidays' => (object) $holidayNames,
            'steps' => $steps,
            'sum' => $need,
            'available' => $available,
            'period' => $period,
            'pre' => $pre,
        ];
    }

    /**
     * Zeitbedarf laut Workflow gegenüber dem Projektzeitraum, für die Infozeile oben in Planung > Planstunden (Ralf, 2026-10-06).
     *
     * @return array{state: string, sum?: int, available?: ?int, factor?: float, steps?: list<array{title: string, days: int, default_used: bool}>}
     *                                                                                                                                           state: no_workflow | ok | over
     */
    public function timeNeed(\App\Models\Project $project): array
    {
        $breakdown = $this->stepTimeline()->breakdown($project);
        if ($breakdown === null) {
            return ['state' => 'no_workflow'];
        }
        $available = $this->projectWorkdays($project);

        return [
            'state' => $available !== null && $breakdown['sum'] > $available ? 'over' : 'ok',
            'sum' => $breakdown['sum'],
            'available' => $available,
            'factor' => $available !== null && $breakdown['sum'] > 0 ? round($available / $breakdown['sum'], 2) : null,
            'steps' => $breakdown['steps'],
            'has_overrides' => \App\Models\ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
                ->where('project_id', $project->id)->whereNotNull('duration_days')->exists(),
        ];
    }

    /**
     * Berechnetes Ende jedes Arbeitsschritts (Ralf, 2026-10-06): ab dem Projektstart werden die Dauern der Schritte "In Bearbeitung"
     * der Reihe nach auf Arbeitstage gelegt (ohne Wochenenden und Feiertage der Organisation). Der erste Schritt beginnt am Starttag
     * (fällt er auf einen freien Tag, am nächsten Arbeitstag), jeder weitere am Arbeitstag nach dem Ende des vorigen.
     *
     * @return array<int, CarbonImmutable> Schritt-ID => Enddatum (leer ohne Projektstart oder Workflow)
     */
    public function stepEndDates(\App\Models\Project $project): array
    {
        $days = $this->stepTimeline()->stepDays($project);
        if ($days === null || ! $project->start_date) {
            return [];
        }

        $start = CarbonImmutable::parse($project->start_date->toDateString());
        $holidays = $this->holidays((int) $project->tenant_id, $start->year, $start->year + 3);
        $isFree = fn (CarbonImmutable $date) => $date->isWeekend() || $holidays->contains($date->toDateString());
        $nextWorkday = function (CarbonImmutable $date) use ($isFree) {
            while ($isFree($date)) {
                $date = $date->addDay();
            }

            return $date;
        };

        $result = [];
        $cursor = $nextWorkday($start);
        foreach ($days as $stepId => $count) {
            $end = $cursor;
            for ($i = 1; $i < $count; $i++) {
                $end = $nextWorkday($end->addDay());
            }
            $result[$stepId] = $end;
            $cursor = $nextWorkday($end->addDay());
        }

        return $result;
    }

    /** @return array<int, int>|null Schritt-ID => neue Dauer, auf den Projektzeitraum umgerechnet */
    public function scaledDurations(\App\Models\Project $project): ?array
    {
        $available = $this->projectWorkdays($project);

        return $available === null ? null : $this->stepTimeline()->scaledDurations($project, $available);
    }

    private function stepTimeline(): ProjectStepTimeline
    {
        return $this->stepTimeline ??= new ProjectStepTimeline;
    }

    private function workHoursForDay(Person $person, CarbonImmutable $date): float
    {
        return $this->holidays($person->tenant_id, $date->year, $date->year)->contains($date->toDateString())
            ? 0.0
            : $this->regularHoursForDay($person, $date);
    }

    /** Stunden, die an einem Feiertag regulär anfielen (Wochentag, im Beschäftigungszeitraum), sonst 0. */
    private function holidayHoursForDay(Person $person, CarbonImmutable $date): float
    {
        return $this->holidays($person->tenant_id, $date->year, $date->year)->contains($date->toDateString())
            ? $this->regularHoursForDay($person, $date)
            : 0.0;
    }

    /** Reguläre Tagesstunden ohne Rücksicht auf Feiertage: Wochenende 0, außerhalb der Beschäftigung 0. */
    private function regularHoursForDay(Person $person, CarbonImmutable $date): float
    {
        if ($date->isWeekend()
            || ($person->start_date && $date->lt(CarbonImmutable::parse($person->start_date->toDateString())))
            || ($person->end_date && $date->gt(CarbonImmutable::parse($person->end_date->toDateString())))) {
            return 0.0;
        }

        $row = $person->weeklyHours->first(fn ($hours) => ($hours->valid_from === null || $hours->valid_from->toDateString() <= $date->toDateString())
            && ($hours->valid_to === null || $hours->valid_to->toDateString() >= $date->toDateString()));

        return $row ? (float) $row->hours / 5 : 0.0;
    }

    private function baseLoadForDay(Person $person, CarbonImmutable $date): float
    {
        $key = $person->id.'-'.$date->year;
        $personLoads = $this->personBaseLoadCache[$key] ??= PlanningPersonBaseLoad::query()
            ->withoutGlobalScope('tenant')->where('tenant_id', $person->tenant_id)
            ->where('person_id', $person->id)->where('year', $date->year)->get();
        $loads = $personLoads->isNotEmpty()
            ? $personLoads
            : ($this->baseLoadCache[$person->tenant_id.'-'.$date->year] ??= PlanningBaseLoad::query()
                ->withoutGlobalScope('tenant')->where('tenant_id', $person->tenant_id)->where('year', $date->year)->get());
        $yearStart = CarbonImmutable::create($date->year, 1, 1);
        $yearEnd = CarbonImmutable::create($date->year, 12, 31);
        $yearWorkdays = Workdays::count($yearStart, $yearEnd);

        return (float) $loads->filter(fn ($load) => CarbonImmutable::parse($load->valid_from)->lessThanOrEqualTo($date)
            && CarbonImmutable::parse($load->valid_to)->greaterThanOrEqualTo($date))
            ->sum(fn ($load) => ((float) $load->value * ($load->calculation_type === 'weekly' ? PlanningBaseLoadCalculator::STANDARD_WEEKS_PER_YEAR : 1)) / max(1, $yearWorkdays));
    }

    private function holidays(int $tenantId, int $fromYear, int $toYear): Collection
    {
        $dates = collect();
        foreach (range($fromYear, $toYear) as $year) {
            $key = $tenantId.'-'.$year;
            $dates = $dates->merge($this->holidayCache[$key] ??= Holiday::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)->where('active', true)
                ->whereYear('date', $year)
                ->pluck('date')->map(fn ($date) => CarbonImmutable::parse($date)->toDateString()));
        }

        return $dates;
    }

    /** @param  array<int, string>  $stepTitles */
    private function milestoneRule(\App\Models\ProjectMilestone $milestone, array $stepTitles): string
    {
        $offset = (int) $milestone->offset_days;
        $suffix = $offset === 0 ? '' : ' '.($offset > 0 ? '+' : '−').abs($offset).' '.__('AT');
        $step = $stepTitles[$milestone->anchor_workflow_step_id] ?? '';

        return match ($milestone->anchor_type) {
            'workflow_start' => __('Workflow-Start').$suffix,
            'workflow_end' => __('Workflow-Ende').$suffix,
            'step_start' => __('Start von :step', ['step' => $step]).$suffix,
            'step_end' => __('Ende von :step', ['step' => $step]).$suffix,
            default => __('festes Datum'),
        };
    }
}
