<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Project;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Produkt-Verknüpfung am Projekt-Feld "Modell/System" (Ralf, 2026-09-13,
 * analog Viettos Modell-Zuordnung: ajax_getmodelle.php/ajax_modelsadd.php).
 * Bewusst NICHT nachgebaut: "Neues Modell anlegen" aus dem Picker heraus,
 * die Modell/Artikel-Übersicht, die PSP-Verknüpfung (Ralf, explizit
 * ausgeschlossen).
 *
 * Sofort-Toggle wie im Vietto-Vorbild (kein Speichern-Button, jeder Klick
 * verknüpft/entfernt direkt) - anders als der Projektverknüpfungen-Picker
 * (der braucht Freitext-Labels beim Hinzufügen, hier nicht nötig, reine
 * Ja/Nein-Mitgliedschaft). Das Picker-Overlay selbst wird beim Toggle NICHT
 * neu gerendert (sonst geht der "geöffnet"-Zustand des x-modal verloren) -
 * nur die Feldzeile außerhalb des Overlays wird aktualisiert.
 */
class ProjectProductController extends Controller
{
    private const PAGE_SIZE = 500;

    public function picker(Project $project, Request $request): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $search = trim((string) $request->query('q', ''));
        $otherQuery = $this->otherQuery($project, $search);
        $otherProducts = (clone $otherQuery)->take(self::PAGE_SIZE)->get();

        return response()->view('projekte.partials.product-picker-body', [
            'project' => $project,
            'linkedProducts' => $project->products()->with('productGroup')->get(),
            'otherProducts' => $otherProducts,
            'otherHasMore' => (clone $otherQuery)->count() > $otherProducts->count(),
        ]);
    }

    /**
     * Nachladen weiterer "andere Produkte" beim Scrollen - gleiches Muster
     * wie ProjectConnectionController::moreOtherProjects().
     */
    public function more(Project $project, Request $request): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $search = trim((string) $request->query('q', ''));
        $offset = max(0, $request->integer('offset'));

        $otherProducts = $this->otherQuery($project, $search)->skip($offset)->take(self::PAGE_SIZE)->get();

        $html = view('projekte.partials.product-picker-rows', ['products' => $otherProducts])->render();

        return response($html)->header('X-Has-More', $otherProducts->count() === self::PAGE_SIZE ? '1' : '0');
    }

    public function toggle(Project $project, Product $product): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($product->tenant_id === CurrentTenant::id(), 404);

        if ($project->products()->where('product_id', $product->id)->exists()) {
            $project->products()->detach($product->id);
        } else {
            $project->products()->attach($product->id);
        }

        return response()->view('projekte.partials.system-fields.system_model', [
            'project' => $project->fresh(['products']),
            'field' => Attribute::query()->where('tenant_id', $project->tenant_id)->where('key', 'system_model')->first(),
        ]);
    }

    private function otherQuery(Project $project, string $search): Builder
    {
        $linkedIds = $project->products()->pluck('products.id');

        $query = Product::query()->whereNotIn('id', $linkedIds)->with('productGroup')->orderBy('name');

        if ($search !== '') {
            $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('product_number', 'like', "%{$search}%"));
        }

        return $query;
    }
}
