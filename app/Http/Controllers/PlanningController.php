<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Person;
use App\Models\PersonVacationDays;
use App\Models\PersonWeeklyHours;
use App\Models\PlanningBaseLoad;
use App\Models\PlanningPersonBaseLoad;
use App\Models\ProjectPerson;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\UserPreference;
use App\Services\PlanningBaseLoadCalculator;
use App\Services\ProjectPlanningCalculator;
use App\Support\CurrentTenant;
use App\Support\PlanningNav;
use App\Support\PlanningAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hauptnavigationspunkt "Planung": Mitglieder einer Funktionsgruppe sehen
 * ihre Projektplanung; planning.view schaltet die erweiterten Tabs frei.
 * Design wie der Admin-Bereich (Tabs oben, siehe
 * App\Support\PlanningNav/planning-layout.blade.php). Erster Tab
 * "Stunden": Jahresstunden-Kapazität je Person, aus Wochenstunden- und
 * Urlaubstage-Historie (siehe Person::weeklyHours()/vacationDays()) und den
 * (reinen Wochentags-)Arbeitstagen des gewählten Jahres berechnet.
 */
class PlanningController extends Controller
{
    private const HOURS_SORTABLE_COLUMNS = ['name', 'department', 'annotation', 'wost', 'workdays', 'holidays', 'vacation_hours', 'annual_hours', 'base_load', 'project_hours'];

    public function index(Request $request): RedirectResponse
    {
        abort_unless(PlanningAccess::canOpen($request->user()), 403);

        return redirect()->route(PlanningNav::preferredRoute($request->user()));
    }

    public function projektplanung(Request $request, ProjectPlanningCalculator $calculator): View
    {
        abort_unless(PlanningAccess::canOpen($request->user()), 403);
        PlanningNav::remember($request->user(), 'planung.projektplanung');

        $displayMode = $request->query('view') === 'year' ? 'year' : 'month';
        $contentMode = $request->query('content') === 'utilization' ? 'utilization' : 'projects';
        $firstYear = 2026;
        $lastYear = (int) now()->year + 5;
        $years = range($firstYear, $lastYear);
        $year = $request->integer('year', (int) now()->year);
        if ($year < $firstYear || $year > $lastYear) {
            $year = (int) now()->year;
        }
        $month = $request->integer('month', (int) now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $days = collect(range(0, $monthStart->daysInMonth - 1))->map(fn (int $offset) => $monthStart->addDays($offset));
        $dayWeekSegments = $days->reduce(function (Collection $segments, CarbonImmutable $day) {
            $key = $day->isoWeekYear().'-'.$day->isoWeek();
            if ($segments->isNotEmpty() && $segments->last()['key'] === $key) {
                $segment = $segments->pop();
                $segment['count']++;
                $segments->push($segment);

                return $segments;
            }

            return $segments->push(['key' => $key, 'week' => $day->isoWeek(), 'count' => 1]);
        }, collect());

        $yearStart = CarbonImmutable::now()->setISODate($year, 1)->startOfWeek();
        $yearEnd = CarbonImmutable::now()->setISODate($year + 1, 1)->startOfWeek();
        $weeks = collect();
        $weekMonthSegments = collect();
        for ($weekStart = $yearStart; $weekStart->lessThan($yearEnd); $weekStart = $weekStart->addWeek()) {
            $monthKey = $weekStart->addDays(3)->format('Y-m');
            $weeks->push(['key' => sprintf('%04d-W%02d', $weekStart->isoWeekYear(), $weekStart->isoWeek()), 'number' => $weekStart->isoWeek(), 'start' => $weekStart]);
            if ($weekMonthSegments->isNotEmpty() && $weekMonthSegments->last()['key'] === $monthKey) {
                $segment = $weekMonthSegments->pop();
                $segment['count']++;
                $weekMonthSegments->push($segment);
            } else {
                $weekMonthSegments->push(['key' => $monthKey, 'label' => $weekStart->addDays(3)->translatedFormat('F'), 'count' => 1]);
            }
        }

        [$personGroups, $tenants] = $this->projectPlanningPeople($request);
        $eligiblePeople = $personGroups->flatten(1);
        $eligiblePersonIds = $eligiblePeople->pluck('id');
        $preference = UserPreference::configFor($request->user()->id, UserPreference::PROJECT_PLANNING);
        $hasSavedSelection = array_key_exists('person_ids', $preference);
        $requestedPersonIds = $request->has('person_filter')
            ? collect($request->input('people', []))
            : collect($preference['person_ids'] ?? $eligiblePersonIds);
        $selectedPersonIds = $requestedPersonIds
            ->map(fn ($id) => (int) $id)
            ->intersect($eligiblePersonIds)
            ->unique()
            ->values();
        $preferenceChanged = false;
        if ($request->has('person_filter') || ($hasSavedSelection && $selectedPersonIds->all() !== array_values($preference['person_ids']))) {
            $preference['person_ids'] = $selectedPersonIds->all();
            $preferenceChanged = true;
        }
        $selectedPeople = $eligiblePeople->whereIn('id', $selectedPersonIds)->keyBy('id');
        $selectedPersonGroups = $personGroups
            ->map(fn (Collection $people) => $people->filter(fn (Person $person) => $selectedPeople->has($person->id))->values())
            ->filter(fn (Collection $people) => $people->isNotEmpty());

        $organizations = SystemSetting::multiTenantEnabled()
            ? CurrentTenant::availableTenants()
            : Tenant::query()->whereKey(CurrentTenant::id())->get();
        $eligibleOrganizationIds = $organizations->pluck('id');
        $hasSavedOrganizationSelection = array_key_exists('organization_ids', $preference);
        $requestedOrganizationIds = $request->has('organization_filter')
            ? collect($request->input('organizations', []))
            : collect($preference['organization_ids'] ?? $eligibleOrganizationIds);
        $selectedOrganizationIds = $requestedOrganizationIds
            ->map(fn ($id) => (int) $id)
            ->intersect($eligibleOrganizationIds)
            ->unique()
            ->values();
        if ($request->has('organization_filter') || ($hasSavedOrganizationSelection && $selectedOrganizationIds->all() !== array_values($preference['organization_ids']))) {
            $preference['organization_ids'] = $selectedOrganizationIds->all();
            $preferenceChanged = true;
        }
        if ($preferenceChanged) {
            UserPreference::persist($request->user()->id, UserPreference::PROJECT_PLANNING, $preference);
        }

        $rangeStart = $displayMode === 'month' ? $monthStart : CarbonImmutable::create($year, 1, 1);
        $rangeEnd = $displayMode === 'month' ? $monthStart->endOfMonth()->startOfDay() : CarbonImmutable::create($year, 12, 31);
        $assignments = ProjectPerson::query()->withoutGlobalScope('tenant')
            ->whereIn('person_id', $selectedPersonIds)
            ->with(['person', 'functionGroup', 'project.projectWorkflowSteps.workflowStep'])
            ->whereHas('project', fn (Builder $query) => $query->whereIn('tenant_id', $selectedOrganizationIds)
                ->whereIn('status', [0, 1])
                ->where(function (Builder $query) use ($rangeStart, $rangeEnd) {
                    $query->whereNull('start_date')->orWhereNull('end_date')
                        ->orWhere(fn (Builder $dated) => $dated->whereDate('start_date', '<=', $rangeEnd)->whereDate('end_date', '>=', $rangeStart));
                }))
            ->get();
        $projectTenantIds = $assignments->pluck('project.tenant_id')->filter()->unique();
        $projectTenants = Tenant::query()->whereIn('id', $projectTenantIds)->get()->keyBy('id');
        $tenantPalette = ['#bfdbfe', '#bbf7d0', '#fde68a', '#fecdd3', '#ddd6fe', '#bae6fd', '#fed7aa', '#d9f99d'];
        $tenantColors = $projectTenantIds->values()->mapWithKeys(fn ($tenantId, $index) => [(int) $tenantId => $tenantPalette[$index % count($tenantPalette)]]);
        $maySeeAllProjectDetails = $request->user()->canAccessAllOrganizations();

        $projectRowsByPerson = $assignments->groupBy('person_id')->map(function (Collection $personAssignments) use ($maySeeAllProjectDetails, $projectTenants, $tenantColors) {
            return $personAssignments->groupBy('project_id')->map(function (Collection $rows) use ($maySeeAllProjectDetails, $projectTenants, $tenantColors) {
                $project = $rows->first()->project;
                $mayShowDetails = $maySeeAllProjectDetails || $project->tenant_id === CurrentTenant::id();

                return [
                    'project' => $project,
                    'label' => $mayShowDetails ? $project->source_pn.' – '.$project->title : __('Andere Projekte'),
                    'tenant' => $projectTenants->get($project->tenant_id),
                    'color' => $tenantColors->get($project->tenant_id, '#bfdbfe'),
                    'mayShowDetails' => $mayShowDetails,
                    'canOpen' => $mayShowDetails && $project->tenant_id === CurrentTenant::id(),
                    'functionGroups' => $rows->pluck('functionGroup.short_name')->filter()->unique()->join(', '),
                    'plannedHours' => (float) $rows->sum('planned_hours'),
                    'missingHours' => $rows->contains(fn (ProjectPerson $row) => $row->planned_hours === null),
                    'milestones' => $mayShowDetails
                        ? $project->projectWorkflowSteps
                            ->filter(fn ($step) => $step->due_date
                                && $step->workflowStep?->workflow_id === $project->workflow_id
                                && $step->workflowStep->has_due_date)
                            ->map(fn ($step) => [
                                'date' => $step->due_date->toDateString(),
                                'label' => $step->effectiveMilestoneTitle() ?: $step->workflowStep->title,
                            ])->values()
                        : collect(),
                ];
            })->sortBy(fn (array $row) => $row['project']->start_date?->toDateString() ?? '9999-12-31')->values();
        });

        $planningByPersonAndDate = [];
        $calculator->prime($selectedPeople->values(), $rangeStart, $rangeEnd);
        foreach ($assignments as $assignment) {
            foreach ($calculator->plannedHoursByDay($assignment, $rangeStart, $rangeEnd) as $date => $hours) {
                $planningByPersonAndDate[$assignment->person_id][$date] = ($planningByPersonAndDate[$assignment->person_id][$date] ?? 0) + $hours;
            }
        }
        $capacityByPerson = $selectedPeople->mapWithKeys(fn (Person $person) => [
            $person->id => $calculator->capacityByDay($person, $rangeStart, $rangeEnd),
        ]);
        $utilizationByPerson = $selectedPeople->mapWithKeys(function (Person $person) use ($capacityByPerson, $displayMode, $planningByPersonAndDate, $weeks) {
            $capacity = $capacityByPerson->get($person->id, collect());
            $planned = collect($planningByPersonAndDate[$person->id] ?? []);
            if ($displayMode === 'month') {
                return [$person->id => $capacity->map(function (array $values, string $date) use ($planned) {
                    $project = (float) $planned->get($date, 0);

                    return [...$values, 'project' => $project, 'remaining' => $values['available'] - $project];
                })];
            }

            return [$person->id => $weeks->mapWithKeys(function (array $week) use ($capacity, $planned) {
                $weekEnd = $week['start']->addDays(6);
                $values = $capacity->filter(fn ($value, $date) => CarbonImmutable::parse($date)->between($week['start'], $weekEnd));
                $project = (float) $planned->filter(fn ($value, $date) => CarbonImmutable::parse($date)->between($week['start'], $weekEnd))->sum();
                $available = (float) $values->sum('available');

                return [$week['key'] => [
                    'work' => (float) $values->sum('work'),
                    'absence' => (float) $values->sum('absence'),
                    'base_load' => (float) $values->sum('base_load'),
                    'available' => $available,
                    'project' => $project,
                    'remaining' => $available - $project,
                ]];
            })];
        });

        $minimumMonth = CarbonImmutable::create($firstYear, 1, 1);
        $maximumMonth = CarbonImmutable::create($lastYear, 12, 1);
        $previousMonth = $monthStart->greaterThan($minimumMonth) ? $monthStart->subMonth() : null;
        $nextMonth = $monthStart->lessThan($maximumMonth) ? $monthStart->addMonth() : null;

        return view('planning.projektplanung', compact(
            'displayMode', 'contentMode', 'years', 'year', 'month', 'monthStart', 'days', 'dayWeekSegments',
            'weeks', 'weekMonthSegments', 'personGroups', 'selectedPersonGroups', 'selectedPersonIds',
            'organizations', 'selectedOrganizationIds',
            'tenants', 'previousMonth', 'nextMonth', 'projectRowsByPerson', 'utilizationByPerson',
            'projectTenants', 'tenantColors', 'rangeStart', 'rangeEnd'
        ));
    }

    /** @return array{0: Collection<int, Collection<int, Person>>, 1: Collection<int, Tenant>} */
    private function projectPlanningPeople(Request $request): array
    {
        $user = $request->user();
        $activeTenantId = CurrentTenant::id();
        $homeTenant = Tenant::query()->where('is_home_tenant', true)->first();
        $homeTenantId = $homeTenant?->id;
        $hasCentralScope = $user->canAccessAllOrganizations();

        if (! PlanningAccess::canViewExtended($user)) {
            $person = Person::query()
                ->withoutGlobalScope('tenant')
                ->with(['weeklyHours', 'calendarEntries'])
                ->findOrFail($user->person_id);

            return [
                collect([$person->tenant_id => collect([$person])]),
                Tenant::query()->whereKey($person->tenant_id)->get()->keyBy('id'),
            ];
        }

        $query = Person::query()
            ->withoutGlobalScope('tenant')
            ->visibleToRole($user->role)
            ->where('active', true)
            ->where('resource_planning', true)
            ->with(['weeklyHours', 'calendarEntries'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! SystemSetting::multiTenantEnabled() || $homeTenantId === null) {
            $people = $query->where('people.tenant_id', $activeTenantId)->get();
        } elseif ($hasCentralScope) {
            $tenantIds = collect([$homeTenantId]);
            if ($activeTenantId !== $homeTenantId) {
                $tenantIds->push($activeTenantId);
            }
            $people = $query->whereIn('people.tenant_id', $tenantIds)->get();
        } else {
            $people = $query->where(function (Builder $query) use ($activeTenantId, $homeTenantId, $user) {
                $query->where('people.tenant_id', $activeTenantId)
                    ->orWhere('people.id', $user->person_id)
                    ->orWhere(function (Builder $query) use ($activeTenantId, $homeTenantId) {
                        $query->where('people.tenant_id', $homeTenantId)
                            ->whereIn('people.id', DB::table('person_tenant')->select('person_id')->where('tenant_id', $activeTenantId));
                    });
            })->get();
        }

        $groups = $people->groupBy('tenant_id');
        $groups = $groups->sortBy(fn (Collection $people, int|string $tenantId) => [(int) $tenantId !== $homeTenantId, (int) $tenantId]);
        $tenants = Tenant::query()->whereIn('id', $groups->keys())->get()->keyBy('id');

        return [$groups, $tenants];
    }

    public function grundlast(Request $request, PlanningBaseLoadCalculator $calculator): View
    {
        abort_unless($request->user()->can('planning.view'), 403);
        PlanningNav::remember($request->user(), 'planung.grundlast');

        $tenantId = CurrentTenant::id();
        $currentYear = (int) now()->year;
        $baseLoadYears = PlanningBaseLoad::query()->where('tenant_id', $tenantId)->pluck('year');
        $years = $baseLoadYears
            ->merge([$currentYear, $currentYear + 1, $currentYear + 2])
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
        $year = $request->integer('year', $currentYear);
        if (! in_array($year, $years, true)) {
            $year = $currentYear;
        }

        $baseLoads = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->orderBy('valid_from')
            ->orderBy('name')
            ->get();
        $totals = $calculator->totals($baseLoads, $year);
        $weeklyTotal = $totals['weekly'];
        $yearlyTotal = $totals['yearly'];
        $standardWeeks = PlanningBaseLoadCalculator::STANDARD_WEEKS_PER_YEAR;
        $previousYearCount = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year - 1)
            ->count();
        $tenant = CurrentTenant::current();

        return view('planning.grundlast', compact('years', 'year', 'baseLoads', 'weeklyTotal', 'yearlyTotal', 'standardWeeks', 'previousYearCount', 'tenant'));
    }

    public function grundlastPerson(Request $request, PlanningBaseLoadCalculator $calculator): View
    {
        abort_unless($request->user()->can('planning.view'), 403);
        PlanningNav::remember($request->user(), 'planung.grundlast-person');

        $tenantId = CurrentTenant::id();
        $currentYear = (int) now()->year;
        $years = PlanningBaseLoad::query()->where('tenant_id', $tenantId)->pluck('year')
            ->merge(PlanningPersonBaseLoad::query()->where('tenant_id', $tenantId)->pluck('year'))
            ->merge([$currentYear, $currentYear + 1, $currentYear + 2])
            ->map(fn ($value) => (int) $value)->unique()->sortDesc()->values()->all();
        $year = $request->integer('year', $currentYear);
        if (! in_array($year, $years, true)) {
            $year = $currentYear;
        }
        $people = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($request->user()->role)
            ->visibleInTenant($tenantId)
            ->whereHas('user')
            ->where('resource_planning', true)
            ->orderBy('last_name')->orderBy('first_name')
            ->get();
        $person = $people->firstWhere('id', $request->integer('person')) ?? $people->first();
        $personBaseLoads = $person
            ? PlanningPersonBaseLoad::query()
                ->where('tenant_id', $tenantId)
                ->where('person_id', $person->id)
                ->where('year', $year)
                ->orderBy('valid_from')->orderBy('name')->get()
            : collect();
        $baseLoads = PlanningBaseLoad::query()->where('tenant_id', $tenantId)->where('year', $year)->get();
        $linkedBaseIds = $personBaseLoads->pluck('planning_base_load_id')->filter();
        $missingBaseLoadCount = $baseLoads->whereNotIn('id', $linkedBaseIds)->count();
        $deletedBaseLoadCount = $personBaseLoads->whereNull('planning_base_load_id')->count();
        $totals = $calculator->totals($personBaseLoads, $year);
        $weeklyTotal = $totals['weekly'];
        $yearlyTotal = $totals['yearly'];
        $standardWeeks = PlanningBaseLoadCalculator::STANDARD_WEEKS_PER_YEAR;

        return view('planning.grundlast-person', compact(
            'years', 'year', 'people', 'person', 'personBaseLoads', 'missingBaseLoadCount',
            'deletedBaseLoadCount', 'weeklyTotal', 'yearlyTotal', 'standardWeeks'
        ));
    }

    public function arbeitszeit(Request $request): View
    {
        abort_unless($request->user()->can('planning.view'), 403);
        PlanningNav::remember($request->user(), 'planung.arbeitszeit');

        $tenantId = CurrentTenant::id();
        $peopleQuery = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($request->user()->role)
            ->visibleInTenant($tenantId)
            ->whereHas('user')
            ->where('resource_planning', true);

        $personIds = (clone $peopleQuery)->pluck('id');
        [$years, $year] = $this->planningYears($personIds, $request);
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();

        // Exakt dieselbe fachliche Auswahl wie in der Stunden-Tabelle:
        // Personen ohne einen im gewählten Jahr gültigen Wochenstundenwert
        // werden hier ebenfalls nicht angeboten.
        $people = (clone $peopleQuery)
            ->with(['weeklyHours' => fn ($query) => $query->orderByRaw('valid_from IS NOT NULL')->orderBy('valid_from')])
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->filter(fn (Person $person) => $this->overlappingWeeklyHours($person, $yearStart, $yearEnd)->isNotEmpty())
            ->values();

        $showAllPeople = $request->query('person') === 'all';
        $personId = $request->integer('person');
        $person = $showAllPeople ? null : ($people->firstWhere('id', $personId) ?? $people->first());
        $highestWeeklyHours = (float) ($people
            ->flatMap(function (Person $item) use ($yearStart, $yearEnd) {
                [$employmentStart, $employmentEnd] = $this->employmentPeriod($item, $yearStart, $yearEnd);

                return $employmentStart->lessThanOrEqualTo($employmentEnd)
                    ? $this->overlappingWeeklyHours($item, $employmentStart, $employmentEnd)
                    : collect();
            })
            ->max(fn (PersonWeeklyHours $row) => (float) $row->hours) ?? 0);
        $roundedYMax = (int) ceil($highestWeeklyHours / 10) * 10;
        $yMax = max(10, $roundedYMax, (int) ceil($highestWeeklyHours + 2));
        $chartPeople = $showAllPeople ? $people : collect([$person])->filter();
        $datasets = $chartPeople->map(fn (Person $item) => [
            'personId' => $item->id,
            'label' => $item->fullName(),
            'points' => $this->workingHoursChartPoints($item, $yearStart, $yearEnd),
        ])->values();

        return view('planning.arbeitszeit', compact('years', 'year', 'people', 'person', 'datasets', 'showAllPeople', 'yMax'));
    }

    private function workingHoursChartPoints(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): Collection
    {
        $daysInYear = $yearStart->diffInDays($yearEnd) + 1;
        [$employmentStart, $employmentEnd] = $this->employmentPeriod($person, $yearStart, $yearEnd);
        if ($employmentStart->greaterThan($employmentEnd)) {
            return collect([['x' => 0, 'y' => 0], ['x' => $daysInYear, 'y' => 0, 'terminal' => true]]);
        }

        $points = collect();
        if ($employmentStart->greaterThan($yearStart)) {
            $points->push(['x' => 0, 'y' => 0]);
        }
        $this->overlappingWeeklyHours($person, $employmentStart, $employmentEnd)
            ->each(function (PersonWeeklyHours $row) use ($employmentStart, $points, $yearStart) {
                $start = $row->valid_from
                    ? CarbonImmutable::parse($row->valid_from->toDateString())->max($employmentStart)
                    : $employmentStart;
                $points->push(['x' => $yearStart->diffInDays($start), 'y' => (float) $row->hours]);
            });

        if ($points->isNotEmpty() && $employmentEnd->lessThan($yearEnd)) {
            $points->push(['x' => $yearStart->diffInDays($employmentEnd->addDay()), 'y' => 0]);
        }
        if ($points->isNotEmpty()) {
            $points->push(['x' => $daysInYear, 'y' => $points->last()['y'], 'terminal' => true]);
        }

        return $points;
    }

    public function stunden(Request $request, PlanningBaseLoadCalculator $baseLoadCalculator): View
    {
        abort_unless($request->user()->can('planning.view'), 403);
        PlanningNav::remember($request->user(), 'planung.stunden');

        $tenantId = CurrentTenant::id();
        $viewerRole = $request->user()->role;

        // Gleiche Sichtbarkeit wie die Personenliste (Ralf: "auf die die
        // Person Zugriff hat, die sich das ansieht") - Heimat-Personen des
        // aktiven Kunden plus freigeschaltete Personen, Superadmins bleiben
        // für alle anderen unsichtbar. Nur Login-Personen: nur die haben
        // eine Wochenstunden-/Urlaubstage-Historie (siehe Migrationen).
        $peopleQuery = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($viewerRole)
            ->visibleInTenant($tenantId)
            ->whereHas('user');

        $personIds = (clone $peopleQuery)->where('resource_planning', true)->pluck('id');

        // Jahre fürs Dropdown: alle Jahre, die irgendein Wochenstunden-
        // Datensatz dieser (sichtbaren) Personen berührt (Ralf, 2026-09-28:
        // "Jahre, für die [Wochenstunden-]Planungsdaten vorliegen") - plus
        // immer das laufende Jahr, auch wenn noch nie ein Datum gepflegt
        // wurde (der ursprüngliche "schon immer"-Datensatz deckt es ja ab).
        [$years, $year] = $this->planningYears($personIds, $request);

        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $totalWorkdays = $this->countWeekdays($yearStart, $yearEnd);
        $activeHolidays = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->whereBetween('date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->whereBetween('weekday', [1, 5])
            ->get();
        $baseLoads = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->get();
        $personBaseLoads = PlanningPersonBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->whereIn('person_id', $personIds)
            ->get()
            ->groupBy('person_id');
        $sort = in_array($request->query('sort'), self::HOURS_SORTABLE_COLUMNS, true)
            ? $request->query('sort')
            : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        // Ralf, 2026-09-29: Abteilungsfilter funktionierte nicht zuverlässig für
        // alle Szenarien (ohne Mandantenfähigkeit sind alle Personen eigene
        // Mitarbeiter, kein Kriterium zum Ausschließen externer Kräfte) -
        // ersetzt durch eine direkte, manuell gepflegte Markierung je Person
        // (Person::resource_planning, siehe Personendetails).
        $people = (clone $peopleQuery)
            ->where('resource_planning', true)
            ->with(['weeklyHours', 'vacationDays', 'department'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $collator = new \Collator('de_DE');
        $rows = $people->map(function (Person $person) use ($activeHolidays, $baseLoadCalculator, $baseLoads, $personBaseLoads, $yearStart, $yearEnd, $totalWorkdays) {
            $employmentStart = $person->start_date
                ? CarbonImmutable::parse($person->start_date->toDateString())->max($yearStart)
                : $yearStart;
            $employmentEnd = $person->end_date
                ? CarbonImmutable::parse($person->end_date->toDateString())->min($yearEnd)
                : $yearEnd;
            $employmentWorkdays = $employmentStart->lessThanOrEqualTo($employmentEnd)
                ? $this->countWeekdays($employmentStart, $employmentEnd)
                : 0;
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

            // Urlaubstage-Historie (Ralf, 2026-09-28): ändert sich der
            // Jahresanspruch innerhalb des Jahres, wird er anteilig nach
            // Arbeitstagen der jeweiligen Gültigkeit geblendet (analog zu
            // einer unterjährigen Anpassung) - dieselbe Denkweise wie bei
            // den Wochenstunden.
            $vacationDaysEffective = $employmentWorkdays > 0
                ? $this->overlappingSum($person->vacationDays, $employmentStart, $employmentEnd, fn (PersonVacationDays $row, int $segmentWorkdays) => $totalWorkdays > 0 ? (float) $row->days * $segmentWorkdays / $totalWorkdays : 0.0) ?? 0.0
                : 0.0;
            $vacationHours = $vacationDaysEffective * $effectiveWoSt / 5;
            $netto = $brutto - $vacationHours;
            $annotations = collect();
            if ($person->start_date && $person->start_date->year === $yearStart->year) {
                $annotations->push(__('ab :date', ['date' => $person->start_date->format('d.m.Y')]));
            }
            if ($person->end_date && $person->end_date->year === $yearStart->year) {
                $annotations->push(__('bis :date', ['date' => $person->end_date->format('d.m.Y')]));
            }
            $baseLoad = $baseLoadCalculator->totalsForPerson(
                $baseLoads,
                $personBaseLoads->get($person->id, collect()),
                $yearStart->year,
                $employmentStart,
                $employmentEnd
            )['yearly'];
            $projectHours = $netto - $baseLoad;

            return [
                'personId' => $person->id,
                'firstName' => $person->first_name,
                'lastName' => $person->last_name,
                'sortKey' => $person->last_name.', '.$person->first_name,
                'department' => $person->department?->name ?? '',
                'annotation' => $annotations->implode(', '),
                'annotationSort' => collect([! $person->active ? __('Inaktiv') : null, ...$annotations])->filter()->implode(', '),
                'inactive' => ! $person->active,
                'wost' => $effectiveWoSt,
                'workdays' => $employmentWorkdays,
                'holidays' => $holidayCount,
                'vacationHours' => $vacationHours,
                'jahresstd' => $netto,
                'baseLoad' => $baseLoad,
                'projectHours' => $projectHours,
            ];
        })
            ->filter()
            ->sort(function ($a, $b) use ($collator, $direction, $sort) {
                $result = match ($sort) {
                    'department' => $collator->compare($a['department'], $b['department']),
                    'annotation' => $collator->compare($a['annotationSort'], $b['annotationSort']),
                    'wost' => $a['wost'] <=> $b['wost'],
                    'workdays' => $a['workdays'] <=> $b['workdays'],
                    'holidays' => $a['holidays'] <=> $b['holidays'],
                    'vacation_hours' => $a['vacationHours'] <=> $b['vacationHours'],
                    'annual_hours' => $a['jahresstd'] <=> $b['jahresstd'],
                    'base_load' => $a['baseLoad'] <=> $b['baseLoad'],
                    'project_hours' => $a['projectHours'] <=> $b['projectHours'],
                    default => $collator->compare($a['sortKey'], $b['sortKey']),
                };
                if ($result === 0 && $sort !== 'name') {
                    $result = $collator->compare($a['sortKey'], $b['sortKey']);
                }

                return $direction === 'desc' ? -$result : $result;
            })
            ->values();

        $total = (float) $rows->sum('jahresstd');
        $baseLoadTotal = (float) $rows->sum('baseLoad');
        $projectHoursTotal = (float) $rows->sum('projectHours');

        return view('planning.stunden', compact('years', 'year', 'totalWorkdays', 'rows', 'total', 'baseLoadTotal', 'projectHoursTotal', 'sort', 'direction'));
    }

    private function planningYears($personIds, Request $request): array
    {
        $dateValues = DB::table('person_weekly_hours')->whereIn('person_id', $personIds)
            ->get(['valid_from', 'valid_to'])
            ->flatMap(fn ($row) => [$row->valid_from, $row->valid_to])
            ->filter();
        $currentYear = (int) now()->year;
        $minYear = $dateValues->isNotEmpty() ? (int) $dateValues->map(fn ($date) => substr($date, 0, 4))->min() : $currentYear;
        $maxYear = max($currentYear, $dateValues->isNotEmpty() ? (int) $dateValues->map(fn ($date) => substr($date, 0, 4))->max() : $currentYear);
        $years = range($maxYear, $minYear, -1);
        $year = (int) $request->integer('year', $currentYear);

        return [$years, in_array($year, $years, true) ? $year : $years[0]];
    }

    private function overlappingWeeklyHours(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd)
    {
        return $person->weeklyHours->filter(function (PersonWeeklyHours $row) use ($yearStart, $yearEnd) {
            $rowStart = $row->valid_from ? CarbonImmutable::parse($row->valid_from->toDateString()) : $yearStart;
            $rowEnd = $row->valid_to ? CarbonImmutable::parse($row->valid_to->toDateString()) : $yearEnd;

            return $rowStart->lessThanOrEqualTo($yearEnd) && $rowEnd->greaterThanOrEqualTo($yearStart);
        })->values();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function employmentPeriod(Person $person, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): array
    {
        $start = $person->start_date
            ? CarbonImmutable::parse($person->start_date->toDateString())->max($yearStart)
            : $yearStart;
        $end = $person->end_date
            ? CarbonImmutable::parse($person->end_date->toDateString())->min($yearEnd)
            : $yearEnd;

        return [$start, $end];
    }

    /**
     * Summiert $valueFn über alle Historien-Zeilen, die das Jahr
     * [$yearStart, $yearEnd] überhaupt berühren - je Zeile begrenzt auf den
     * Überschneidungs-Zeitraum mit dem Jahr (Ralf, 2026-09-28: eine Person
     * kann ihre Wochenstunden/ihren Urlaubsanspruch unterjährig ändern, die
     * Jahresstunden dürfen dadurch nicht falsch werden). Gibt null zurück,
     * wenn KEINE Zeile das Jahr berührt (Person hat dafür noch keine gültigen
     * Daten - wird dann aus der Liste ausgeschlossen).
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
            $sum += $valueFn($row, $this->countWeekdays($segmentStart, $segmentEnd), $segmentStart, $segmentEnd);
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

    /**
     * Reine Wochentage (Mo-Fr) im Zeitraum, ohne Feiertage oder
     * Krankheitstage abzuziehen (Ralf, 2026-09-28, bewusste Vereinfachung).
     */
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
