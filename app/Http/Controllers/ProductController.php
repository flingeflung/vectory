<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Produkte"-Übersicht (Ralf, 2026-09-13), analog Viettos Modelle-Seite
 * (inhalt/modelle.php) - Daten kommen vorerst aus dem Mini-PIM
 * (ProductGroup/Product, siehe GenerateTestProducts), später über eine
 * echte PIM-Schnittstelle. Projektverknüpfung wird über das "Modell/
 * System"-Feld im Projekt gepflegt (siehe ProjectProductController), hier
 * nur Anzeige.
 *
 * Nachladen beim Scrollen ans Listenende (Ralf: "bei der Projektübersicht
 * haste das doch mit Automatik hinbekommen") - gleiches Muster wie
 * ProjectConnectionController::moreOtherProjects()/connection-add-
 * body.blade.php (IntersectionObserver + X-Has-More-Header), keine
 * Button-Pagination.
 */
class ProductController extends Controller
{
    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['product_number', 'name', 'group_number', 'group_name', 'extra_text'];

    private const PAGE_SIZE = 500;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        [$sort, $direction] = $this->sortFromRequest($request);

        $totalCount = Product::query()->count();
        $total = (clone $this->baseQuery($search))->count();
        $products = $this->baseQuery($search)
            ->orderBy($this->sortColumn($sort), $direction)->orderBy('products.id', $direction)
            ->with(['productGroup', 'projects:id,source_pn,title'])
            ->take(self::PAGE_SIZE)
            ->get();

        return view('produkte.index', [
            'products' => $products,
            'totalCount' => $totalCount,
            'total' => $total,
            'hasMore' => $total > $products->count(),
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
    }

    /**
     * Nachladen ab $offset (siehe Klassen-Docblock) - liefert nur die
     * Zeilen-Fragmente, "weitere vorhanden?" steckt im X-Has-More-Header.
     */
    public function more(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $offset = max(0, $request->integer('offset'));
        [$sort, $direction] = $this->sortFromRequest($request);

        $products = $this->baseQuery($search)
            ->orderBy($this->sortColumn($sort), $direction)->orderBy('products.id', $direction)
            ->with(['productGroup', 'projects:id,source_pn,title'])
            ->skip($offset)->take(self::PAGE_SIZE)
            ->get();

        $html = view('produkte.partials.rows', ['products' => $products])->render();

        return response($html)->header('X-Has-More', $products->count() === self::PAGE_SIZE ? '1' : '0');
    }

    private function baseQuery(string $search): Builder
    {
        $query = Product::query()
            ->leftJoin('product_groups', 'product_groups.id', '=', 'products.product_group_id')
            ->select('products.*');

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('products.product_number', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('product_groups.number', 'like', "%{$search}%")
                    ->orWhere('product_groups.name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function sortColumn(?string $sort): string
    {
        return match ($sort) {
            'group_number' => 'product_groups.number',
            'group_name' => 'product_groups.name',
            'extra_text' => 'products.extra_text',
            'name' => 'products.name',
            default => 'products.product_number',
        };
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function sortFromRequest(Request $request): array
    {
        $sort = in_array($request->query('sort'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sort')
            : null;
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return [$sort, $direction];
    }
}
