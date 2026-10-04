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
        if (! $project->start_date || ! $project->end_date || $project->start_date->gt($project->end_date)) {
            return null;
        }
        $start = CarbonImmutable::parse($project->start_date->toDateString());
        $end = CarbonImmutable::parse($project->end_date->toDateString());
        $holidays = $this->holidays((int) $project->tenant_id, $start->year, $end->year);
        $days = 0;
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $days++;
            }
        }
        $timeline = $this->stepTimeline()->forProject($project, $days);

        return $timeline !== null && $timeline['compressed']
            ? ['sum' => $timeline['sum'], 'available' => $timeline['available'], 'factor' => $timeline['factor']]
            : null;
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
}
