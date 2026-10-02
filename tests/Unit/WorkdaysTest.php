<?php

namespace Tests\Unit;

use App\Support\Workdays;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class WorkdaysTest extends TestCase
{
    public function test_counts_only_weekdays_inclusive(): void
    {
        $this->assertSame(261, Workdays::count(CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 12, 31)));
        $this->assertSame(5, Workdays::count(CarbonImmutable::create(2026, 10, 5), CarbonImmutable::create(2026, 10, 11)));
        $this->assertSame(1, Workdays::count(CarbonImmutable::create(2026, 10, 5), CarbonImmutable::create(2026, 10, 5)));
        $this->assertSame(0, Workdays::count(CarbonImmutable::create(2026, 10, 10), CarbonImmutable::create(2026, 10, 11)));
    }

    public function test_start_after_end_counts_zero(): void
    {
        $this->assertSame(0, Workdays::count(CarbonImmutable::create(2026, 10, 6), CarbonImmutable::create(2026, 10, 5)));
    }
}
