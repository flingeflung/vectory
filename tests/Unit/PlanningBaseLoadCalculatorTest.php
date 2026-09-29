<?php

namespace Tests\Unit;

use App\Models\PlanningBaseLoad;
use App\Services\PlanningBaseLoadCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PlanningBaseLoadCalculatorTest extends TestCase
{
    public function test_it_combines_weekly_and_yearly_values_on_one_annual_basis(): void
    {
        $baseLoads = [
            new PlanningBaseLoad([
                'calculation_type' => 'weekly',
                'value' => 10,
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
            ]),
            new PlanningBaseLoad([
                'calculation_type' => 'yearly',
                'value' => 104,
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
            ]),
        ];

        $totals = (new PlanningBaseLoadCalculator)->totals($baseLoads, 2026);

        $this->assertEqualsWithDelta(624, $totals['yearly'], 0.00001);
        $this->assertEqualsWithDelta(12, $totals['weekly'], 0.00001);
    }

    public function test_it_weights_values_by_workdays_in_the_validity_period(): void
    {
        $baseLoads = [
            new PlanningBaseLoad([
                'calculation_type' => 'yearly',
                'value' => 261,
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-06-30',
            ]),
        ];

        $totals = (new PlanningBaseLoadCalculator)->totals($baseLoads, 2026);

        $this->assertEqualsWithDelta(129, $totals['yearly'], 0.00001);
        $this->assertEqualsWithDelta(129 / 52, $totals['weekly'], 0.00001);
    }

    public function test_it_limits_the_personal_total_to_the_employment_period(): void
    {
        $baseLoads = [
            new PlanningBaseLoad([
                'calculation_type' => 'yearly',
                'value' => 261,
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
            ]),
        ];

        $totals = (new PlanningBaseLoadCalculator)->totals(
            $baseLoads,
            2026,
            CarbonImmutable::create(2026, 7, 1),
            CarbonImmutable::create(2026, 12, 31)
        );

        $this->assertEqualsWithDelta(132, $totals['yearly'], 0.00001);
        $this->assertEqualsWithDelta(132 / 52, $totals['weekly'], 0.00001);
    }
}
