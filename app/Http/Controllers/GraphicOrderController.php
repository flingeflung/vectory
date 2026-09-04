<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\GraphicOrderStatus;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * Illustrationsaufträge je Projekt - Vietto: grafikerstellung. Eigenständiges,
 * global im Layout deklariertes Modal (siehe layouts/app.blade.php), das
 * seinen Inhalt selbst per fetch() nachlädt - genau wie das Projekt-Overlay
 * selbst. Bewusst NICHT im Projekt-Detail-Overlay verschachtelt: das führte
 * zu Positionierungs-/Stacking-Problemen (Alpine x-teleport nötig) und
 * einer Fehlerquelle beim Speichern, die zwischenzeitlich sogar ein
 * komplett falsches Modal geöffnet hat.
 */
class GraphicOrderController extends Controller
{
    public function index(Project $project): View
    {
        return view('projekte.partials.illustration-orders-body', $this->viewData($project));
    }

    public function store(Request $request, Project $project): View
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

        $graphicOrder = GraphicOrder::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'graphic_order_status_id' => $initialStatus->id,
            'description' => $validated['description'],
            'image_count' => $validated['image_count'] ?? 0,
            'due_date' => $validated['due_date'] ?? null,
            'initiated_by_person_id' => $request->user()->person_id,
        ]);

        Activity::log($project, ActivityType::GraphicOrderStatusChanged, __('Illustrationsauftrag Illu-:id angelegt.', ['id' => $graphicOrder->id]));

        return view('projekte.partials.illustration-orders-body', $this->viewData($project));
    }

    public function update(Request $request, Project $project, GraphicOrder $graphicOrder): View
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

        Activity::log($project, ActivityType::GraphicOrderStatusChanged, __('Status für Illustrationsauftrag Illu-:id: :status', ['id' => $graphicOrder->id, 'status' => $status->name]));

        return view('projekte.partials.illustration-orders-body', $this->viewData($project));
    }

    /**
     * @return array{project: Project, illustrationPersons: \Illuminate\Support\Collection, graphicOrderStatuses: \Illuminate\Support\Collection}
     */
    private function viewData(Project $project): array
    {
        return [
            'project' => $project->fresh()->loadMissing(['graphicOrders.status', 'graphicOrders.initiatedBy', 'graphicOrders.illustrator', 'graphicOrders.completedBy']),
            'illustrationPersons' => FunctionGroup::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('legacy_id', 5)
                ->first()
                ?->members
                ->sortBy(fn (Person $person) => $person->fullName())
                ->values() ?? collect(),
            'graphicOrderStatuses' => GraphicOrderStatus::query()
                ->where('tenant_id', $project->tenant_id)
                ->orderBy('sort')
                ->get(),
        ];
    }
}
