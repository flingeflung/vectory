<?php

namespace App\Http\Controllers;

use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\GraphicOrderStatus;
use App\Models\Person;
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

    public function index(Request $request): View
    {
        $statuses = GraphicOrderStatus::query()->orderBy('sort')->get();
        $illustrationPersons = FunctionGroup::query()
            ->where('legacy_id', 5)
            ->first()
            ?->members ?? collect();

        $selectedStatuses = $this->selectedIds($request, 'status', $statuses->pluck('id'));
        $selectedIllustrators = $this->selectedIds($request, 'illustrator', [...$illustrationPersons->pluck('id'), self::UNASSIGNED]);
        $initiatorId = $request->query('initiator');
        $dueFrom = $request->query('due_from');
        $dueTo = $request->query('due_to');
        $search = trim((string) $request->query('q', ''));

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
     * Query-Array einlesen (z.B. status[]=1&status[]=2) - fehlt der Parameter
     * komplett (erster Aufruf ohne Filter), gilt alles als ausgewählt statt
     * nichts, sonst wäre die Liste beim ersten Öffnen leer.
     *
     * @param  Collection<int, int|string>  $allValues
     * @return Collection<int, int|string>
     */
    private function selectedIds(Request $request, string $key, iterable $allValues): Collection
    {
        if (! $request->has($key)) {
            return collect($allValues)->values();
        }

        return collect((array) $request->query($key))
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->values();
    }
}
