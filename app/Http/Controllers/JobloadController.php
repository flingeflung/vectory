<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JobloadController extends Controller
{
    public function index(Request $request): View
    {
        [$tenantId, $personId] = $this->identity($request);
        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $hourDecimals = match ($timeGrid) { 60 => 0, 30 => 1, default => 2 };
        $stepHundredths = match ($timeGrid) { 60 => 100, 30 => 50, default => 25 };
        $showWeekends = (bool) DB::table('people')->where('id', $personId)
            ->where('tenant_id', $request->user()->tenant_id)->value('jobload_show_weekends');
        $week = $this->week($request->query('week'));
        $days = collect(range(0, 6))->map(fn ($offset) => $week->addDays($offset));
        $jobs = DB::table('job_types')
            ->join('person_job_types', 'person_job_types.job_type_id', '=', 'job_types.id')
            ->leftJoin('job_groups', function ($join) use ($tenantId) {
                $join->on('job_groups.id', '=', 'job_types.job_group_id')->where('job_groups.tenant_id', '=', $tenantId);
            })
            ->where('job_types.tenant_id', $tenantId)
            ->where('person_job_types.tenant_id', $tenantId)
            ->where('person_job_types.person_id', $personId)
            ->orderBy('job_groups.sort')->orderBy('job_groups.name')->orderBy('job_types.code')->orderBy('job_types.name')
            ->select('job_types.id', 'job_types.code', 'job_types.name', 'job_groups.name as group_name')
            ->get();
        $availableJobs = DB::table('job_types')->where('job_types.tenant_id', $tenantId)
            ->leftJoin('job_groups', function ($join) use ($tenantId) {
                $join->on('job_groups.id', '=', 'job_types.job_group_id')->where('job_groups.tenant_id', '=', $tenantId);
            })
            ->where('job_types.active', true)->orderBy('job_groups.sort')->orderBy('job_groups.name')->orderBy('job_types.code')->orderBy('job_types.name')
            ->get(['job_types.id', 'job_types.code', 'job_types.name', 'job_groups.name as group_name']);
        $hours = DB::table('job_hours')->where('tenant_id', $tenantId)
            ->where('person_id', $personId)->whereBetween('work_date', [$week->toDateString(), $week->addDays(6)->toDateString()])
            ->get()->groupBy('job_type_id')->map(fn ($entries) => $entries->pluck('hours', 'work_date'));

        return view('jobload.index', compact('week', 'days', 'jobs', 'availableJobs', 'hours', 'showWeekends', 'hourDecimals', 'timeGrid', 'stepHundredths'));
    }

    public function saveWeekendPreference(Request $request): Response
    {
        [, $personId] = $this->identity($request);
        $data = $request->validate(['show_weekends' => ['required', 'boolean']]);
        DB::table('people')->where('id', $personId)->where('tenant_id', $request->user()->tenant_id)
            ->update(['jobload_show_weekends' => $data['show_weekends']]);

        return response()->noContent();
    }

    public function saveHours(Request $request): RedirectResponse
    {
        [$tenantId, $personId] = $this->identity($request);
        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $stepHundredths = match ($timeGrid) { 60 => 100, 30 => 50, default => 25 };
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'hours' => ['array'],
            'hours.*' => ['array'],
            'hours.*.*' => ['nullable', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
        ]);
        $week = $this->week($data['week']);
        $allowedDates = collect(range(0, 6))->map(fn ($day) => $week->addDays($day)->toDateString())->all();
        $jobIds = DB::table('person_job_types')->where('tenant_id', $tenantId)
            ->where('person_id', $personId)->pluck('job_type_id')->map(fn ($id) => (string) $id)->all();
        $existingHours = DB::table('job_hours')->where('tenant_id', $tenantId)->where('person_id', $personId)
            ->whereBetween('work_date', [$week->toDateString(), $week->addDays(6)->toDateString()])
            ->get(['job_type_id', 'work_date', 'hours'])
            ->keyBy(fn ($row) => $row->job_type_id.'|'.$row->work_date);
        $entries = [];
        foreach ($data['hours'] ?? [] as $jobId => $dates) {
            if (! in_array((string) $jobId, $jobIds, true)) {
                throw ValidationException::withMessages(['hours' => __('Ungültiger Job.')]);
            }
            foreach ($dates as $date => $value) {
                if (! in_array($date, $allowedDates, true)) {
                    throw ValidationException::withMessages(['hours' => __('Ungültiges Datum.')]);
                }
                $previous = $existingHours->get($jobId.'|'.$date)?->hours;
                $unchanged = $previous !== null && (int) round((float) $previous * 100) === (int) round((float) $value * 100);
                if ($value !== null && $value !== '' && ! $unchanged && (int) round((float) $value * 100) % $stepHundredths !== 0) {
                    throw ValidationException::withMessages([
                        'hours' => __('Stunden müssen dem Zeitraster entsprechen (:step Stunden).', [
                            'step' => number_format($timeGrid / 60, 2, ',', ''),
                        ]),
                    ]);
                }
                if ($value !== null && $value !== '' && (float) $value > 0) {
                    $entries[] = [
                        'tenant_id' => $tenantId, 'person_id' => $personId, 'job_type_id' => $jobId,
                        'work_date' => $date, 'hours' => $value, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }
        DB::transaction(function () use ($tenantId, $personId, $week, $entries, $jobIds) {
            DB::table('job_hours')->where('tenant_id', $tenantId)->where('person_id', $personId)
                ->whereIn('job_type_id', $jobIds)
                ->whereBetween('work_date', [$week->toDateString(), $week->addDays(6)->toDateString()])->delete();
            if ($entries) {
                DB::table('job_hours')->insert($entries);
            }
        });

        return redirect()->route('jobload', ['week' => $data['week']])->with('status', __('Stunden gespeichert.'));
    }

    public function saveJobs(Request $request): RedirectResponse
    {
        [$tenantId, $personId] = $this->identity($request);
        $data = $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'jobs' => ['array'],
            'jobs.*' => ['integer', 'distinct'],
        ]);
        $this->week($data['week']);
        $selected = array_map('intval', $data['jobs'] ?? []);
        $valid = DB::table('job_types')->where('tenant_id', $tenantId)->where('active', true)
            ->whereIn('id', $selected)->count();
        if ($valid !== count($selected)) {
            throw ValidationException::withMessages(['jobs' => __('Ungültiger Job.')]);
        }
        DB::transaction(function () use ($tenantId, $personId, $selected) {
            DB::table('person_job_types')->where('tenant_id', $tenantId)->where('person_id', $personId)
                ->whereNotIn('job_type_id', $selected)->delete();
            foreach ($selected as $jobId) {
                DB::table('person_job_types')->updateOrInsert(
                    ['person_id' => $personId, 'job_type_id' => $jobId],
                    ['tenant_id' => $tenantId]
                );
            }
        });

        return redirect()->route('jobload', ['week' => $data['week']])->with('status', __('Jobs angepasst.'));
    }

    private function identity(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->person_id && $user->person?->tenant_id === $user->tenant_id, 403);

        return [CurrentTenant::id(), $user->person_id];
    }

    private function week(?string $value): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::today()->startOfWeek();
        }
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches)) {
            throw ValidationException::withMessages(['week' => __('Ungültige Kalenderwoche.')]);
        }
        $week = CarbonImmutable::now()->setISODate((int) $matches[1], (int) $matches[2])->startOfWeek();
        if ($week->isoWeekYear() !== (int) $matches[1] || $week->isoWeek() !== (int) $matches[2]) {
            throw ValidationException::withMessages(['week' => __('Ungültige Kalenderwoche.')]);
        }

        return $week;
    }
}
