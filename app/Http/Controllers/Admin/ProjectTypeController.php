<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Verwaltung von Projektkategorien (Vietto: "Projekttyp", Tabelle
 * projartmain) und den darunterliegenden Projektarten (Vietto:
 * "Projektart", projartsub) - anders als z.B. Märkte/Ländergruppen eine
 * echte 1:n-Baumstruktur (jede Art gehört zu genau einer Kategorie), keine
 * Mehrfachzuordnung.
 *
 * Ralf: "Projekttyp"/"Projektart" beibehalten hätte für Neukunden ohne
 * Vietto-Vorwissen kaum erkennbar gemacht, welche Ebene übergeordnet ist -
 * deshalb umbenannt in "Projektkategorie" (oben) / "Projektart" (darunter),
 * das trägt die Hierarchie schon im Namen.
 */
class ProjectTypeController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $categories = ProjectTypeMain::query()->where('tenant_id', $tenantId)->orderBy('sort')->with(['subs'])->get();
        $allSubs = $categories->flatMap->subs;

        $usageBySub = DB::table('projects')->where('tenant_id', $tenantId)->whereNotNull('project_type_sub_id')
            ->select('project_type_sub_id', DB::raw('count(*) as aggregate'))->groupBy('project_type_sub_id')->pluck('aggregate', 'project_type_sub_id');
        $usageByMain = DB::table('projects')->where('tenant_id', $tenantId)->whereNotNull('project_type_main_id')
            ->select('project_type_main_id', DB::raw('count(*) as aggregate'))->groupBy('project_type_main_id')->pluck('aggregate', 'project_type_main_id');

        $selectedCategory = null;

        if ($request->filled('kategorie')) {
            $selectedCategory = $categories->firstWhere('id', (int) $request->query('kategorie'));
        }

        return view('admin.projektkategorien.index', [
            'categories' => $categories,
            'allSubs' => $allSubs,
            'usageBySub' => $usageBySub,
            'usageByMain' => $usageByMain,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function reorderMain(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('categories'))->values()->each(function (string $id, int $index) use ($tenantId) {
            ProjectTypeMain::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.projektkategorien');
    }

    public function reorderSub(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('subs'))->values()->each(function (string $id, int $index) use ($tenantId) {
            ProjectTypeSub::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.projektkategorien');
    }

    public function mainStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $nextSort = 1 + (int) ProjectTypeMain::query()->where('tenant_id', $tenantId)->max('sort');
        $category = ProjectTypeMain::query()->create(['tenant_id' => $tenantId, 'name' => $name, 'active' => true, 'sort' => $nextSort]);

        return redirect()->route('admin.projektkategorien', ['kategorie' => $category->id])->with('status', 'projektkategorien-updated');
    }

    public function mainUpdate(Request $request, ProjectTypeMain $category): RedirectResponse
    {
        abort_unless($category->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $category->update(['name' => $name, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.projektkategorien', ['kategorie' => $category->id])->with('status', 'projektkategorien-updated');
    }

    /**
     * Löschen ist immer möglich, sofern keine Arten mehr darunter hängen
     * (die müssten erst verschoben/gelöscht werden - eine reine
     * Baumstruktur-Regel, kein Verwendungs-Schutz). Projekte, die noch
     * direkt auf diese Kategorie zeigen, werden dabei optional auf eine
     * andere Kategorie umgehängt (reassign_to) oder auf "nicht zugewiesen"
     * gesetzt - gleiches Muster wie beim Löschen einer Firma/Abteilung.
     */
    public function mainDestroy(Request $request, ProjectTypeMain $category): RedirectResponse
    {
        abort_unless($category->tenant_id === CurrentTenant::id(), 404);
        abort_if($category->subs()->exists(), 422, 'Diese Kategorie hat noch zugeordnete Arten und kann nicht gelöscht werden.');

        $reassignTo = null;
        if ($request->filled('reassign_to')) {
            $reassignTo = ProjectTypeMain::query()->where('id', $request->integer('reassign_to'))->where('tenant_id', $category->tenant_id)->firstOrFail();
        }

        DB::table('projects')->where('tenant_id', $category->tenant_id)->where('project_type_main_id', $category->id)
            ->update(['project_type_main_id' => $reassignTo?->id]);

        $category->delete();

        return redirect()->route('admin.projektkategorien')->with('status', 'projektkategorien-updated');
    }

    public function subStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validatedSub($request, $tenantId);

        $nextSort = 1 + (int) ProjectTypeSub::query()->where('tenant_id', $tenantId)->where('project_type_main_id', $validated['project_type_main_id'])->max('sort');
        $sub = ProjectTypeSub::query()->create([
            'tenant_id' => $tenantId,
            'project_type_main_id' => $validated['project_type_main_id'],
            'name' => $validated['name'],
            'active' => true,
            'sort' => $nextSort,
        ]);

        return redirect()->route('admin.projektkategorien', ['kategorie' => $sub->project_type_main_id])->with('status', 'projektkategorien-updated');
    }

    public function subUpdate(Request $request, ProjectTypeSub $sub): RedirectResponse
    {
        abort_unless($sub->tenant_id === CurrentTenant::id(), 404);

        $validated = $this->validatedSub($request, $sub->tenant_id);
        $sub->update([...$validated, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.projektkategorien', ['kategorie' => $sub->project_type_main_id])->with('status', 'projektkategorien-updated');
    }

    /**
     * Löschen ist immer möglich - Projekte, die noch auf diese Art zeigen,
     * werden dabei optional auf eine andere Art umgehängt (reassign_to)
     * oder auf "nicht zugewiesen" gesetzt (gleiches Muster wie beim
     * Löschen einer Firma/Abteilung mit zugeordneten Personen).
     */
    public function subDestroy(Request $request, ProjectTypeSub $sub): RedirectResponse
    {
        abort_unless($sub->tenant_id === CurrentTenant::id(), 404);

        $reassignTo = null;
        if ($request->filled('reassign_to')) {
            $reassignTo = ProjectTypeSub::query()->where('id', $request->integer('reassign_to'))->where('tenant_id', $sub->tenant_id)->firstOrFail();
        }

        DB::table('projects')->where('tenant_id', $sub->tenant_id)->where('project_type_sub_id', $sub->id)
            ->update(['project_type_sub_id' => $reassignTo?->id]);

        $categoryId = $sub->project_type_main_id;
        $sub->delete();

        return redirect()->route('admin.projektkategorien', ['kategorie' => $categoryId])->with('status', 'projektkategorien-updated');
    }

    /**
     * @return array{project_type_main_id: int, name: string}
     */
    private function validatedSub(Request $request, int $tenantId): array
    {
        $validated = $request->validate([
            'project_type_main_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $categoryBelongsToTenant = ProjectTypeMain::query()->where('id', $validated['project_type_main_id'])->where('tenant_id', $tenantId)->exists();
        abort_unless($categoryBelongsToTenant, 404);

        return $validated;
    }
}
