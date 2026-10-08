<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Arbeitstage einer Organisation: Montag bis Freitag ohne aktive Feiertage (Ralf, 2026-10-04: Feiertage fließen in
 * jede Datumsrechnung ein). Gemeinsame Grundlage von ProjectScheduler und WorkflowScheduleCalculator.
 */
class WorkdayCalendar
{
    /** @var array<string, true> Feiertage als Y-m-d-Schlüssel */
    private array $holidays = [];

    /** @param  iterable<mixed>  $holidays  Feiertage (Y-m-d oder Datumsobjekte) */
    public function __construct(iterable $holidays = [])
    {
        foreach ($holidays as $date) {
            $this->holidays[CarbonImmutable::parse($date)->toDateString()] = true;
        }
    }

    public function isWorkday(CarbonInterface $date): bool
    {
        return $date->isWeekday() && ! isset($this->holidays[$date->toDateString()]);
    }

    /** Das Datum selbst, falls Arbeitstag, sonst der nächste Arbeitstag danach. */
    public function onOrAfter(CarbonInterface $date): CarbonImmutable
    {
        $cursor = CarbonImmutable::parse($date->toDateString());
        while (! $this->isWorkday($cursor)) {
            $cursor = $cursor->addDay();
        }

        return $cursor;
    }

    /** Das Datum selbst, falls Arbeitstag, sonst der letzte Arbeitstag davor. */
    public function onOrBefore(CarbonInterface $date): CarbonImmutable
    {
        $cursor = CarbonImmutable::parse($date->toDateString());
        while (! $this->isWorkday($cursor)) {
            $cursor = $cursor->subDay();
        }

        return $cursor;
    }

    /** Verschiebt ein Datum um n Arbeitstage (negativ = zurück). Ausgangspunkt ist ein Arbeitstag. */
    public function shift(CarbonInterface $date, int $days): CarbonImmutable
    {
        $cursor = CarbonImmutable::parse($date->toDateString());
        $direction = $days >= 0 ? 1 : -1;
        $remaining = abs($days);
        while ($remaining > 0) {
            $cursor = $cursor->addDays($direction);
            if ($this->isWorkday($cursor)) {
                $remaining--;
            }
        }

        return $cursor;
    }
}
