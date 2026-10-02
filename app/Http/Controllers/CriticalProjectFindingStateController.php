<?php

namespace App\Http\Controllers;

use App\Models\CriticalProjectFinding;
use App\Models\CriticalProjectFindingState;
use App\Models\SystemSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CriticalProjectFindingStateController extends Controller
{
    public function update(Request $request, CriticalProjectFinding $criticalProjectFinding): RedirectResponse
    {
        abort_unless($request->user()->can('project.view'), 403);
        $allowedTenantIds = SystemSetting::multiTenantEnabled()
            ? CurrentTenant::availableTenants()->pluck('id')
            : collect([CurrentTenant::id()]);
        $criticalProjectFinding->load('project');
        abort_unless($criticalProjectFinding->project
            && $allowedTenantIds->map(fn ($id) => (int) $id)->contains((int) $criticalProjectFinding->project->tenant_id), 403);

        $validated = $this->validateAction($request);

        $state = CriticalProjectFindingState::query()->firstOrNew([
            'critical_project_finding_id' => $criticalProjectFinding->id,
            'user_id' => $request->user()->id,
        ]);

        $this->apply($state, $validated);

        return back()
            ->with('status', 'critical-finding-state-updated')
            ->with('open_critical_project_modal', $criticalProjectFinding->project_id);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('project.view'), 403);
        $validated = $this->validateAction($request, true);
        $findings = CriticalProjectFinding::query()
            ->whereIn('id', $validated['finding_ids'])
            ->with('project')
            ->get();

        abort_unless($findings->count() === count($validated['finding_ids'])
            && $findings->pluck('project_id')->unique()->count() === 1, 404);
        $this->authorizeFindings($request, $findings);

        DB::transaction(function () use ($findings, $request, $validated) {
            foreach ($findings as $finding) {
                $state = CriticalProjectFindingState::query()->firstOrNew([
                    'critical_project_finding_id' => $finding->id,
                    'user_id' => $request->user()->id,
                ]);
                $this->apply($state, $validated);
            }
        });

        return back()
            ->with('status', 'critical-finding-state-updated')
            ->with('open_critical_project_modal', $findings->first()->project_id);
    }

    /** @return array<string, mixed> */
    private function validateAction(Request $request, bool $bulk = false): array
    {
        $actions = $bulk ? ['hide'] : ['hide', 'restore'];
        if (SystemSetting::criticalProjectAcknowledgementEnabled()) {
            $actions = [...$actions, ...($bulk ? ['acknowledge'] : ['acknowledge', 'unacknowledge'])];
        }

        $rules = [
            'action' => ['required', Rule::in($actions)],
            'hidden_until' => ['nullable', 'required_if:action,hide', 'date', 'after_or_equal:today'],
        ];
        if ($bulk) {
            $rules['finding_ids'] = ['required', 'array', 'min:1'];
            $rules['finding_ids.*'] = ['integer', 'distinct'];
        }

        return $request->validate($rules);
    }

    /** @param Collection<int, CriticalProjectFinding> $findings */
    private function authorizeFindings(Request $request, Collection $findings): void
    {
        $allowedTenantIds = SystemSetting::multiTenantEnabled()
            ? CurrentTenant::availableTenants()->pluck('id')
            : collect([CurrentTenant::id()]);
        $allowedTenantIds = $allowedTenantIds->map(fn ($id) => (int) $id);

        abort_unless($findings->every(fn ($finding) => $finding->project
            && $allowedTenantIds->contains((int) $finding->project->tenant_id)), 403);
    }

    /** @param array<string, mixed> $validated */
    private function apply(CriticalProjectFindingState $state, array $validated): void
    {
        match ($validated['action']) {
            'acknowledge' => $state->forceFill(['acknowledged_at' => now()])->save(),
            'hide' => $state->forceFill(['hidden_until' => $validated['hidden_until']])->save(),
            'restore' => $this->restore($state),
            'unacknowledge' => $this->unacknowledge($state),
        };
    }

    private function restore(CriticalProjectFindingState $state): void
    {
        if (! $state->exists) {
            return;
        }

        $state->hidden_until = null;
        $state->acknowledged_at ? $state->save() : $state->delete();
    }

    private function unacknowledge(CriticalProjectFindingState $state): void
    {
        if (! $state->exists) {
            return;
        }

        $state->acknowledged_at = null;
        $state->hidden_until ? $state->save() : $state->delete();
    }
}
