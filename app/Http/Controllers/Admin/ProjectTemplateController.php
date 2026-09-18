<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
 * Katalog, noch ohne Workflow-Kopplung oder Fktgrp-Stunden.
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

        $templates = $query->with(['createdByUser.person', 'updatedByUser.person'])
            // FIELD(): Wochen-Einträge vor Monate-Einträgen (gleiche
            // Reihenfolge wie Viettos gakat.php, intDauerEinheit 1=Wochen
            // zuerst) - alphabetisch wäre "months" fälschlich vor "weeks".
            ->orderByRaw("FIELD(duration_unit, 'weeks', 'months')")
            ->orderBy('duration_value')->orderBy('name')
            ->get();

        $data = ['templates' => $templates];

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
