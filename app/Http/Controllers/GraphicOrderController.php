<?php

namespace App\Http\Controllers;

use App\Models\GraphicOrder;
use App\Models\GraphicOrderStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Illustrationsaufträge je Projekt - Vietto: grafikerstellung. Erreichbar aus
 * dem WFS-Kasten mit js_function "wfs_grafik" im Workflow-Tab.
 */
class GraphicOrderController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string'],
            'image_count' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $initialStatus = GraphicOrderStatus::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('legacy_id', 1)
            ->firstOrFail();

        GraphicOrder::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'graphic_order_status_id' => $initialStatus->id,
            'description' => $validated['description'],
            'image_count' => $validated['image_count'] ?? 0,
            'due_date' => $validated['due_date'] ?? null,
            'initiated_by_person_id' => $request->user()->person_id,
        ]);

        return back();
    }

    public function update(Request $request, Project $project, GraphicOrder $graphicOrder): RedirectResponse
    {
        abort_unless($graphicOrder->project_id === $project->id, 404);

        $validated = $request->validate([
            'graphic_order_status_id' => ['required', 'integer', Rule::exists('graphic_order_statuses', 'id')->where('tenant_id', $project->tenant_id)],
            'illustrator_person_id' => ['nullable', 'integer', Rule::exists('people', 'id')->where('tenant_id', $project->tenant_id)],
        ]);

        $status = GraphicOrderStatus::findOrFail($validated['graphic_order_status_id']);

        $graphicOrder->update([
            'graphic_order_status_id' => $status->id,
            'illustrator_person_id' => $validated['illustrator_person_id'] ?? null,
            'done_at' => $status->is_open === false && ! $status->is_discarded ? now() : null,
            'completed_by_person_id' => $status->is_open === false && ! $status->is_discarded ? $request->user()->person_id : null,
        ]);

        return back();
    }
}
