<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $firstYear = 2026;
        $lastYear = (int) now()->year + 5;
        $years = range($firstYear, $lastYear);
        $year = $request->integer('year', (int) now()->year);
        if ($year < $firstYear || $year > $lastYear) {
            $year = (int) now()->year;
        }
        $month = $request->integer('month', (int) now()->month);
        if ($month < 1 || $month > 12) {
            $month = 1;
        }

        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $days = collect(range(0, $monthStart->daysInMonth - 1))
            ->map(fn (int $offset) => $monthStart->addDays($offset));
        $weekSegments = $this->weekSegments($days);
        $minimumMonth = CarbonImmutable::create($firstYear, 1, 1);
        $maximumMonth = CarbonImmutable::create($lastYear, 12, 1);
        $previousMonth = $monthStart->greaterThan($minimumMonth) ? $monthStart->subMonth() : null;
        $nextMonth = $monthStart->lessThan($maximumMonth) ? $monthStart->addMonth() : null;

        return view('calendar.index', compact(
            'years', 'year', 'month', 'monthStart', 'monthEnd', 'days',
            'weekSegments', 'previousMonth', 'nextMonth'
        ));
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $days
     * @return Collection<int, array{key: string, year: int, week: int, count: int}>
     */
    private function weekSegments(Collection $days): Collection
    {
        return $days->reduce(function (Collection $segments, CarbonImmutable $day) {
            $key = $day->isoWeekYear().'-'.$day->isoWeek();
            if ($segments->isNotEmpty() && $segments->last()['key'] === $key) {
                $segment = $segments->pop();
                $segment['count']++;
                $segments->push($segment);

                return $segments;
            }

            $segments->push([
                'key' => $key,
                'year' => $day->isoWeekYear(),
                'week' => $day->isoWeek(),
                'count' => 1,
            ]);

            return $segments;
        }, collect());
    }
}
