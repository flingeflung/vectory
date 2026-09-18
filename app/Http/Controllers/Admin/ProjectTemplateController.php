<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\Workflow;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung der Projektschablonen (Erfahrungswerte-Katalog für die
 * Redaktionsleitung, Nachfolger von Viettos GA-Kategorien - siehe
 * ProjectTemplate-Model-Docblock). Step 1 von Ralfs 4-Schritte-Plan zur
 * Kapa-Planung (2026-09-18, siehe Roadmap-Backlog): hier der reine Katalog.
 * Step 3 (Workflow-Kopplung) bestimmt jetzt die für Step 2 (Stunden je
 * Funktionsgruppe) zuweisbaren Fktgrp - Reihenfolge Ralf-korrigiert
 * (2026-09-18): "Zuerst muss ein WF gekoppelt werden, erst dadurch ergeben
 * sich die Fktgrps", siehe ProjectTemplate::relevantFunctionGroups().
 *
 * Master-Detail-Layout (Ralf, 2026-09-18: "nach dem bewährten Muster von
 * Projektkategorien, Workflows usw.: links die Schablonen, rechts die
 * Details") - links die (ggf. gefilterte) Liste, rechts entweder die
 * gewählte Schablone zum Bearbeiten (?schablone=<id>), ein Anlegen-Formular
 * (?neu=1) oder ein Platzhalter. Gleiches Grundmuster wie WorkflowController:
 * Umbenennen/Speichern läuft per fetch() + reloadManageListPreservingEdits()
 * (X-Overlay-Header) statt vollem Seiten-Reload.
 */
class ProjectTemplateController extends Controller
{
    public function index(Request $request): View|Response
    {
        $data = $this->buildIndexData($request, $request->filled('schablone') ? (int) $request->query('schablone') : null);

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.project-templates.partials.content', $data);
        }

        return view('admin.project-templates.index', $data);
    }

    /**
     * @return array{templates: \Illuminate\Support\Collection, selectedTemplate: ?ProjectTemplate, workflows: \Illuminate\Support\Collection, creating: bool}
     */
    private function buildIndexData(Request $request, ?int $templateId): array
    {
        $tenantId = CurrentTenant::id();

        $query = ProjectTemplate::query()->where('tenant_id', $tenantId);

        foreach (ProjectTemplate::filterableFields() as $field) {
            if ($request->filled($field)) {
                $query->where($field, (int) $request->query($field));
            }
        }

        // FIELD(): Wochen-Einträge vor Monate-Einträgen (gleiche Reihenfolge
        // wie Viettos gakat.php, intDauerEinheit 1=Wochen zuerst) -
        // alphabetisch wäre "months" fälschlich vor "weeks".
        $templates = $query->orderByRaw("FIELD(duration_unit, 'weeks', 'months')")
            ->orderBy('duration_value')->orderBy('name')
            ->get();

        // Nur für die AUSGEWÄHLTE Schablone die schwereren Relationen laden
        // (gleiches Muster wie WorkflowController: steps() nur für
        // $selectedWorkflow) - die Liste links bleibt schlank.
        $selectedTemplate = $templateId
            ? ProjectTemplate::query()->where('tenant_id', $tenantId)
                ->with(['createdByUser.person', 'updatedByUser.person', 'functionGroups', 'workflow.steps.functionGroups'])
                ->find($templateId)
            : null;

        // Alle Workflows des Mandanten fürs Auswahlfeld, auch inaktive/
        // ersetzte (grau markiert in der View) - eine Schablone könnte
        // schon auf einen solchen zeigen, siehe Auswahllisten-Konvention
        // in CLAUDE.md statt der "aktiv ODER gerade zugewiesen"-Variante.
        $workflows = Workflow::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get();

        return [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'workflows' => $workflows,
            'creating' => ! $selectedTemplate && $request->boolean('neu'),
        ];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validated($request, $tenantId);

        $template = ProjectTemplate::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    public function update(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $validated = $this->validated($request, $template->tenant_id);

        $template->update([
            ...$validated,
            'active' => $request->boolean('active'),
            'updated_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    /**
     * Geplante Stunden je Funktionsgruppe - eine leere/0-Eingabe entfernt
     * die Fktgrp aus der Schablone (kein 0-Stunden-Eintrag), statt sie als
     * "0 h geplant" stehen zu lassen.
     */
    public function updateFunctionGroups(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $request->validate(['hours' => ['nullable', 'array'], 'hours.*' => ['nullable', 'numeric', 'min:0', 'max:999']]);

        $hours = collect($request->array('hours'))
            ->mapWithKeys(fn ($value, $functionGroupId) => [(int) $functionGroupId => $value])
            ->filter(fn ($value) => $value !== null && $value !== '' && (float) $value > 0);

        // Nur Fktgrp, die der gekoppelte Workflow tatsächlich liefert (siehe
        // ProjectTemplate::relevantFunctionGroups()) - nicht mehr der volle
        // Mandanten-Katalog, sonst könnte hier eine fachlich nicht (mehr)
        // zutreffende Fktgrp durchrutschen (z.B. nach Workflow-Wechsel).
        $validIds = $template->load('workflow.steps.functionGroups')->relevantFunctionGroups()
            ->pluck('id')->intersect($hours->keys());

        $syncData = $validIds->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $template->tenant_id, 'planned_hours' => (float) $hours[$id]],
        ]);

        $template->functionGroups()->sync($syncData);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    public function destroy(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $template->delete();

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
    }

    private function validated(Request $request, int $tenantId): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'integer', 'in:1,2,3'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->where('tenant_id', $tenantId)],
            'duration_value' => ['required', 'numeric', 'min:0.5', 'max:999', 'multiple_of:0.5'],
            'duration_unit' => ['required', 'string', 'in:weeks,months'],
            'remarks' => ['nullable', 'string'],
        ];

        foreach (ProjectTemplate::characteristicFields() as $field => $meta) {
            $rules[$field] = ['required', 'integer', 'in:'.implode(',', array_keys($meta['options']))];
        }

        return $request->validate($rules);
    }
}
