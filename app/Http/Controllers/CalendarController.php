<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\Holiday;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\UserPreference;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->isSuperAdmin() || $request->user()?->person?->calendar_enabled,
            403
        );

        $firstYear = 2026;
        $lastYear = (int) now()->year + 5;
        $years = range($firstYear, $lastYear);
        $calendarPreference = UserPreference::configFor($request->user()->id, UserPreference::CALENDAR);
        $year = $request->has('year')
            ? $request->integer('year')
            : (int) ($calendarPreference['year'] ?? now()->year);
        if ($year < $firstYear || $year > $lastYear) {
            $year = (int) now()->year;
        }
        $month = $request->has('month')
            ? $request->integer('month')
            : (int) ($calendarPreference['month'] ?? now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ((int) ($calendarPreference['year'] ?? 0) !== $year || (int) ($calendarPreference['month'] ?? 0) !== $month) {
            UserPreference::persist($request->user()->id, UserPreference::CALENDAR, [
                'year' => $year,
                'month' => $month,
            ]);
        }

        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $days = collect(range(0, $monthStart->daysInMonth - 1))
            ->map(fn (int $offset) => $monthStart->addDays($offset));
        $weekSegments = $this->weekSegments($days);
        $holidaysByDate = Holiday::query()
            ->where('tenant_id', CurrentTenant::id())
            ->where('active', true)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        [$people, $groupPeopleByTenant] = $this->visiblePeople($request);
        $entries = CalendarEntry::query()
            ->with('createdByUser.person')
            ->whereIn('person_id', $people->pluck('id'))
            ->whereDate('starts_on', '<=', $monthEnd)
            ->whereDate('ends_on', '>=', $monthStart)
            ->orderBy('starts_on')
            ->get();
        $entriesByPersonAndDate = $this->entriesByPersonAndDate($entries, $monthStart, $monthEnd);
        $personGroups = $groupPeopleByTenant
            ? $people->groupBy('tenant_id')->map(fn (Collection $group) => $group->values())
            : collect([CurrentTenant::id() => $people]);
        $tenants = Tenant::query()->whereIn('id', $personGroups->keys())->get()->keyBy('id');
        if ($groupPeopleByTenant) {
            $personGroups = $personGroups->sortBy(function (Collection $group, int|string $tenantId) use ($tenants) {
                $tenant = $tenants->get((int) $tenantId);

                return [! $tenant?->is_home_tenant, mb_strtolower($tenant?->name ?? '')];
            });
        }
        $ownPersonId = $request->user()?->person_id;
        $canManageOthers = $request->user()?->can('calendar.entries.manage_others') ?? false;

        $minimumMonth = CarbonImmutable::create($firstYear, 1, 1);
        $maximumMonth = CarbonImmutable::create($lastYear, 12, 1);
        $previousMonth = $monthStart->greaterThan($minimumMonth) ? $monthStart->subMonth() : null;
        $nextMonth = $monthStart->lessThan($maximumMonth) ? $monthStart->addMonth() : null;

        return view('calendar.index', compact(
            'years', 'year', 'month', 'monthStart', 'monthEnd', 'days',
            'weekSegments', 'holidaysByDate', 'previousMonth', 'nextMonth',
            'personGroups', 'tenants', 'entriesByPersonAndDate', 'ownPersonId', 'canManageOthers', 'groupPeopleByTenant'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $personId = $request->integer('person_id');
        abort_unless($personId > 0, 422);
        $this->authorizeTargetPerson($request, $personId);
        $data = $this->validatedEntryData($request);

        CalendarEntry::query()->create([
            'person_id' => $personId,
            'created_by_user_id' => $request->user()->id,
            'type' => $data['type'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'note' => $data['note'] ?? null,
        ]);

        return $this->redirectToCalendar($data)->with('status', 'calendar-entry-saved');
    }

    public function update(Request $request, CalendarEntry $calendarEntry): RedirectResponse
    {
        $this->authorizeEntryChange($request, $calendarEntry);
        $data = $this->validatedEntryData($request);

        $calendarEntry->update([
            'type' => $data['type'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'note' => $data['note'] ?? null,
        ]);

        return $this->redirectToCalendar($data)->with('status', 'calendar-entry-saved');
    }

    public function destroy(Request $request, CalendarEntry $calendarEntry): RedirectResponse
    {
        $this->authorizeEntryChange($request, $calendarEntry);
        $data = $request->validate([
            'return_year' => ['required', 'integer', 'min:2026'],
            'return_month' => ['required', 'integer', 'between:1,12'],
        ]);
        $calendarEntry->delete();

        return $this->redirectToCalendar($data)->with('status', 'calendar-entry-deleted');
    }

    private function authorizeEntryChange(Request $request, CalendarEntry $calendarEntry): void
    {
        $this->authorizeTargetPerson($request, $calendarEntry->person_id);
    }

    private function authorizeTargetPerson(Request $request, int $personId): void
    {
        $ownPerson = $personId === $request->user()?->person_id;
        $mayManageOthers = $request->user()?->can('calendar.entries.manage_others') ?? false;
        $personIsVisible = $this->visiblePeople($request)[0]->contains('id', $personId);

        abort_unless($personIsVisible && ($ownPerson || $mayManageOthers), 403);
    }

    /** @return array{type: string, starts_on: string, ends_on: string, note?: string|null, return_year: int, return_month: int} */
    private function validatedEntryData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(CalendarEntry::TYPES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:255'],
            'return_year' => ['required', 'integer', 'min:2026'],
            'return_month' => ['required', 'integer', 'between:1,12'],
        ]);
    }

    /** @param array{return_year: int, return_month: int} $data */
    private function redirectToCalendar(array $data): RedirectResponse
    {
        return redirect()->route('kalender', [
            'year' => $data['return_year'],
            'month' => $data['return_month'],
        ]);
    }

    /** @return array{0: Collection<int, Person>, 1: bool} */
    private function visiblePeople(Request $request): array
    {
        $activeTenantId = CurrentTenant::id();
        $multiTenant = SystemSetting::multiTenantEnabled();
        $activeTenant = Tenant::query()->find($activeTenantId);
        $query = Person::query()
            ->withoutGlobalScope('tenant')
            ->with('tenant')
            ->where('calendar_enabled', true)
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! $multiTenant) {
            return [$query->where('people.tenant_id', $activeTenantId)->get(), false];
        }

        if ($activeTenant?->is_home_tenant) {
            $user = $request->user();
            $tenantIds = CurrentTenant::availableTenants()->pluck('id');
            if ($tenantIds->isEmpty() && $user) {
                $tenantIds = collect([$user->tenant_id]);
            }

            return [$query->whereIn('people.tenant_id', $tenantIds)->get(), $tenantIds->count() > 1];
        }

        $homeTenantId = Tenant::query()->where('is_home_tenant', true)->value('id');
        $query->where(function (Builder $query) use ($activeTenantId, $homeTenantId) {
            $query->where('people.tenant_id', $activeTenantId);
            if ($homeTenantId) {
                $query->orWhere(function (Builder $query) use ($activeTenantId, $homeTenantId) {
                    $query->where('people.tenant_id', $homeTenantId)
                        ->whereIn('people.id', DB::table('person_tenant')
                            ->select('person_id')
                            ->where('tenant_id', $activeTenantId));
                });
            }
        });

        return [$query->get(), false];
    }

    /** @param Collection<int, CalendarEntry> $entries */
    private function entriesByPersonAndDate(Collection $entries, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): Collection
    {
        $result = collect();
        foreach ($entries as $entry) {
            $start = CarbonImmutable::parse($entry->starts_on)->max($monthStart);
            $end = CarbonImmutable::parse($entry->ends_on)->min($monthEnd);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $key = $entry->person_id.'|'.$day->toDateString();
                $result->put($key, $result->get($key, collect())->push($entry));
            }
        }

        return $result;
    }

    /** @param Collection<int, CarbonImmutable> $days */
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
