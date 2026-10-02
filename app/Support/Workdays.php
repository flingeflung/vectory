<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Gemeinsame Zählung reiner Wochentage (Mo-Fr), ohne Feiertage oder
 * Krankheitstage abzuziehen (Ralf, 2026-09-28, bewusste Vereinfachung). Eine
 * einzige Stelle für alle Planungsrechnungen, damit sie nicht auseinanderlaufen.
 */
final class Workdays
{
    public static function count(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($start->greaterThan($end)) {
            return 0;
        }

        $days = $start->diffInDays($end) + 1;
        $fullWeeks = intdiv($days, 7);
        $count = $fullWeeks * 5;
        $cursor = $start->addWeeks($fullWeeks);
        for ($i = 0; $i < $days % 7; $i++) {
            if ($cursor->isWeekday()) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
