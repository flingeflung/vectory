<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaperFormat;
use App\Models\PaperFormatCombination;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung des Papierformat-Katalogs (Ralf, 2026-09-15, Vietto-Analyse
 * "Print-Formate", Schritt 1 von 5 - siehe Backlog-Memory für die volle
 * Herleitung). Reine Einzelgrößen (z.B. "DIN A6 hoch"); Schritt 2 baut
 * darauf die eigentlich nutzbaren Format-KOMBINATIONEN.
 */
class PaperFormatController extends Controller
{
    public function index(): View
    {
        $formats = PaperFormat::query()->orderBy('sort')->get();

        $combinations = PaperFormatCombination::query()
            ->with(['inputFormat', 'outputFormat'])
            ->get()
            ->sortBy([
                fn ($c) => $c->inputFormat->sort,
                fn ($c) => $c->outputFormat->sort,
            ])
            ->values();

        return view('admin.papierformate.index', ['formats' => $formats, 'combinations' => $combinations]);
    }

    /**
     * Liefert nur den Katalog-Inhalt fürs "Papierformate verwalten"-Overlay
     * (per fetch nachgeladen, gleiches Muster wie z.B. CompanyController -
     * eigener Endpunkt statt Overlay-Erkennung im normalen index(), da das
     * Overlay nur den Katalog braucht, nicht die Format-Kombinationen).
     */
    public function catalog(): View
    {
        $formats = PaperFormat::query()->orderBy('sort')->get();

        return view('admin.papierformate.partials.catalog', ['formats' => $formats]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        $name = trim((string) $request->string('name'));
        $widthMm = $request->integer('width_mm');
        $heightMm = $request->integer('height_mm');
        abort_if($name === '' || $widthMm < 1 || $heightMm < 1, 422);

        PaperFormat::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'sort' => 1 + (int) PaperFormat::query()->max('sort'),
        ]);

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-updated');
    }

    public function update(Request $request, PaperFormat $paperFormat): RedirectResponse
    {
        abort_unless($paperFormat->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        $widthMm = $request->integer('width_mm');
        $heightMm = $request->integer('height_mm');
        abort_if($name === '' || $widthMm < 1 || $heightMm < 1, 422);

        $paperFormat->update([
            'name' => $name,
            'short_name' => trim((string) $request->string('short_name')) ?: null,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'remark' => trim((string) $request->string('remark')) ?: null,
            'show_dimensions' => $request->boolean('show_dimensions'),
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-updated');
    }

    /**
     * Sobald ein Format in einer Format-Kombination (Schritt 2) verwendet
     * wird, ist Löschen gesperrt - dann übernimmt das "Aktiv"-Häkchen in
     * update() die Rolle (nicht mehr neu wählbar, bestehende Verknüpfungen
     * bleiben gültig). Die DB-Fremdschlüssel (restrictOnDelete) würden das
     * ohnehin verhindern, dieser Check liefert nur die saubere Meldung
     * statt eines rohen SQL-Fehlers.
     */
    public function destroy(PaperFormat $paperFormat): RedirectResponse
    {
        abort_unless($paperFormat->tenant_id === CurrentTenant::id(), 404);
        abort_if($paperFormat->isUsedInCombination(), 422, 'Dieses Format wird bereits in einer Format-Kombination verwendet und kann nicht gelöscht werden.');

        $paperFormat->delete();

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-deleted');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('formats'))->values()->each(function (string $id, int $index) use ($tenantId) {
            PaperFormat::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.papierformate');
    }
}
