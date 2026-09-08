<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung von Workflows und ihren Schritten (Vietto: workflows2/
 * workflowsteps2 - bewusst nur das "neue" System ab 2018, siehe
 * workflows-Migration). Zwei Besonderheiten gegenüber Projektkategorien:
 *
 * 1. Projekt-Zuweisung bleibt ein Verweis, keine Kopie (project_workflow_
 *    steps referenziert weiterhin live workflow_steps für Titel/Text/
 *    Funktionsgruppe) - siehe Ralf-Diskussion, Redaktionssysteme-Vergleich.
 * 2. Ein Workflow ist inhaltlich EINGEFROREN, sobald er bewusst über
 *    publish() veröffentlicht wurde (Workflow::isPublished(), explizites
 *    published_at-Flag) - keine Bearbeitung mehr, nur noch "Neue Version
 *    erstellen" (Kopie + supersededBy) oder Aktiv/Inaktiv.
 *
 *    WICHTIG, Ralfs konkreter Bug-Report: das Sperren war ZUERST an die
 *    reine Nutzung durch ein Projekt gekoppelt (isPublished() = "irgendein
 *    Projekt hat schon zugewiesen") - das sperrte einen Workflow aber
 *    schon beim ersten TEST-Zuweisen, genau während man ihn noch
 *    ausprobiert/korrigiert. Jetzt bleibt ein Entwurf beliebig oft
 *    testweise einem Projekt zuweisbar UND frei bearbeitbar (auch die
 *    Test-Zuweisung bleibt live verknüpft, siehe Punkt 1 - Änderungen
 *    wirken sich sofort auf das Test-Projekt aus, genau das will man beim
 *    Testen), bis man ihn bewusst veröffentlicht.
 */
class WorkflowController extends Controller
{
    public function index(Request $request): View|Response
    {
        $tenantId = CurrentTenant::id();

        $workflows = Workflow::query()->where('tenant_id', $tenantId)->orderBy('sort')->withCount('steps')->with('supersededBy')->get();

        $selectedWorkflow = null;
        if ($request->filled('workflow')) {
            $selectedWorkflow = $workflows->firstWhere('id', (int) $request->query('workflow'));
        }

        $steps = collect();
        $isPublished = false;
        if ($selectedWorkflow) {
            $steps = WorkflowStep::query()->where('workflow_id', $selectedWorkflow->id)->orderBy('sort')->with('functionGroups')->get();
            $isPublished = $selectedWorkflow->isPublished();
        }

        // Alphabetisch statt nach sort - Funktionsgruppen werden überall
        // alphabetisch gelistet (siehe FunctionGroupController), sort ist
        // dort bewusst kein Sortierkriterium (kein D&D für diese Liste).
        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get(['id', 'name']);

        $data = [
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
            'steps' => $steps,
            'isPublished' => $isPublished,
            'functionGroups' => $functionGroups,
            'specialButtons' => WorkflowStep::SPECIAL_BUTTONS,
            'lifecycleColors' => WorkflowStep::LIFECYCLE_COLORS,
        ];

        // Gleiches Muster wie Projektkategorien: Umbenennen/Schritt-Speichern
        // laufen per fetch() + reloadManageListPreservingEdits() statt
        // vollem Seiten-Reload, der Reload holt sich hierüber nur das
        // Inhalts-Partial (X-Overlay-Header).
        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.workflows.partials.content', $data);
        }

        return view('admin.workflows.index', $data);
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('workflows'))->values()->each(function (string $id, int $index) use ($tenantId) {
            Workflow::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.workflows');
    }

    public function stepReorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $workflowId = $request->integer('workflow_id');

        $workflow = Workflow::query()->where('tenant_id', $tenantId)->findOrFail($workflowId);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        collect($request->array('steps'))->values()->each(function (string $id, int $index) use ($tenantId) {
            WorkflowStep::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.workflows', ['workflow' => $workflowId]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validatedWorkflow($request);

        $nextSort = 1 + (int) Workflow::query()->where('tenant_id', $tenantId)->max('sort');
        $workflow = Workflow::query()->create([...$validated, 'tenant_id' => $tenantId, 'active' => true, 'sort' => $nextSort]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);

        // Ein "nur Aktiv/Inaktiv umschalten"-Aufruf (kein anderes Feld im
        // Request) bleibt auch bei publizierten Workflows erlaubt - das
        // ändert nichts am Inhalt, nur an der Sichtbarkeit für neue
        // Projekte. Inhaltliche Änderungen (Name etc.) sind ab Publikation
        // gesperrt, siehe Klassen-Docblock.
        if ($request->has('name')) {
            abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');
            $workflow->update($this->validatedWorkflow($request));
        }

        $workflow->update(['active' => $request->boolean('active')]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /**
     * Löschen nur für Entwürfe (noch nie einem Projekt zugewiesen) - dann
     * gefahrlos, da nirgends referenziert. Schritte + Funktionsgruppen-
     * Zuordnung hängen per cascadeOnDelete direkt dran, kein manuelles
     * Aufräumen nötig.
     */
    public function destroy(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr gelöscht werden.');

        $workflow->delete();

        return redirect()->route('admin.workflows')->with('status', 'workflows-updated');
    }

    /**
     * Bewusster "Freigabe"-Schritt (Ralfs Redaktionsprinzip: nicht
     * automatisch beim ersten Benutzen sperren, siehe Klassen-Docblock) -
     * ab hier ist der Workflow eingefroren, nur noch "Neue Version
     * erstellen" oder Aktiv/Inaktiv möglich.
     */
    public function publish(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht.');

        $workflow->update(['published_at' => now()]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /**
     * "Neue Version erstellen": Kopie des Workflows + aller Schritte + der
     * Funktionsgruppen-Zuordnung je Schritt unter neuem Namen. Alte Version
     * bleibt unverändert (bestehende Projekte zeigen weiter darauf),
     * bekommt aber superseded_by_id gesetzt und wird deaktiviert, damit sie
     * für NEUE Projekte nicht mehr wählbar ist (ProjectController::
     * availableWorkflows() filtert schon auf active=true OR aktuell
     * zugewiesen).
     */
    public function newVersion(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);

        $newWorkflow = DB::transaction(function () use ($workflow) {
            $nextSort = 1 + (int) Workflow::query()->where('tenant_id', $workflow->tenant_id)->max('sort');
            $newWorkflow = Workflow::query()->create([
                'tenant_id' => $workflow->tenant_id,
                'short_name' => $workflow->short_name,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'active' => true,
                'sort' => $nextSort,
            ]);

            $workflow->steps->each(function (WorkflowStep $step) use ($newWorkflow) {
                $newStep = WorkflowStep::query()->create([
                    'tenant_id' => $newWorkflow->tenant_id,
                    'workflow_id' => $newWorkflow->id,
                    'title' => $step->title,
                    'short_title' => $step->short_title,
                    'milestone_title' => $step->milestone_title,
                    'sort' => $step->sort,
                    'duration_days' => $step->duration_days,
                    'is_active' => $step->is_active,
                    'is_start' => $step->is_start,
                    'is_end' => $step->is_end,
                    'is_market_launch' => $step->is_market_launch,
                    'has_due_date' => $step->has_due_date,
                    'send_email' => $step->send_email,
                    'duration_editable' => $step->duration_editable,
                    'show_in_translation' => $step->show_in_translation,
                    'js_function' => $step->js_function,
                    'description' => $step->description,
                    'email_text' => $step->email_text,
                    'lifecycle_status' => $step->lifecycle_status,
                ]);

                $functionGroupIds = $step->functionGroups()->pluck('function_groups.id');
                if ($functionGroupIds->isNotEmpty()) {
                    $newStep->functionGroups()->sync(
                        $functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $newWorkflow->tenant_id]])
                    );
                }
            });

            $workflow->update(['superseded_by_id' => $newWorkflow->id, 'active' => false]);

            return $newWorkflow;
        });

        return redirect()->route('admin.workflows', ['workflow' => $newWorkflow->id])->with('status', 'workflows-updated');
    }

    public function stepStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $workflow = Workflow::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('workflow_id'));
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);

        $nextSort = 1 + (int) WorkflowStep::query()->where('workflow_id', $workflow->id)->max('sort');
        $step = WorkflowStep::query()->create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'title' => $title,
            'sort' => $nextSort,
            'is_active' => true,
        ]);

        return redirect()->route('admin.workflows', ['workflow' => $step->workflow_id])->with('status', 'workflows-updated');
    }

    public function stepUpdate(Request $request, WorkflowStep $step): RedirectResponse
    {
        abort_unless($step->tenant_id === CurrentTenant::id(), 404);

        $workflow = $step->workflow;
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'short_title' => ['nullable', 'string', 'max:255'],
            'milestone_title' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'js_function' => ['nullable', 'string', Rule::in(array_keys(WorkflowStep::SPECIAL_BUTTONS))],
            'lifecycle_status' => ['required', 'integer', 'between:1,4'],
            'description' => ['nullable', 'string'],
            'email_text' => ['nullable', 'string'],
        ]);

        $step->update([
            ...$validated,
            'duration_days' => $validated['duration_days'] ?? 0,
            'js_function' => $validated['js_function'] ?: null,
            'is_active' => $request->boolean('is_active'),
            'is_start' => $request->boolean('is_start'),
            'is_end' => $request->boolean('is_end'),
            'is_market_launch' => $request->boolean('is_market_launch'),
            'has_due_date' => $request->boolean('has_due_date'),
            'send_email' => $request->boolean('send_email'),
            'duration_editable' => $request->boolean('duration_editable'),
            'show_in_translation' => $request->boolean('show_in_translation'),
        ]);

        // function_groups[<id>]=1 statt function_groups[]=<id> - jede
        // Checkbox braucht einen eigenen Feldnamen, sonst würde
        // reloadManageListPreservingEdits() (snapshot je "input.name") beim
        // ungespeicherten Wiederherstellen nur die letzte Checkbox behalten.
        $functionGroupIds = collect(array_keys($request->array('function_groups', [])))->map(fn ($id) => (int) $id);
        $validIds = FunctionGroup::query()->where('tenant_id', $step->tenant_id)->whereIn('id', $functionGroupIds)->pluck('id');
        $step->functionGroups()->sync($validIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $step->tenant_id]]));

        return redirect()->route('admin.workflows', ['workflow' => $step->workflow_id])->with('status', 'workflows-updated');
    }

    /**
     * Löschen ist immer gefahrlos möglich - ein Schritt kann nur dann
     * existieren, ohne dass sein Workflow "publiziert" ist (siehe
     * stepUpdate/stepStore-Sperre), das heißt er wurde nie einem Projekt
     * zugewiesen und ist nirgends referenziert.
     */
    public function stepDestroy(WorkflowStep $step): RedirectResponse
    {
        abort_unless($step->tenant_id === CurrentTenant::id(), 404);

        $workflow = $step->workflow;
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $workflowId = $step->workflow_id;
        $step->delete();

        return redirect()->route('admin.workflows', ['workflow' => $workflowId])->with('status', 'workflows-updated');
    }

    /**
     * @return array{name: string, short_name: ?string, description: ?string}
     */
    private function validatedWorkflow(Request $request): array
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $shortName = trim((string) $request->string('short_name'));

        return [
            'name' => $name,
            'short_name' => $shortName !== '' ? mb_substr($shortName, 0, 10) : mb_substr($name, 0, 10),
            'description' => $request->filled('description') ? (string) $request->string('description') : null,
        ];
    }
}
