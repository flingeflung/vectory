<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\ProjectTemplate;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Verwaltung der Projektschablonen (Erfahrungswerte-Katalog für die
 * Redaktionsleitung, Nachfolger von Viettos GA-Kategorien - siehe
 * ProjectTemplate-Model-Docblock). Step 1 von Ralfs 4-Schritte-Plan zur
 * Kapa-Planung (2026-09-18, siehe Roadmap-Backlog): hier nur der reine
 * Katalog. Step 2 (Stunden je Funktionsgruppe) ist dazugekommen, noch ohne
 * Workflow-Kopplung (Step 3) - die beteiligten Fktgrp werden hier weiterhin
 * manuell zusammengestellt.
 *
 * Gleiches Grundmuster wie die "klitzekleinen" Verwalten-Overlays (Firma/
 * Abteilung/...): eine Liste von Zeilen-Formularen (data-row-form, siehe
 * View) + ein Anlegen-Formular, per fetch() gespeichert und per
 * reloadManageListPreservingEdits() neu geladen statt vollem Seiten-Reload -
 * nur hier auf einer eigenen Seite statt in einem globalen Modal, weil
 * Projektschablonen (anders als Firma/Abteilung) ein eigenständiger
 * Admin-Bereich sind, kein Unterbereich der Personenverwaltung.
 */
class ProjectTemplateController extends Controller
{
    public function index(Request $request): View|Response
    {
        $tenantId = CurrentTenant::id();

        $query = ProjectTemplate::query()->where('tenant_id', $tenantId);

        foreach (ProjectTemplate::filterableFields() as $field) {
            if ($request->filled($field)) {
                $query->where($field, (int) $request->query($field));
            }
        }

        $templates = $query->with(['createdByUser.person', 'updatedByUser.person', 'functionGroups'])
            // FIELD(): Wochen-Einträge vor Monate-Einträgen (gleiche
            // Reihenfolge wie Viettos gakat.php, intDauerEinheit 1=Wochen
            // zuerst) - alphabetisch wäre "months" fälschlich vor "weeks".
            ->orderByRaw("FIELD(duration_unit, 'weeks', 'months')")
            ->orderBy('duration_value')->orderBy('name')
            ->get();

        // Katalog des Mandanten, NICHT über Person::visibleTenantIds o.ä. -
        // Projektschablonen sind (anders als Personen) bewusst nicht per
        // Kundenzugriff mit anderen Mandanten teilbar (Ralf, 2026-09-18:
        // "Pro Kunden-Mandant"), Fktgrp-Auswahl bleibt also strikt auf den
        // eigenen Katalog beschränkt.
        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get();

        $data = ['templates' => $templates, 'functionGroups' => $functionGroups];

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.project-templates.partials.content', $data);
        }

        return view('admin.project-templates.index', $data);
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validated($request);

        ProjectTemplate::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
    }

    public function update(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $validated = $this->validated($request);

        $template->update([
            ...$validated,
            'active' => $request->boolean('active'),
            'updated_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
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

        $validIds = FunctionGroup::query()->where('tenant_id', $template->tenant_id)->whereIn('id', $hours->keys())->pluck('id');

        $syncData = $validIds->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $template->tenant_id, 'planned_hours' => (float) $hours[$id]],
        ]);

        $template->functionGroups()->sync($syncData);

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
    }

    public function destroy(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $template->delete();

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
    }

    private function validated(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'integer', 'in:1,2,3'],
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
