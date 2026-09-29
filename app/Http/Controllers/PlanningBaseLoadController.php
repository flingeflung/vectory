<?php

namespace App\Http\Controllers;

use App\Models\PlanningBaseLoad;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanningBaseLoadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlanning($request);
        $data = $this->validatedData($request);

        PlanningBaseLoad::query()->create(['tenant_id' => CurrentTenant::id(), ...$data]);

        return $this->redirectToYear($data['year'], 'base-load-saved');
    }

    public function update(Request $request, PlanningBaseLoad $planningBaseLoad): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureCurrentTenant($planningBaseLoad);
        $data = $this->validatedData($request);
        abort_unless($planningBaseLoad->year === $data['year'], 422);
        $planningBaseLoad->update($data);

        return $this->redirectToYear($data['year'], 'base-load-saved');
    }

    public function destroy(Request $request, PlanningBaseLoad $planningBaseLoad): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureCurrentTenant($planningBaseLoad);
        $year = $planningBaseLoad->year;
        $planningBaseLoad->delete();

        return $this->redirectToYear($year, 'base-load-deleted');
    }

    public function copyPreviousYear(Request $request): RedirectResponse
    {
        $this->authorizePlanning($request);
        $year = $request->validate(['year' => ['required', 'integer', 'min:1900', 'max:2100']])['year'];
        $this->ensureYearIsAllowed((int) $year);
        $tenantId = CurrentTenant::id();
        $sourceRows = PlanningBaseLoad::query()->where('tenant_id', $tenantId)->where('year', $year - 1)->get();

        DB::transaction(function () use ($sourceRows, $tenantId, $year) {
            foreach ($sourceRows as $source) {
                PlanningBaseLoad::query()->create([
                    'tenant_id' => $tenantId,
                    'year' => $year,
                    'name' => $source->name,
                    'calculation_type' => $source->calculation_type,
                    'value' => $source->value,
                    'valid_from' => $this->moveDateToYear($source->valid_from, $year),
                    'valid_to' => $this->moveDateToYear($source->valid_to, $year),
                ]);
            }
        });

        return $this->redirectToYear($year, 'base-load-copied');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'name' => ['required', 'string', 'max:255'],
            'calculation_type' => ['required', Rule::in(['weekly', 'yearly'])],
            'value' => ['required', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) {
                if (abs(((float) $value * 4) - round((float) $value * 4)) > 0.00001) {
                    $fail(__('Der Wert muss in 0,25-Stunden-Schritten eingegeben werden.'));
                }
            }],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ]);

        $year = (int) $data['year'];
        $this->ensureYearIsAllowed($year);
        if ((int) substr($data['valid_from'], 0, 4) !== $year || (int) substr($data['valid_to'], 0, 4) !== $year) {
            throw ValidationException::withMessages([
                'valid_from' => __('Der Gültigkeitszeitraum muss vollständig im gewählten Jahr liegen.'),
            ]);
        }

        return $data;
    }

    private function ensureYearIsAllowed(int $year): void
    {
        $currentYear = (int) now()->year;
        $isPlanningYear = $year >= $currentYear && $year <= $currentYear + 2;
        $hasExistingBaseLoad = PlanningBaseLoad::query()
            ->where('tenant_id', CurrentTenant::id())
            ->where('year', $year)
            ->exists();

        if (! $isPlanningYear && ! $hasExistingBaseLoad) {
            throw ValidationException::withMessages([
                'year' => __('Das gewählte Jahr ist für die Grundlast nicht verfügbar.'),
            ]);
        }
    }

    private function moveDateToYear($date, int $year): CarbonImmutable
    {
        $month = (int) $date->month;
        $day = min((int) $date->day, CarbonImmutable::create($year, $month, 1)->daysInMonth);

        return CarbonImmutable::create($year, $month, $day);
    }

    private function authorizePlanning(Request $request): void
    {
        abort_unless($request->user()->can('planning.view'), 403);
    }

    private function ensureCurrentTenant(PlanningBaseLoad $baseLoad): void
    {
        abort_unless($baseLoad->tenant_id === CurrentTenant::id(), 404);
    }

    private function redirectToYear(int $year, string $status): RedirectResponse
    {
        return redirect()->route('planung.grundlast', ['year' => $year])->with('status', $status);
    }
}
