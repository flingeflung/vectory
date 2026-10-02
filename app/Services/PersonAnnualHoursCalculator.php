<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Person;
use App\Models\PersonVacationDays;
use App\Models\PersonWeeklyHours;
use App\Support\Workdays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Jahresstunden-Kapazität einer Person aus Wochenstunden- und Urlaubstage-
 * Historie, Beschäftigungszeitraum und Feiertagen (Ralf, 2026-09-28: beide
 * Historien können sich im Jahresverlauf ändern, daher wird abschnittsweise
 * gerechnet). Einzige Quelle für die Tabs "Stunden" und "Arbeitszeit".
 *
 * Arbeitstage = reine Wochentage (siehe Workdays); Feiertage mindern nur die
 * Brutto-Stunden, nicht die Arbeitstage-Spalte.
 */
class PersonAnnualHoursCalculator
{
    /**
     * Gibt null zurück, wenn die Person im Jahr keine gültigen Wochenstunden hat.
     *
     * @param  Collection<int, Holiday>  $activeHolidays  aktive Feiertage (Mo-Fr) des Jahres
     * @return array{employmentStart: CarbonImmutable, employmentEnd: CarbonImmutable, workdays: int, holidays: int, wost: float, vacationHours: float, brutto: float, netto: float}|null
     */
    public function forPerson(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, Collection $activeHolidays): ?array
    {
        $totalWorkdays = Workdays::count($yearStart, $yearEnd);
        [$employmentStart, $employmentEnd] = $this->employmentPeriod($person, $yearStart, $yearEnd);
        $employmentWorkdays = Workdays::count($employmentStart, $employmentEnd);
        $holidayCount = $employmentWorkdays > 0
            ? $this->countHolidays($activeHolidays, $employmentStart, $employmentEnd)
            : 0;

        if ($employmentWorkdays === 0) {
            $brutto = 0.0;
            $effectiveWoSt = 0.0;
        } else {
            $contractHours = $this->overlappingSum($person->weeklyHours, $employmentStart, $employmentEnd, fn (PersonWeeklyHours $row, int $segmentWorkdays) => $segmentWorkdays * ((float) $row->hours / 5));
            $brutto = $this->overlappingSum(
                $person->weeklyHours,
                $employmentStart,
                $employmentEnd,
                fn (PersonWeeklyHours $row, int $segmentWorkdays, CarbonImmutable $segmentStart, CarbonImmutable $segmentEnd) => ($segmentWorkdays - $this->countHolidays($activeHolidays, $segmentStart, $segmentEnd)) * ((float) $row->hours / 5)
            );
            if ($contractHours === null || $brutto === null) {
                return null;
            }
            $effectiveWoSt = $contractHours / $employmentWorkdays * 5;
        }

        // Ändert sich der Urlaubsanspruch innerhalb des Jahres, wird er anteilig
        // nach Arbeitstagen der jeweiligen Gültigkeit geblendet - dieselbe
        // Denkweise wie bei den Wochenstunden.
        $vacationDaysEffective = $employmentWorkdays > 0
            ? $this->overlappingSum($person->vacationDays, $employmentStart, $employmentEnd, fn (PersonVacationDays $row, int $segmentWorkdays) => $totalWorkdays > 0 ? (float) $row->days * $segmentWorkdays / $totalWorkdays : 0.0) ?? 0.0
            : 0.0;
        $vacationHours = $vacationDaysEffective * $effectiveWoSt / 5;

        return [
            'employmentStart' => $employmentStart,
            'employmentEnd' => $employmentEnd,
            'workdays' => $employmentWorkdays,
            'holidays' => $holidayCount,
            'wost' => $effectiveWoSt,
            'vacationHours' => $vacationHours,
            'brutto' => $brutto,
            'netto' => $brutto - $vacationHours,
        ];
    }

    /**
     * Beschäftigungszeitraum der Person, begrenzt auf das Jahr. Start nach Ende
     * bedeutet: im Jahr nicht beschäftigt.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function employmentPeriod(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): array
    {
        $start = $person->start_date
            ? CarbonImmutable::parse($person->start_date->toDateString())->max($yearStart)
            : $yearStart;
        $end = $person->end_date
            ? CarbonImmutable::parse($person->end_date->toDateString())->min($yearEnd)
            : $yearEnd;

        return [$start, $end];
    }

    /** Wochenstunden-Zeilen, die den Zeitraum berühren. */
    public function overlappingWeeklyHours(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): Collection
    {
        return $person->weeklyHours->filter(function (PersonWeeklyHours $row) use ($yearStart, $yearEnd) {
            $rowStart = $row->valid_from ? CarbonImmutable::parse($row->valid_from->toDateString()) : $yearStart;
            $rowEnd = $row->valid_to ? CarbonImmutable::parse($row->valid_to->toDateString()) : $yearEnd;

            return $rowStart->lessThanOrEqualTo($yearEnd) && $rowEnd->greaterThanOrEqualTo($yearStart);
        })->values();
    }

    /**
     * Summiert $valueFn über alle Historien-Zeilen, die den Zeitraum berühren -
     * je Zeile begrenzt auf den Überschneidungs-Zeitraum. Gibt null zurück, wenn
     * KEINE Zeile den Zeitraum berührt (dann hat die Person dafür keine gültigen
     * Daten).
     *
     * @param  Collection<int, PersonWeeklyHours|PersonVacationDays>  $history
     * @param  \Closure(PersonWeeklyHours|PersonVacationDays, int, CarbonImmutable, CarbonImmutable): float  $valueFn
     */
    private function overlappingSum($history, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, \Closure $valueFn): ?float
    {
        $touched = false;
        $sum = 0.0;
        foreach ($history as $row) {
            $rowStart = $row->valid_from ? CarbonImmutable::parse($row->valid_from->toDateString()) : $yearStart;
            $rowEnd = $row->valid_to ? CarbonImmutable::parse($row->valid_to->toDateString()) : $yearEnd;
            $segmentStart = $rowStart->greaterThan($yearStart) ? $rowStart : $yearStart;
            $segmentEnd = $rowEnd->lessThan($yearEnd) ? $rowEnd : $yearEnd;
            if ($segmentStart->greaterThan($segmentEnd)) {
                continue;
            }
            $touched = true;
            $sum += $valueFn($row, Workdays::count($segmentStart, $segmentEnd), $segmentStart, $segmentEnd);
        }

        return $touched ? $sum : null;
    }

    private function countHolidays(Collection $holidays, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $holidays
            ->filter(fn (Holiday $holiday) => $holiday->date->betweenIncluded($start, $end))
            ->unique(fn (Holiday $holiday) => $holiday->date->toDateString())
            ->count();
    }
}
