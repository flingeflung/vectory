<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Services\ProjectPlanningCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Auslastung der Beteiligten eines Projekts (Ralf, 2026-10-04): derselbe Algorithmus wie in der Planungsseite,
 * aber NUR mit den Stunden dieses Projekts - bei einem Hauptprojekt zusammen mit allen Unterprojekten,
 * kumuliert. Wer mehrere Projekte zusammen sehen will, geht in die Planung.
 *
 * Je Person und Zeitabschnitt (Tag in der Monatsansicht, Kalenderwoche in der Jahresansicht):
 * - work/holiday/absence/base_load/available/project/remaining wie in der Planungstabelle
 * - Diagrammwerte: Arbeitszeit (work - absence), Abwesenheit, Feiertag; Grundlast und Projektstunden, wobei der
 *   Teil der Projektstunden über der verfügbaren Zeit als Überbuchung gilt (project_over).
 */
final class ProjectUtilization
{
    /**
     * @return array{mode: string, year: int, month: int, periods: Collection<int, array{key: string, label: string, sub: string}>, people: Collection<int, array{person: Person, rows: array<string, array<string, float>>, chart: array<string, mixed>}>, allPeople: list<array{id: int, name: string}>, members: Collection<int, Project>}
     */
    public static function for(Project $project, ProjectPlanningCalculator $calculator, string $mode, int $year, int $month, ?int $personId = null): array
    {
        $mode = $mode === 'year' ? 'year' : 'month';
        $members = collect([$project]);
        if ($project->verbund_rolle === 1) {
            $members = $members->concat($project->unterprojekte()->withoutGlobalScope('tenant')->orderBy('source_pn')->get())->unique('id')->values();
        }

        $assignments = ProjectPerson::query()->withoutGlobalScope('tenant')
            ->whereIn('project_id', $members->pluck('id'))
            ->with(['project', 'person.weeklyHours', 'person.calendarEntries'])
            ->get()->filter(fn (ProjectPerson $row) => $row->person !== null);
        $people = $assignments->pluck('person')->unique('id')->sortBy(fn (Person $p) => mb_strtolower($p->last_name.' '.$p->first_name))->values();
        $allPeople = $people->map(fn (Person $p) => ['id' => $p->id, 'name' => trim($p->fullName().($p->short_name ? ' ('.$p->short_name.')' : ''))])->values()->all();
        if ($personId !== null) {
            $people = $people->where('id', $personId)->values();
        }

        [$periods, $rangeStart, $rangeEnd] = $mode === 'month'
            ? self::monthPeriods($year, $month)
            : self::yearPeriods($year);

        $holidayNames = Holiday::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $project->tenant_id)->where('active', true)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get()->mapWithKeys(fn (Holiday $holiday) => [$holiday->date->toDateString() => $holiday->name]);

        $calculator->prime($people, $rangeStart, $rangeEnd);
        $result = $people->map(function (Person $person) use ($assignments, $calculator, $periods, $rangeStart, $rangeEnd, $mode, $holidayNames) {
            $capacity = $calculator->capacityByDay($person, $rangeStart, $rangeEnd);
            $planned = [];
            foreach ($assignments->where('person_id', $person->id) as $assignment) {
                foreach ($calculator->plannedHoursByDay($assignment, $rangeStart, $rangeEnd) as $date => $hours) {
                    $planned[$date] = ($planned[$date] ?? 0.0) + $hours;
                }
            }

            $rows = [];
            foreach ($periods as $period) {
                $sum = array_fill_keys(['work', 'holiday', 'absence', 'base_load', 'available', 'project', 'project_in', 'project_over'], 0.0);
                foreach ($period['days'] as $day) {
                    $cap = $capacity->get($day, ['work' => 0, 'holiday' => 0, 'absence' => 0, 'base_load' => 0, 'available' => 0]);
                    $project = (float) ($planned[$day] ?? 0);
                    $inside = min($project, (float) $cap['available']);
                    $sum['work'] += (float) $cap['work'];
                    $sum['holiday'] += (float) ($cap['holiday'] ?? 0);
                    $sum['absence'] += (float) $cap['absence'];
                    $sum['base_load'] += (float) $cap['base_load'];
                    $sum['available'] += (float) $cap['available'];
                    $sum['project'] += $project;
                    $sum['project_in'] += $inside;
                    $sum['project_over'] += $project - $inside;
                }
                $sum['remaining'] = $sum['available'] - $sum['project'];
                $sum['utilization'] = $sum['available'] > 0 ? $sum['project'] / $sum['available'] * 100 : 0.0;
                $rows[$period['key']] = $sum;
            }

            return [
                'person' => $person,
                'rows' => $rows,
                'chart' => self::chartData($periods, $rows, $mode, $holidayNames),
            ];
        });

        return [
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
            'periods' => $periods->map(fn (array $p) => [
                'key' => $p['key'],
                'label' => $p['label'],
                'sub' => $p['sub'],
                'weekend' => $mode === 'month' && CarbonImmutable::parse($p['days'][0])->isWeekend(),
                'holiday' => $mode === 'month' ? ($holidayNames[$p['days'][0]] ?? null) : null,
                'today' => in_array(CarbonImmutable::today()->toDateString(), $p['days'], true),
            ]),
            'people' => $result,
            'allPeople' => $allPeople,
            'members' => $members,
        ];
    }

    /** @return array{0: Collection<int, array{key: string, label: string, sub: string, days: list<string>}>, 1: CarbonImmutable, 2: CarbonImmutable} */
    private static function monthPeriods(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periods = collect(range(0, $start->daysInMonth - 1))->map(function (int $offset) use ($start) {
            $day = $start->addDays($offset);

            return ['key' => $day->toDateString(), 'label' => (string) $day->day, 'sub' => $day->translatedFormat('D'), 'days' => [$day->toDateString()]];
        });

        return [$periods, $start, $start->endOfMonth()->startOfDay()];
    }

    /** @return array{0: Collection<int, array{key: string, label: string, sub: string, days: list<string>}>, 1: CarbonImmutable, 2: CarbonImmutable} */
    private static function yearPeriods(int $year): array
    {
        $yearStart = CarbonImmutable::now()->setISODate($year, 1)->startOfWeek();
        $yearEnd = CarbonImmutable::now()->setISODate($year + 1, 1)->startOfWeek();
        $periods = collect();
        for ($weekStart = $yearStart; $weekStart->lessThan($yearEnd); $weekStart = $weekStart->addWeek()) {
            $periods->push([
                'key' => sprintf('%04d-W%02d', $weekStart->isoWeekYear(), $weekStart->isoWeek()),
                'label' => (string) $weekStart->isoWeek(),
                'sub' => $weekStart->format('d.m.'),
                'days' => collect(range(0, 6))->map(fn (int $i) => $weekStart->addDays($i)->toDateString())->all(),
            ]);
        }

        return [$periods, $yearStart, $yearEnd->subDay()];
    }

    /**
     * Diagramm: Säulen für die Belegung (Grundlast, Projektstunden, darin die Überbuchung), dazu eine Linie für die
     * verfügbare Arbeitszeit (Arbeitszeit minus Abwesenheit) - wie im Reiter "Arbeitszeit". Wochenenden, Feiertage und
     * Abwesenheit (nur Monatsansicht) kommen als Kennzeichen je Abschnitt und werden als Hintergrundstreifen gemalt.
     *
     * @param  Collection<string, string>  $holidayNames
     * @return array<string, mixed>
     */
    private static function chartData(Collection $periods, array $rows, string $mode, Collection $holidayNames): array
    {
        $series = fn (callable $value) => $periods->map(fn (array $p) => round($value($rows[$p['key']]), 2))->values()->all();

        return [
            'labels' => $periods->map(fn (array $p) => $p['label'])->values()->all(),
            'subs' => $periods->map(fn (array $p) => $p['sub'])->values()->all(),
            'capacity' => $series(fn ($r) => max(0, $r['work'] - $r['absence'])),
            'base_load' => $series(fn ($r) => $r['base_load']),
            'project_in' => $series(fn ($r) => $r['project_in']),
            'project_over' => $series(fn ($r) => $r['project_over']),
            'today' => $periods->search(fn (array $p) => in_array(CarbonImmutable::today()->toDateString(), $p['days'], true)),
            'flags' => $periods->map(function (array $p) use ($rows, $mode, $holidayNames) {
                if ($mode !== 'month') {
                    return '';
                }
                $day = $p['days'][0];
                if ($holidayNames->has($day)) {
                    return 'holiday';
                }
                if (CarbonImmutable::parse($day)->isWeekend()) {
                    return 'weekend';
                }

                return $rows[$p['key']]['absence'] > 0 ? 'absence' : '';
            })->values()->all(),
        ];
    }
}
