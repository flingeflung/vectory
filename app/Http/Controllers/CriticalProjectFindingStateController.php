<?php

namespace App\Http\Controllers;

use App\Models\CriticalProjectFinding;
use App\Models\CriticalProjectFindingState;
use App\Models\SystemSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $validated = $request->validate([
            'action' => ['required', Rule::in(['acknowledge', 'hide', 'restore', 'unacknowledge'])],
            'hidden_until' => ['nullable', 'required_if:action,hide', 'date', 'after_or_equal:today'],
        ]);

        $state = CriticalProjectFindingState::query()->firstOrNew([
            'critical_project_finding_id' => $criticalProjectFinding->id,
            'user_id' => $request->user()->id,
        ]);

        match ($validated['action']) {
            'acknowledge' => $state->forceFill(['acknowledged_at' => now()])->save(),
            'hide' => $state->forceFill(['hidden_until' => $validated['hidden_until']])->save(),
            'restore' => $this->restore($state),
            'unacknowledge' => $this->unacknowledge($state),
        };

        return back()->with('status', 'critical-finding-state-updated');
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
