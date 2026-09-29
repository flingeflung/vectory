<?php

namespace App\Services;

use App\Models\PlanningBaseLoad;
use Carbon\CarbonImmutable;

class PlanningBaseLoadCalculator
{
    public const STANDARD_WEEKS_PER_YEAR = 52;

    /**
     * @param  iterable<PlanningBaseLoad>  $baseLoads
     * @return array{yearly: float, weekly: float}
     */
    public function totals(iterable $baseLoads, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $yearWorkdays = $this->countWeekdays($yearStart, $yearEnd);
        $yearly = 0.0;

        foreach ($baseLoads as $baseLoad) {
            $validFrom = CarbonImmutable::parse($baseLoad->valid_from)->max($yearStart);
            $validTo = CarbonImmutable::parse($baseLoad->valid_to)->min($yearEnd);
            if ($validFrom->greaterThan($validTo) || $yearWorkdays === 0) {
                continue;
            }

            $validityShare = $this->countWeekdays($validFrom, $validTo) / $yearWorkdays;
            $annualValue = (float) $baseLoad->value
                * ($baseLoad->calculation_type === 'weekly' ? self::STANDARD_WEEKS_PER_YEAR : 1);
            $yearly += $annualValue * $validityShare;
        }

        return [
            'yearly' => $yearly,
            'weekly' => $yearly / self::STANDARD_WEEKS_PER_YEAR,
        ];
    }

    private function countWeekdays(CarbonImmutable $start, CarbonImmutable $end): int
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
