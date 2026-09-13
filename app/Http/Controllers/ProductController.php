<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Produkte"-Übersicht (Ralf, 2026-09-13), analog Viettos Modelle-Seite
 * (inhalt/modelle.php) - Daten kommen vorerst aus dem Mini-PIM
 * (ProductGroup/Product, siehe GenerateTestProducts), später über eine
 * echte PIM-Schnittstelle. Projektverknüpfung noch nicht gebaut (Ralf:
 * "machen wir danach") - Spalte zeigt bewusst nur einen Platzhalter.
 */
class ProductController extends Controller
{
    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['product_number', 'name', 'group_number', 'group_name', 'extra_text'];

    private const DEFAULT_LIMIT = 500;

    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $limit = max(self::DEFAULT_LIMIT, (int) $request->query('limit', self::DEFAULT_LIMIT));
        $search = trim((string) $request->query('q', ''));

        [$sort, $direction] = $this->sortFromRequest($request);

        $query = Product::query()
            ->leftJoin('product_groups', 'product_groups.id', '=', 'products.product_group_id')
            ->select('products.*');

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('products.product_number', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('product_groups.number', 'like', "%{$search}%")
                    ->orWhere('product_groups.name', 'like', "%{$search}%");
            });
        }

        $sortColumn = match ($sort) {
            'group_number' => 'product_groups.number',
            'group_name' => 'product_groups.name',
            'extra_text' => 'products.extra_text',
            'name' => 'products.name',
            default => 'products.product_number',
        };
        $query->orderBy($sortColumn, $direction)->orderBy('products.id', $direction);

        $total = (clone $query)->count();
        $products = $query->with('productGroup')->limit($limit)->get();

        return view('produkte.index', [
            'products' => $products,
            'total' => $total,
            'limit' => $limit,
            'hasMore' => $total > $limit,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
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
