<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $year = $request->integer('year');
        if (! $years->contains($year)) {
            $year = $years->first() ?? (int) now()->year;
        }
        $holidays = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->orderBy('name')
            ->get();

        return view('admin.holidays.index', compact('years', 'year', 'holidays'));
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
}
