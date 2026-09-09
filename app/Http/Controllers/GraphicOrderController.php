<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\GraphicOrderStatus;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        abort_unless($request->user()->can('graphic_order.create'), 403);

        $validated = $request->validate([
            'description' => ['required', 'string'],
            'image_count' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $graphicOrder = GraphicOrder::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'graphic_order_status_id' => GraphicOrderStatus::NeuerAuftrag,
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
            'graphic_order_status_id' => ['required', 'integer', Rule::in(array_column(GraphicOrderStatus::cases(), 'value'))],
            'illustrator_person_id' => ['nullable', 'integer', Rule::exists('people', 'id')->where(
                fn ($query) => $query->where('tenant_id', $project->tenant_id)
                    ->orWhereIn('id', DB::table('person_tenant')->where('tenant_id', $project->tenant_id)->pluck('person_id'))
            )],
            'image_count' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $status = GraphicOrderStatus::from((int) $validated['graphic_order_status_id']);

        $update = [
            'graphic_order_status_id' => $status,
            'illustrator_person_id' => $validated['illustrator_person_id'] ?? null,
            'done_at' => ! $status->isOpen() && ! $status->isDiscarded() ? now() : null,
            'completed_by_person_id' => ! $status->isOpen() && ! $status->isDiscarded() ? $request->user()->person_id : null,
        ];

        // Termin/Anzahl Bilder nur änderbar mit eigenem Recht - Feldrechte
        // aus Vietto übernommen (dort: nur Auftraggeber oder Admin).
        if ($request->has('image_count') || $request->has('due_date')) {
            abort_unless($request->user()->can('illustration_order.edit_terms'), 403);
            $update['image_count'] = $validated['image_count'] ?? $graphicOrder->image_count;
            $update['due_date'] = $validated['due_date'] ?? null;
        }

        $graphicOrder->update($update);

        Activity::log($project, ActivityType::GraphicOrderStatusChanged, __('Status für Illustrationsauftrag Illu-:id: :status', ['id' => $graphicOrder->id, 'status' => $status->label()]));

        return view('projekte.partials.illustration-orders-body', $this->viewData($project));
    }

    /**
     * @return array{project: Project, illustrationPersons: \Illuminate\Support\Collection, graphicOrderStatuses: list<GraphicOrderStatus>}
     */
    private function viewData(Project $project): array
    {
        return [
            'project' => $project->fresh()->loadMissing(['graphicOrders.initiatedBy', 'graphicOrders.illustrator', 'graphicOrders.completedBy']),
            'illustrationPersons' => FunctionGroup::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('legacy_id', 5)
                ->with(['members' => fn ($query) => $query->withoutGlobalScope('tenant')
                    ->visibleInTenant($project->tenant_id)
                    ->visibleToRole(auth()->user()->role)])
                ->first()
                ?->members
                ->sortBy(fn (Person $person) => $person->fullName())
                ->values() ?? collect(),
            'graphicOrderStatuses' => GraphicOrderStatus::cases(),
        ];
    }
}
