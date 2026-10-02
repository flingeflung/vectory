<?php

namespace App\Services;

use App\Models\PlanningBaseLoad;
use App\Models\PlanningPersonBaseLoad;
use App\Support\Workdays;
use Carbon\CarbonImmutable;

class PlanningBaseLoadCalculator
{
    public const STANDARD_WEEKS_PER_YEAR = 52;

    /**
     * Individuelle Datensätze ersetzen für Person und Jahr die Basis vollständig.
     *
     * @param  iterable<PlanningBaseLoad>  $baseLoads
     * @param  iterable<PlanningPersonBaseLoad>  $personBaseLoads
     * @return array{yearly: float, weekly: float}
     */
    public function totalsForPerson(
        iterable $baseLoads,
        iterable $personBaseLoads,
        int $year,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableTo = null
    ): array {
        $personBaseLoads = collect($personBaseLoads);

        return $this->totals(
            $personBaseLoads->isNotEmpty() ? $personBaseLoads : $baseLoads,
            $year,
            $availableFrom,
            $availableTo
        );
    }

    /**
     * @param  iterable<PlanningBaseLoad>  $baseLoads
     * @return array{yearly: float, weekly: float}
     */
    public function totals(
        iterable $baseLoads,
        int $year,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableTo = null
    ): array {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $yearWorkdays = Workdays::count($yearStart, $yearEnd);
        $periodStart = $availableFrom?->max($yearStart) ?? $yearStart;
        $periodEnd = $availableTo?->min($yearEnd) ?? $yearEnd;
        $yearly = 0.0;

        if ($periodStart->greaterThan($periodEnd)) {
            return ['yearly' => 0.0, 'weekly' => 0.0];
        }

        foreach ($baseLoads as $baseLoad) {
            $validFrom = CarbonImmutable::parse($baseLoad->valid_from)->max($periodStart);
            $validTo = CarbonImmutable::parse($baseLoad->valid_to)->min($periodEnd);
            if ($validFrom->greaterThan($validTo) || $yearWorkdays === 0) {
                continue;
            }

            $validityShare = Workdays::count($validFrom, $validTo) / $yearWorkdays;
            $annualValue = (float) $baseLoad->value
                * ($baseLoad->calculation_type === 'weekly' ? self::STANDARD_WEEKS_PER_YEAR : 1);
            $yearly += $annualValue * $validityShare;
        }

        return [
            'yearly' => $yearly,
            'weekly' => $yearly / self::STANDARD_WEEKS_PER_YEAR,
        ];
    }
}
