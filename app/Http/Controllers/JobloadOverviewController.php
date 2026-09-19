<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobloadOverviewController extends Controller
{
    public function weekDetail(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->person_id, 403);
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'job_id' => ['required', 'integer'],
            'person_id' => ['nullable', 'integer'],
        ]);
        [$year, $number] = array_map('intval', explode('-W', $data['week']));
        abort_unless($year >= 2000 && $year <= 2100, 422);
        $start = CarbonImmutable::now()->setISODate($year, $number)->startOfWeek();
        abort_unless($start->isoWeekYear() === $year && $start->isoWeek() === $number, 422);
        $tenantId = CurrentTenant::id();
        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $hourDecimals = match ($timeGrid) { 60 => 0, 30 => 1, default => 2 };
        $job = DB::table('job_types')->where('tenant_id', $tenantId)->find($data['job_id']);
        abort_unless($job, 404);

        $canViewAll = $user->can('jobload.overview.view_all');
        $query = DB::table('job_hours')
            ->join('people', 'people.id', '=', 'job_hours.person_id')
            ->where('job_hours.tenant_id', $tenantId)
            ->where('job_hours.job_type_id', $job->id)
            ->whereBetween('job_hours.work_date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->where('job_hours.hours', '>', 0);
        if (! $canViewAll) {
            $query->where('job_hours.person_id', $user->person_id);
        } elseif (isset($data['person_id'])) {
            $query->where('job_hours.person_id', $data['person_id']);
        }
        $entries = $query->orderBy('people.last_name')->orderBy('people.first_name')
            ->get(['job_hours.person_id', 'job_hours.work_date', 'job_hours.hours', 'people.first_name', 'people.last_name']);
        $days = collect(range(0, 6))->map(fn ($offset) => $start->addDays($offset));
        $rows = [];
        $total = 0.0;
        foreach ($entries as $entry) {
            $personId = (int) $entry->person_id;
            $rows[$personId] ??= [
                'name' => trim($entry->last_name.', '.$entry->first_name, ', '),
                'days' => [],
                'total' => 0.0,
            ];
            $hours = (float) $entry->hours;
            $rows[$personId]['days'][$entry->work_date] = $hours;
            $rows[$personId]['total'] += $hours;
            $total += $hours;
        }

        return view('jobload.partials.week-detail', compact('start', 'job', 'days', 'rows', 'total', 'hourDecimals'));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->person_id, 403);
        $tenantId = CurrentTenant::id();
        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $hourDecimals = match ($timeGrid) { 60 => 0, 30 => 1, default => 2 };
        $ownPersonId = (int) $user->person_id;
        $canViewAll = $user->can('jobload.overview.view_all');

        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'mode' => ['nullable', Rule::in(['person', 'job'])],
            'person_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'integer'],
        ]);
        $year = (int) ($filters['year'] ?? CarbonImmutable::today()->isoWeekYear());
        $currentWeekKey = sprintf('%04d-W%02d', CarbonImmutable::today()->isoWeekYear(), CarbonImmutable::today()->isoWeek());
        $mode = $filters['mode'] ?? 'person';

        $people = DB::table('people')->where(function ($query) use ($tenantId, $ownPersonId) {
            $query->where('people.tenant_id', $tenantId)
                ->orWhere('people.id', $ownPersonId)
                ->orWhereIn('people.id', DB::table('person_tenant')->select('person_id')->where('tenant_id', $tenantId))
                ->orWhereIn('people.id', DB::table('job_hours')->select('person_id')->where('tenant_id', $tenantId));
        })
            // Ralf, 2026-09-19: Personen ohne Login können keine Stunden buchen -
            // in der Personen-Auswahl sinnlos.
            ->whereIn('people.id', DB::table('users')->select('person_id')->whereNotNull('person_id'))
            ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'active']);
        if (! $canViewAll) {
            $people = $people->where('id', $ownPersonId)->values();
        }
        $peopleById = $people->keyBy('id');
        $personId = (int) ($filters['person_id'] ?? $ownPersonId);
        if (! $canViewAll || ! $peopleById->has($personId)) {
            $personId = $ownPersonId;
        }

        $jobs = DB::table('job_types')
            ->leftJoin('job_groups', function ($join) use ($tenantId) {
                $join->on('job_groups.id', '=', 'job_types.job_group_id')->where('job_groups.tenant_id', '=', $tenantId);
            })
            ->where('job_types.tenant_id', $tenantId)
            ->orderBy('job_groups.sort')->orderBy('job_types.code')->orderBy('job_types.name')
            ->get(['job_types.id', 'job_types.code', 'job_types.name', 'job_groups.name as group_name']);
        $jobsById = $jobs->keyBy('id');
        $jobId = isset($filters['job_id']) && $jobsById->has((int) $filters['job_id'])
            ? (int) $filters['job_id'] : null;

        $start = CarbonImmutable::now()->setISODate($year, 1)->startOfWeek();
        $end = CarbonImmutable::now()->setISODate($year + 1, 1)->startOfWeek();
        $weeks = collect();
        $monthSegments = collect();
        for ($day = $start; $day->lessThan($end); $day = $day->addWeek()) {
            $key = sprintf('%04d-W%02d', $day->isoWeekYear(), $day->isoWeek());
            $month = $day->addDays(3)->format('Y-m');
            $weeks->push(['key' => $key, 'number' => $day->isoWeek(), 'start' => $day, 'end' => $day->addDays(6), 'month' => $month]);
            if ($monthSegments->isNotEmpty() && $monthSegments->last()['key'] === $month) {
                $segment = $monthSegments->pop();
                $segment['count']++;
                $monthSegments->push($segment);
            } else {
                $monthSegments->push(['key' => $month, 'label' => $day->addDays(3)->translatedFormat('F Y'), 'count' => 1]);
            }
        }

        $entries = DB::table('job_hours')->where('tenant_id', $tenantId)
            ->where('work_date', '>=', $start->toDateString())
            ->where('work_date', '<', $end->toDateString())
            ->where('hours', '>', 0);
        if ($mode === 'person' || ! $canViewAll) {
            $entries->where('person_id', $mode === 'person' ? $personId : $ownPersonId);
        }
        if ($mode === 'job' && $jobId !== null) {
            $entries->where('job_type_id', $jobId);
        }

        $jobOrder = $jobs->pluck('id')->flip();
        $rows = [];
        $weekTotals = $weeks->pluck('key')->mapWithKeys(fn ($key) => [$key => 0.0])->all();
        $yearTotal = 0.0;
        foreach ($entries->select('person_id', 'job_type_id', 'work_date', 'hours')->cursor() as $entry) {
            $date = CarbonImmutable::parse($entry->work_date);
            $weekKey = sprintf('%04d-W%02d', $date->isoWeekYear(), $date->isoWeek());
            $rowIsPerson = $mode === 'job' && $jobId !== null;
            $rowId = $rowIsPerson ? (int) $entry->person_id : (int) $entry->job_type_id;
            if (! isset($rows[$rowId])) {
                if ($rowIsPerson) {
                    $person = $peopleById->get($rowId);
                    if (! $person) {
                        continue;
                    }
                    $label = trim($person->last_name.', '.$person->first_name, ', ');
                    $sort = mb_strtolower($label);
                } else {
                    $job = $jobsById->get($rowId);
                    if (! $job) {
                        continue;
                    }
                    $label = ($job->code ? $job->code.' – ' : '').$job->name;
                    $sort = sprintf('%08d', $jobOrder->get($rowId, 99999999));
                }
                $rows[$rowId] = ['id' => $rowId, 'label' => $label, 'sort' => $sort, 'weeks' => [], 'total' => 0.0];
            }
            $hours = (float) $entry->hours;
            $rows[$rowId]['weeks'][$weekKey] = ($rows[$rowId]['weeks'][$weekKey] ?? 0) + $hours;
            $rows[$rowId]['total'] += $hours;
            $weekTotals[$weekKey] += $hours;
            $yearTotal += $hours;
        }
        $rows = collect($rows)->sortBy('sort')->values();

        // Übliche "Inaktive zeigen"-Umschaltung: inaktive Personen standard-
        // mäßig ausblenden, die aktuell gewählte und die eigene Person bleiben
        // aber immer in der Liste (sonst passt die Auswahl nicht zur Anzeige).
        $showInactive = $request->boolean('show_inactive');
        $people = $people->filter(fn ($person) => $showInactive || $person->active
            || (int) $person->id === $personId || (int) $person->id === $ownPersonId)->values();

        return view('jobload.overview', compact(
            'year', 'currentWeekKey', 'mode', 'people', 'showInactive', 'personId', 'jobs', 'jobId', 'canViewAll',
            'weeks', 'monthSegments', 'rows', 'weekTotals', 'yearTotal', 'hourDecimals'
        ));
    }
}
