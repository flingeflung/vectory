<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\ChecklistPoint;
use App\Models\ChecklistSection;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Checklisten-Verwaltung (Ralf, 2026-09-12, nach Vietto-Analyse: dort gibt
 * es Checklisten technisch, aber KEINE Verwaltungs-UI - Vorlagen
 * (checklist_gruppen/_subtitle/_list) wurden nur direkt in der DB
 * gepflegt). Struktur/Muster analog zu WorkflowController, aber bewusst
 * ohne dessen Publish/Versions-Sperre - für Checklisten hat Ralf sowas
 * nicht verlangt, Vietto kannte das Konzept dort auch nicht.
 */
class ChecklistController extends Controller
{
    public function index(Request $request): View|Response
    {
        $data = $this->buildIndexData($request, $request->filled('checklist') ? (int) $request->query('checklist') : null);

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.checklists.partials.content', $data);
        }

        return view('admin.checklists.index', $data);
    }

    /**
     * @return array{checklists: \Illuminate\Support\Collection, selectedChecklist: ?Checklist, otherTenants: \Illuminate\Support\Collection}
     */
    private function buildIndexData(Request $request, ?int $checklistId): array
    {
        $tenantId = CurrentTenant::id();

        $checklists = Checklist::query()->where('tenant_id', $tenantId)->orderBy('sort')
            ->with('sections.points')
            ->get();

        $selectedChecklist = $checklistId ? $checklists->firstWhere('id', $checklistId) : null;

        $otherTenants = SystemSetting::multiTenantEnabled()
            ? Tenant::query()->where('id', '!=', $tenantId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return [
            'checklists' => $checklists,
            'selectedChecklist' => $selectedChecklist,
            'otherTenants' => $otherTenants,
        ];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $nextSort = 1 + (int) Checklist::query()->where('tenant_id', $tenantId)->max('sort');
        $checklist = Checklist::query()->create(['tenant_id' => $tenantId, 'name' => $name, 'active' => true, 'sort' => $nextSort]);

        return redirect()->route('admin.checklisten', ['checklist' => $checklist->id])->with('status', 'checklists-updated');
    }

    public function update(Request $request, Checklist $checklist): RedirectResponse
    {
        abort_unless($checklist->tenant_id === CurrentTenant::id(), 404);

        if ($request->has('name')) {
            $name = trim((string) $request->string('name'));
            abort_if($name === '', 422);
            $checklist->update(['name' => $name]);
        }

        $checklist->update(['active' => $request->boolean('active')]);

        return redirect()->route('admin.checklisten', ['checklist' => $checklist->id])->with('status', 'checklists-updated');
    }

    /**
     * Löschen ist immer möglich (anders als Workflows kein "publiziert"-
     * Zustand) - bestehende Zuordnungen/Abhak-Stände bei Projekten hängen
     * per cascadeOnDelete direkt dran und würden mitgelöscht. Bewusst KEINE
     * Warnung/Sperre bei bereits genutzten Checklisten - Ralf hat das nicht
     * verlangt, und anders als bei Workflows (Projektablauf-kritisch) ist
     * eine Checkliste rein informativ, ihr Verlust reißt nichts um.
     */
    public function destroy(Checklist $checklist): RedirectResponse
    {
        abort_unless($checklist->tenant_id === CurrentTenant::id(), 404);

        $checklist->delete();

        return redirect()->route('admin.checklisten')->with('status', 'checklists-updated');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('checklists'))->values()->each(function (string $id, int $index) use ($tenantId) {
            Checklist::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.checklisten');
    }

    public function sectionStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $checklist = Checklist::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('checklist_id'));

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);

        $nextSort = 1 + (int) ChecklistSection::query()->where('checklist_id', $checklist->id)->max('sort');
        ChecklistSection::query()->create(['checklist_id' => $checklist->id, 'title' => $title, 'sort' => $nextSort]);

        return redirect()->route('admin.checklisten', ['checklist' => $checklist->id])->with('status', 'checklists-updated');
    }

    public function sectionUpdate(Request $request, ChecklistSection $section): RedirectResponse
    {
        abort_unless($section->checklist->tenant_id === CurrentTenant::id(), 404);

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);
        $section->update(['title' => $title]);

        return redirect()->route('admin.checklisten', ['checklist' => $section->checklist_id])->with('status', 'checklists-updated');
    }

    public function sectionDestroy(ChecklistSection $section): RedirectResponse
    {
        abort_unless($section->checklist->tenant_id === CurrentTenant::id(), 404);

        $checklistId = $section->checklist_id;
        $section->delete();

        return redirect()->route('admin.checklisten', ['checklist' => $checklistId])->with('status', 'checklists-updated');
    }

    public function sectionReorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $checklist = Checklist::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('checklist_id'));

        collect($request->array('sections'))->values()->each(function (string $id, int $index) use ($checklist) {
            ChecklistSection::query()->where('checklist_id', $checklist->id)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.checklisten', ['checklist' => $checklist->id]);
    }

    public function pointStore(Request $request): RedirectResponse
    {
        $section = ChecklistSection::query()->findOrFail($request->integer('checklist_section_id'));
        abort_unless($section->checklist->tenant_id === CurrentTenant::id(), 404);

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);

        $nextSort = 1 + (int) ChecklistPoint::query()->where('checklist_section_id', $section->id)->max('sort');
        ChecklistPoint::query()->create(['checklist_section_id' => $section->id, 'title' => $title, 'sort' => $nextSort]);

        return redirect()->route('admin.checklisten', ['checklist' => $section->checklist_id])->with('status', 'checklists-updated');
    }

    public function pointUpdate(Request $request, ChecklistPoint $point): RedirectResponse
    {
        abort_unless($point->section->checklist->tenant_id === CurrentTenant::id(), 404);

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);
        $point->update(['title' => $title]);

        return redirect()->route('admin.checklisten', ['checklist' => $point->section->checklist_id])->with('status', 'checklists-updated');
    }

    public function pointDestroy(ChecklistPoint $point): RedirectResponse
    {
        abort_unless($point->section->checklist->tenant_id === CurrentTenant::id(), 404);

        $checklistId = $point->section->checklist_id;
        $point->delete();

        return redirect()->route('admin.checklisten', ['checklist' => $checklistId])->with('status', 'checklists-updated');
    }

    public function pointReorder(Request $request): RedirectResponse
    {
        $section = ChecklistSection::query()->findOrFail($request->integer('checklist_section_id'));
        abort_unless($section->checklist->tenant_id === CurrentTenant::id(), 404);

        collect($request->array('points'))->values()->each(function (string $id, int $index) use ($section) {
            ChecklistPoint::query()->where('checklist_section_id', $section->id)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.checklisten', ['checklist' => $section->checklist_id]);
    }

    /**
     * "Zu anderem Kunden kopieren" (Ralf, 2026-09-12: "Möglichkeit,
     * Checklisten vom Kunden zum Kunden zu kopieren. Kopie innerhalb eines
     * Kunden macht keinen Sinn.") - gleiches Muster wie
     * WorkflowController::copyToTenant(), anders als dort aber inklusive
     * ALLER Inhalte (Abschnitte+Punkte haben keine kundenspezifische
     * Fremdreferenz wie Funktionsgruppen bei Workflow-Schritten, die man
     * separat neu zuordnen müsste - eine Checkliste ist in sich
     * abgeschlossen).
     */
    public function copyToTenant(Request $request, Checklist $checklist): RedirectResponse
    {
        abort_unless($checklist->tenant_id === CurrentTenant::id(), 404);
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $targetTenant = Tenant::query()
            ->where('id', '!=', $checklist->tenant_id)
            ->findOrFail($request->integer('target_tenant_id'));

        DB::transaction(function () use ($checklist, $targetTenant) {
            $nextSort = 1 + (int) Checklist::withoutGlobalScope('tenant')->where('tenant_id', $targetTenant->id)->max('sort');
            $newChecklist = Checklist::query()->create([
                'tenant_id' => $targetTenant->id,
                'name' => $this->uniqueChecklistName($checklist->name, $targetTenant->id),
                'active' => false,
                'sort' => $nextSort,
            ]);

            $checklist->sections->each(function (ChecklistSection $section) use ($newChecklist) {
                $newSection = ChecklistSection::query()->create([
                    'checklist_id' => $newChecklist->id,
                    'title' => $section->title,
                    'sort' => $section->sort,
                ]);

                $section->points->each(fn (ChecklistPoint $point) => ChecklistPoint::query()->create([
                    'checklist_section_id' => $newSection->id,
                    'title' => $point->title,
                    'sort' => $point->sort,
                ]));
            });
        });

        return redirect()->route('admin.checklisten', ['checklist' => $checklist->id])->with('status', 'checklist-copied-to-tenant');
    }

    /**
     * Gleiches Windows-Schema wie bei Workflows: "Name" -> "Name (1)" -> ...
     */
    private function uniqueChecklistName(string $name, int $tenantId): string
    {
        if (! Checklist::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('name', $name)->exists()) {
            return $name;
        }

        $counter = 1;
        while (Checklist::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('name', "{$name} ({$counter})")->exists()) {
            $counter++;
        }

        return "{$name} ({$counter})";
    }
}
