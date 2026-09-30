<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $years = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('YEAR(date) AS year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values();
        $year = $request->has('year') ? $request->integer('year') : (int) now()->year;
        if (! $years->contains($year)) {
            $year = $years->first() ?? (int) now()->year;
        }
        $holidays = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->orderBy('name')
            ->get();

        $user = $request->user();
        $otherTenants = SystemSetting::multiTenantEnabled() && (CurrentTenant::isHomeTenantAdmin($user) || $user->role === 'super_admin')
            ? CurrentTenant::availableTenants()->reject(fn (Tenant $tenant) => $tenant->id === $tenantId)->values()
            : collect();

        return view('admin.holidays.index', compact('years', 'year', 'holidays', 'otherTenants'));
    }

    public function importFromTenant(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $user = $request->user();
        abort_unless(CurrentTenant::isHomeTenantAdmin($user) || $user->role === 'super_admin', 403);

        $targetTenantId = CurrentTenant::id();
        $sourceTenant = CurrentTenant::availableTenants()
            ->reject(fn (Tenant $tenant) => $tenant->id === $targetTenantId)
            ->firstWhere('id', $request->integer('source_tenant_id'));
        abort_if($sourceTenant === null, 422);

        $sourceHolidays = Holiday::withoutGlobalScope('tenant')
            ->where('tenant_id', $sourceTenant->id)
            ->orderBy('date')
            ->orderBy('name')
            ->get();
        $existingKeys = Holiday::query()
            ->where('tenant_id', $targetTenantId)
            ->get(['date', 'name'])
            ->mapWithKeys(fn (Holiday $holiday) => [$this->duplicateKey($holiday->date->format('Y-m-d'), $holiday->name) => true]);
        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($sourceHolidays, $targetTenantId, $existingKeys, &$copied, &$skipped): void {
            foreach ($sourceHolidays as $sourceHoliday) {
                $key = $this->duplicateKey($sourceHoliday->date->format('Y-m-d'), $sourceHoliday->name);
                if ($existingKeys->has($key)) {
                    $skipped++;

                    continue;
                }

                Holiday::query()->create([
                    'tenant_id' => $targetTenantId,
                    'name' => $sourceHoliday->name,
                    'date' => $sourceHoliday->date->format('Y-m-d'),
                    'weekday' => $sourceHoliday->weekday,
                    'remarks' => $sourceHoliday->remarks,
                    'active' => $sourceHoliday->active,
                ]);
                $existingKeys->put($key, true);
                $copied++;
            }
        });

        $year = $request->integer('year', (int) now()->year);

        return redirect()->route('admin.feiertage', ['year' => $year])
            ->with('status', 'holidays-imported')
            ->with('import_source_name', $sourceTenant->name)
            ->with('holidays_copied', $copied)
            ->with('holidays_skipped', $skipped);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        Holiday::query()->create(['tenant_id' => CurrentTenant::id(), ...$data]);

        return $this->redirectToYear((int) substr($data['date'], 0, 4), 'holiday-saved');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->ensureCurrentTenant($holiday);
        $data = $this->validatedData($request);
        $holiday->update($data);

        return $this->redirectToYear((int) substr($data['date'], 0, 4), 'holiday-saved');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->ensureCurrentTenant($holiday);
        $year = (int) $holiday->date->year;
        $holiday->delete();

        return $this->redirectToYear($year, 'holiday-deleted');
    }

    private function validatedData(Request $request): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['remarks'] = isset($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null;
        $data['active'] = $request->boolean('active');
        $data['weekday'] = CarbonImmutable::createFromFormat('Y-m-d', $data['date'])->isoWeekday();

        return $data;
    }

    private function ensureCurrentTenant(Holiday $holiday): void
    {
        abort_unless($holiday->tenant_id === CurrentTenant::id(), 404);
    }

    private function redirectToYear(int $year, string $status): RedirectResponse
    {
        return redirect()->route('admin.feiertage', ['year' => $year])->with('status', $status);
    }

    private function duplicateKey(string $date, string $name): string
    {
        return $date.'|'.mb_strtolower(trim($name));
    }
}
