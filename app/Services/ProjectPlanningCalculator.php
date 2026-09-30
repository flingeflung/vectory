<?php

namespace App\Services;

use App\Models\CalendarEntry;
use App\Models\Holiday;
use App\Models\Person;
use App\Models\PlanningBaseLoad;
use App\Models\PlanningPersonBaseLoad;
use App\Models\ProjectPerson;
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
     * @return Collection<string, array{work: float, absence: float, base_load: float, available: float}>
     */
    public function capacityByDay(Person $person, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $person->loadMissing(['weeklyHours', 'calendarEntries']);
        $result = collect();

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $work = $this->workHoursForDay($person, $date);
            $absence = $work > 0 && $person->calendarEntries->contains(fn (CalendarEntry $entry) => $entry->type === CalendarEntry::TYPE_ABSENCE
                && $entry->starts_on->toImmutable()->startOfDay()->lessThanOrEqualTo($date)
                && $entry->ends_on->toImmutable()->startOfDay()->greaterThanOrEqualTo($date)) ? $work : 0.0;
            $baseLoad = $work > 0 ? $this->baseLoadForDay($person, $date) : 0.0;
            $result->put($date->toDateString(), [
                'work' => $work,
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
        $eligibleDays = 0;
        for ($date = $projectStart; $date->lessThanOrEqualTo($projectEnd); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $eligibleDays++;
            }
        }
        if ($eligibleDays === 0) {
            return collect();
        }

        $daily = (float) $assignment->planned_hours / $eligibleDays;
        $result = collect();
        $visibleStart = $projectStart->max($rangeStart);
        $visibleEnd = $projectEnd->min($rangeEnd);
        for ($date = $visibleStart; $date->lessThanOrEqualTo($visibleEnd); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $result->put($date->toDateString(), $daily);
            }
        }

        return $result;
    }

    private function workHoursForDay(Person $person, CarbonImmutable $date): float
    {
        if ($date->isWeekend()
            || ($person->start_date && $date->lt(CarbonImmutable::parse($person->start_date->toDateString())))
            || ($person->end_date && $date->gt(CarbonImmutable::parse($person->end_date->toDateString())))
            || $this->holidays($person->tenant_id, $date->year, $date->year)->contains($date->toDateString())) {
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
        $yearWorkdays = $this->weekdays($yearStart, $yearEnd);

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

    private function weekdays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $count = 0;
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $count += $date->isWeekday() ? 1 : 0;
        }

        return $count;
    }
}
