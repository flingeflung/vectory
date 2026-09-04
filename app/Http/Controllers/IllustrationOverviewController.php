<?php

namespace App\Http\Controllers;

use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\GraphicOrderStatus;
use App\Models\Person;
use App\Models\User;
use App\Support\ProjectColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Projektübergreifende Illustrationsauftrags-Liste, analog Viettos
 * "Grafikerstellung" (inhalt/grafiken.php + ajax_grtask_getgrtasks.php).
 * Ergänzt die projektbezogene Sicht (GraphicOrderController /
 * Illustrationsaufträge-Modal) um eine Gesamtübersicht über alle Projekte.
 *
 * Bewusst kein "Viega Bearbeiter"-Feld (Vietto: wer hat zuletzt zugewiesen) -
 * gibt's bei uns nicht als eigene Spalte, die Info steckt schon im
 * Vorgänge-Log jedes Projekts. Kein Datei-Verzeichnis-Link (Vietto:
 * lokaler Netzwerkpfad) - eigenes Feature, noch nicht gebaut.
 */
class IllustrationOverviewController extends Controller
{
    /**
     * "nicht zugewiesen" als Illustrator-Filteroption - kein echter
     * Personen-ID, eigener Sentinel-Wert für die Filter-Checkbox.
     */
    private const UNASSIGNED = 'none';

    /**
     * @var list<string>
     */
    private const FILTER_KEYS = ['status', 'illustrator', 'initiator', 'due_from', 'due_to', 'q'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $statuses = GraphicOrderStatus::query()->orderBy('sort')->get();
        $illustrationPersons = FunctionGroup::query()
            ->where('legacy_id', 5)
            ->first()
            ?->members
            ->sortBy(fn (Person $person) => $person->fullName())
            ->values() ?? collect();

        // Gleiches Muster wie Projekt-/Aufgaben-Filter: ein expliziter
        // Marker unterscheidet "Filterformular abgeschickt" (jetzt merken,
        // auch wenn dabei einzelne Checkbox-Gruppen komplett leer sind) von
        // "nackte Navigation zu /illustrationen" (zuletzt gemerkten Stand
        // wiederherstellen).
        if ($request->has('illustrationsfilter_submitted')) {
            $filters = $this->filtersFromRequest($request);
            $this->persistFilters($user, $filters);
        } elseif (! $request->hasAny([...self::FILTER_KEYS, 'illustrationsfilter_submitted'])) {
            $filters = $this->persistedFiltersFor($user);
        } else {
            $filters = $this->filtersFromRequest($request);
        }

        $selectedStatuses = $filters['status'] !== null
            ? collect($filters['status'])->map(fn ($value) => (int) $value)->values()
            : $statuses->pluck('id');
        $selectedIllustrators = $filters['illustrator'] !== null
            ? collect($filters['illustrator'])->map(fn ($value) => $value === self::UNASSIGNED ? $value : (int) $value)->values()
            : collect([...$illustrationPersons->pluck('id'), self::UNASSIGNED]);
        $initiatorId = $filters['initiator'];
        $dueFrom = $filters['due_from'];
        $dueTo = $filters['due_to'];
        $search = (string) $filters['q'];

        $query = GraphicOrder::query()
            ->with(['project', 'status', 'initiatedBy', 'illustrator.company'])
            ->whereIn('graphic_order_status_id', $selectedStatuses->isEmpty() ? [0] : $selectedStatuses);

        $query->where(function (Builder $query) use ($selectedIllustrators) {
            if ($selectedIllustrators->contains(self::UNASSIGNED)) {
                $query->orWhereNull('illustrator_person_id');
            }
            $ids = $selectedIllustrators->reject(fn ($id) => $id === self::UNASSIGNED)->values();
            if ($ids->isNotEmpty()) {
                $query->orWhereIn('illustrator_person_id', $ids);
            }
        });

        if ($initiatorId) {
            $query->where('initiated_by_person_id', $initiatorId);
        }

        if ($dueFrom) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }

        if ($dueTo) {
            $query->whereDate('due_date', '<=', $dueTo);
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->whereHas('project', fn (Builder $q) => $q->where('source_pn', 'like', "%{$search}%"));
                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        $orders = $query
            ->join('projects', 'projects.id', '=', 'graphic_orders.project_id')
            ->orderBy('projects.source_pn')
            ->orderBy('graphic_orders.created_at')
            ->select('graphic_orders.*')
            ->get();

        $initiatorOptions = Person::query()
            ->whereIn('id', GraphicOrder::query()->whereNotNull('initiated_by_person_id')->distinct()->pluck('initiated_by_person_id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('illustrationen.index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'illustrationPersons' => $illustrationPersons,
            'initiatorOptions' => $initiatorOptions,
            'selectedStatuses' => $selectedStatuses,
            'selectedIllustrators' => $selectedIllustrators,
            'initiatorId' => $initiatorId,
            'dueFrom' => $dueFrom,
            'dueTo' => $dueTo,
            'search' => $search,
        ]);
    }

    /**
     * @return array{status: ?list<string>, illustrator: ?list<string>, initiator: ?string, due_from: ?string, due_to: ?string, q: ?string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            // null = Parameter fehlt komplett (Filtergruppe nie angefasst) ->
            // später als "alles ausgewählt" behandelt. Leeres Array dagegen
            // heißt "abgeschickt, aber bewusst nichts angehakt" (z.B. "Keiner"
            // + Anwenden) und bleibt auch leer.
            'status' => $request->has('status') ? array_map('strval', (array) $request->query('status')) : null,
            'illustrator' => $request->has('illustrator') ? array_map('strval', (array) $request->query('illustrator')) : null,
            'initiator' => $request->query('initiator') ?: null,
            'due_from' => $request->query('due_from') ?: null,
            'due_to' => $request->query('due_to') ?: null,
            'q' => trim((string) $request->query('q', '')) ?: null,
        ];
    }

    /**
     * @return array{status: ?list<string>, illustrator: ?list<string>, initiator: ?string, due_from: ?string, due_to: ?string, q: ?string}
     */
    private function persistedFiltersFor(User $user): array
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);

        return $set->config['illustrationen_filter'] ?? [
            'status' => null, 'illustrator' => null, 'initiator' => null, 'due_from' => null, 'due_to' => null, 'q' => null,
        ];
    }

    /**
     * @param  array{status: ?list<string>, illustrator: ?list<string>, initiator: ?string, due_from: ?string, due_to: ?string, q: ?string}  $filters
     */
    private function persistFilters(User $user, array $filters): void
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);
        $config = $set->config;
        $config['illustrationen_filter'] = $filters;
        $set->update(['config' => $config]);
    }
}
