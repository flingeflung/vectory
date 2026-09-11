<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\CopyTemplate;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Projekte kopieren"-Vorlagen (Ralf, 2026-09-11) - Verwaltungsseite,
 * analog Mail-Vorlagen (links Liste, rechts Details) fürs Anlegen/
 * Umbenennen/Löschen der Vorlage, plus ein Kästchen-Raster wie bei den
 * Projektattribut-Projektart-Zuordnungen fürs Ein-/Ausschließen einzelner
 * Felder - kein Speichern-Button nötig, jeder Klick wirkt sofort. Die
 * eigentliche Kopieraktion im Projekt selbst ist ein späterer Schritt,
 * hier geht es nur um die Vorlagen-Verwaltung.
 */
class CopyTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = CurrentTenant::current();

        $templates = CopyTemplate::query()->where('tenant_id', $tenant->id)->orderBy('sort')->orderBy('name')->get();

        $selectedTemplate = $request->filled('vorlage')
            ? $templates->firstWhere('id', (int) $request->query('vorlage'))
            : null;

        $attributesBySection = Attribute::query()->where('tenant_id', $tenant->id)->orderBy('sort')->get()->groupBy('section');

        $selectedFieldIds = $selectedTemplate
            ? $selectedTemplate->fields()->pluck('attributes.id')->all()
            : [];

        return view('admin.copy-templates.index', [
            'tenant' => $tenant,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'attributesBySection' => $attributesBySection,
            'selectedFieldIds' => $selectedFieldIds,
        ]);
    }

    /**
     * Feste Felder, die beim Anlegen einer neuen Vorlage standardmäßig
     * angehakt sind (Ralf/Claude-Analyse, 2026-09-11): strukturelle
     * Klassifikation, die bei einer Kopie meist unverändert bleibt.
     * Alle anderen festen Felder starten unangehakt (Bezeichnung, Start/
     * Ende, Version, Status/Erstellungsstatus, Bemerkungen, Publikations-
     * datum, Projektbeteiligte Personen, Archiviert, die noch datenlosen
     * Platzhalterfelder) - jeweils, weil eine Kopie hier bewusst frisch
     * starten oder der Nutzer aktiv entscheiden soll.
     */
    private const DEFAULT_CHECKED_KEYS = ['project_type', 'markets', 'workflow_id'];

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $template = CopyTemplate::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'sort' => 1 + (int) CopyTemplate::query()->where('tenant_id', $tenantId)->max('sort'),
        ]);

        // Zusatzfelder (system=false) sind ebenfalls default angehakt -
        // Ausnahme Freitext (mehrzeilig) und Datum, die wie ihre festen
        // Pendants (Bemerkungen, Start/Ende, Publikationsdatum) bewusst
        // nicht automatisch übernommen werden.
        $defaultFieldIds = Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereIn('key', self::DEFAULT_CHECKED_KEYS)
                    ->orWhere(function ($query) {
                        $query->where('system', false)
                            ->whereNotIn('data_type', [Attribute::DATA_TYPE_TEXTAREA, Attribute::DATA_TYPE_DATE]);
                    });
            })
            ->pluck('id');

        $template->fields()->attach($defaultFieldIds);

        return redirect()->route('admin.projektkopie-vorlagen', ['vorlage' => $template->id])->with('status', 'copy-templates-updated');
    }

    public function update(Request $request, CopyTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $template->update(['name' => $name]);

        return redirect()->route('admin.projektkopie-vorlagen', ['vorlage' => $template->id])->with('status', 'copy-templates-updated');
    }

    public function destroy(CopyTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $template->delete();

        return redirect()->route('admin.projektkopie-vorlagen')->with('status', 'copy-templates-updated');
    }

    /**
     * Kästchen-Raster-Toggle, analog AttributeController::toggleProjectType().
     */
    public function toggleField(Request $request, CopyTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $attribute = Attribute::query()->where('tenant_id', $template->tenant_id)->findOrFail($request->integer('attribute_id'));

        if ($template->fields()->where('attributes.id', $attribute->id)->exists()) {
            $template->fields()->detach($attribute->id);
        } else {
            $template->fields()->attach($attribute->id);
        }

        return redirect()->route('admin.projektkopie-vorlagen', ['vorlage' => $template->id]);
    }

    public function markAll(CopyTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $allIds = Attribute::query()->where('tenant_id', $template->tenant_id)->pluck('id');
        $template->fields()->sync($allIds);

        return redirect()->route('admin.projektkopie-vorlagen', ['vorlage' => $template->id]);
    }

    public function markNone(CopyTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $template->fields()->detach();

        return redirect()->route('admin.projektkopie-vorlagen', ['vorlage' => $template->id]);
    }

    public function updateMaxCopies(Request $request): RedirectResponse
    {
        $tenant = CurrentTenant::current();

        $max = $request->integer('max_project_copies');
        abort_unless($max >= 1 && $max <= 50, 422);

        $tenant->update(['max_project_copies' => $max]);

        return redirect()->route('admin.projektkopie-vorlagen')->with('status', 'copy-templates-updated');
    }
}
