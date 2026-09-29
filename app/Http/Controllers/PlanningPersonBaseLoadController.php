<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PlanningBaseLoad;
use App\Models\PlanningPersonBaseLoad;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanningPersonBaseLoadController extends Controller
{
    public function inherit(Request $request): RedirectResponse
    {
        $this->authorizePlanning($request);
        [$person, $year] = $this->validatedSelection($request);
        $tenantId = CurrentTenant::id();
        $existingBaseIds = PlanningPersonBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('person_id', $person->id)
            ->where('year', $year)
            ->whereNotNull('planning_base_load_id')
            ->pluck('planning_base_load_id');
        $baseLoads = PlanningBaseLoad::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->whereNotIn('id', $existingBaseIds)
            ->get();

        DB::transaction(function () use ($baseLoads, $person, $tenantId) {
            foreach ($baseLoads as $baseLoad) {
                PlanningPersonBaseLoad::query()->create([
                    'tenant_id' => $tenantId,
                    'person_id' => $person->id,
                    'planning_base_load_id' => $baseLoad->id,
                    'year' => $baseLoad->year,
                    'name' => $baseLoad->name,
                    'calculation_type' => $baseLoad->calculation_type,
                    'value' => $baseLoad->value,
                    'valid_from' => $baseLoad->valid_from,
                    'valid_to' => $baseLoad->valid_to,
                ]);
            }
        });

        return $this->redirectToSelection($year, $person->id, 'person-base-load-inherited');
    }

    public function update(Request $request, PlanningPersonBaseLoad $planningPersonBaseLoad): RedirectResponse
    {
        $this->authorizePlanning($request);
        abort_unless($planningPersonBaseLoad->tenant_id === CurrentTenant::id(), 404);
        [$person, $year] = $this->validatedSelection($request);
        abort_unless($planningPersonBaseLoad->person_id === $person->id && $planningPersonBaseLoad->year === $year, 422);
        $data = $request->validate([
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
        if ((int) substr($data['valid_from'], 0, 4) !== $year || (int) substr($data['valid_to'], 0, 4) !== $year) {
            throw ValidationException::withMessages([
                'valid_from' => __('Der Gültigkeitszeitraum muss vollständig im gewählten Jahr liegen.'),
            ]);
        }
        $planningPersonBaseLoad->update($data);

        return $this->redirectToSelection($year, $person->id, 'person-base-load-saved');
    }

    /** @return array{Person, int} */
    private function validatedSelection(Request $request): array
    {
        $selection = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'person' => ['required', 'integer'],
        ]);
        $year = (int) $selection['year'];
        $person = Person::query()->withoutGlobalScope('tenant')
            ->visibleToRole($request->user()->role)
            ->visibleInTenant(CurrentTenant::id())
            ->whereHas('user')
            ->where('resource_planning', true)
            ->findOrFail((int) $selection['person']);

        return [$person, $year];
    }

    private function authorizePlanning(Request $request): void
    {
        abort_unless($request->user()->can('planning.view'), 403);
    }

    private function redirectToSelection(int $year, int $personId, string $status): RedirectResponse
    {
        return redirect()->route('planung.grundlast-person', ['year' => $year, 'person' => $personId])->with('status', $status);
    }
}
