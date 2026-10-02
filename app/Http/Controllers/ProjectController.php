<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\GraphicOrderStatus;
use Carbon\CarbonImmutable;
use App\Mail\ProjectRequestMail;
use App\Models\Activity;
use App\Models\Attribute;
use App\Models\Checklist;
use App\Models\DisplayFilterSet;
use App\Models\Favorite;
use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\Market;
use App\Models\MarketSet;
use App\Models\Project;
use App\Models\ProjectFilterSet;
use App\Models\ProjectGroup;
use App\Models\ProjectPerson;
use App\Models\ProjectTemplate;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\ProjectWorkflowStep;
use App\Models\ProjectWorkflowStepPerson;
use App\Models\RecentlyViewedProject;
use App\Models\Setting;
use App\Models\SystemSetting;
use App\Models\UserTablePreference;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\CriticalProjects\CriticalProjectEvaluator;
use App\Services\ProjectDirectoryLocator;
use App\Services\ProjectNumberAllocator;
use App\Support\CurrentTenant;
use App\Support\ProjectColumnCatalog;
use App\Support\ProjectFilterCatalog;
use App\Support\StammId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Spalten, für die eine Sortierung fachlich sinnvoll ist. Wird sukzessive erweitert.
     *
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['source_pn', 'title', 'status', 'workflow'];

    private const DATE_RANGE_FIELDS = ['start_date', 'end_date', 'publication_date'];

    private const BOOL_FIELDS = ['archived'];

    /**
     * Ralf, 2026-09-13: "Hatten wir das nicht umgestellt auf automatisch
     * nachladen?" - war bisher klassische Seiten-Pagination (25/Seite),
     * jetzt automatisches Nachladen beim Scrollen wie bei Produkte/den
     * Verknüpfen-Pickern. Batchgröße bewusst bei 25 belassen (nicht wie
     * dort 500) - hier ist jede Zeile deutlich schwerer (viele Spalten,
     * Märkte-Icons, Workflow-Fortschritt), 500 auf einmal wäre spürbar
     * langsamer beim Rendern.
     */
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly ProjectDirectoryLocator $directoryLocator,
        private readonly ProjectNumberAllocator $numberAllocator,
        private readonly CriticalProjectEvaluator $criticalProjectEvaluator,
    ) {}

    public function index(Request $request): View
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = ProjectFilterCatalog::filtersFromRequest($request);
        $user = $request->user();

        // Projektfilter wurde abgeschickt -> die gerade sichtbare Feldauswahl wird
        // automatisch als neuer Standard gemerkt. Eigener Marker statt nur auf
        // filter_fields zu prüfen, da der bei komplett leerer Auswahl (z.B. nach
        // "Alle Filter zurücksetzen") gar nicht mitgeschickt wird - sonst würde der
        // Reset nie dauerhaft ankommen und die alte Feldauswahl käme beim nächsten
        // Öffnen wieder zurück.
        if ($request->has('projektfilter_submitted')) {
            ProjectFilterCatalog::persistActiveFields($user, array_values($request->input('filter_fields', [])));
            ProjectFilterCatalog::persistFilterValues($user, $filters);
        } elseif (empty($filters) && ! $request->hasAny(['filter', 'sort', 'direction', 'page'])) {
            // Komplett "nackte" Navigation zu /projekte (z.B. Sidebar-Link "Projekte",
            // nicht ein Sortier-/Blätter-/Filter-Link innerhalb der Übersicht selbst) ->
            // zuletzt angewandte Filterwerte wiederherstellen. Ein Sortier-Klick auf einer
            // bewusst ungefilterten Liste (sort/direction/page gesetzt, aber kein Filter)
            // soll dagegen NICHT plötzlich einen alten Filter wiederbeleben.
            $filters = ProjectFilterCatalog::persistedFiltersFor($user);
        }

        // Persönliche Einstellung (Ralf, 2026-09-09; Vietto-Vorbild
        // voreinst_projektfilter_verworfene): ohne eigene Status-Auswahl
        // werden verworfene Projekte (status=3) nie mitgezeigt, alle
        // anderen Status schon. Bewusst ECHTER Filter (landet in $filters
        // selbst, nicht nur in der Query) - soll im Projektfilter-Dialog
        // als aktives, sichtbares/änderbares Feld erscheinen (Ralf:
        // "Status immer anzeigen" heißt auch, dass man ihn sieht, nicht
        // nur dass er im Hintergrund wirkt). Greift nicht, wenn schon
        // irgendein Status-Filter aktiv ist, das soll nicht überstimmt
        // werden. Bewusst NACH dem persist-Block, damit dieser Standard
        // nie versehentlich als "vom Nutzer gewählt" gespeichert wird.
        if (empty($filters['status']) && $user->hide_discarded_projects_on_reset) {
            $filters['status'] = [0, 1, 2];
        }

        $allColumns = ProjectColumnCatalog::effectiveFor($user);
        $visibleColumns = array_values(array_filter($allColumns, fn (array $column) => $column['visible']));

        $totalFiltered = $this->orderedQuery($sort, $direction, $filters)->count();
        $query = $this->eagerLoadForColumns($this->orderedQuery($sort, $direction, $filters), $visibleColumns);
        $projects = $query->take(self::PAGE_SIZE)->get();

        $activeFilterFields = ! empty($filters) || $request->has('projektfilter_submitted')
            ? array_values(array_unique([...ProjectFilterCatalog::activeFieldsFor($user), ...array_keys($filters)]))
            : ProjectFilterCatalog::activeFieldsFor($user);

        // Ralf-Bug-Report, 2026-09-13: "Projekte dieser Gruppe anzeigen"
        // (reopen_group) öffnete das Gruppieren-Panel bisher per Nach-Laden
        // (Fetch nach dem ersten Render) - das verursachte ein sichtbares
        // "Flush und neu laden" des Panel-Inhalts. Bei reopen_group werden
        // Gruppenliste + Mitglieder-IDs jetzt direkt mit dieser Seite selbst
        // mitgerendert (kein zweiter Request mehr nötig für den ersten
        // Anblick), siehe project-group-modal.blade.php.
        $reopenGroups = null;
        $reopenMemberIds = collect();
        if ($request->filled('reopen_group')) {
            $reopenGroups = ProjectGroup::visibleTo($user)->withCount(['projects', 'viewers'])->orderBy('name')->get();
            $reopenMemberIds = ProjectGroup::query()->find($request->integer('reopen_group'))?->projects()->pluck('projects.id') ?? collect();
        }

        return view('projekte.index', [
            'reopenGroups' => $reopenGroups,
            'reopenMemberIds' => $reopenMemberIds,
            'columnWidths' => UserTablePreference::widthsFor($user->id, 'projekte'),
            ...$this->rowData($projects, $visibleColumns, $user),
            'columns' => $visibleColumns,
            'sortableColumnKeys' => [...self::SORTABLE_COLUMNS, ...self::sortableAttributeColumns()],
            'allColumns' => $allColumns,
            'sets' => DisplayFilterSet::query()->where('user_id', $user->id)->orderBy('name')->get(),
            'projectFilterSets' => ProjectFilterSet::query()->where('user_id', $user->id)->orderBy('name')->get(),
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $filters,
            'filterFields' => ProjectFilterCatalog::available(CurrentTenant::id()),
            'activeFilterFields' => $activeFilterFields,
            'filterChips' => ProjectFilterCatalog::describeFilters($filters, CurrentTenant::id()),
            'totalCount' => Project::query()->count(),
            'totalFiltered' => $totalFiltered,
            'pageSize' => self::PAGE_SIZE,
            'hasMore' => $totalFiltered > $projects->count(),
        ]);
    }

    /**
     * Nachladen ab $offset (siehe PAGE_SIZE-Docblock) - liefert nur die
     * Zeilen-Fragmente, "weitere vorhanden?" steckt im X-Has-More-Header.
     * Gleiches Muster wie ProductController::more()/ProjectConnection
     * Controller::moreOtherProjects().
     *
     * Optionaler "take"-Parameter (Default PAGE_SIZE): Ralf, 2026-09-13,
     * zu Multichange: "ich verstehe nicht, warum du nicht per Ajax nur die
     * Übersicht lädst, sondern die komplett neue Seite" - damit lassen
     * sich mit offset=0 genau die gerade schon geladenen Zeilen (take =
     * aktuelle Zeilenanzahl) in EINEM Rutsch neu abfragen und per Ajax
     * ersetzen, statt die ganze Seite neu zu laden (siehe
     * window.refreshProjectRows() in layouts/app.blade.php). Nach oben
     * begrenzt, damit daraus kein "alles auf einmal"-Vollabzug wird.
     */
    public function more(Request $request): Response
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = ProjectFilterCatalog::filtersFromRequest($request);
        $user = $request->user();
        $offset = max(0, $request->integer('offset'));
        $take = min(500, max(1, $request->integer('take') ?: self::PAGE_SIZE));

        $visibleColumns = array_values(array_filter(ProjectColumnCatalog::effectiveFor($user), fn (array $column) => $column['visible']));

        $query = $this->eagerLoadForColumns($this->orderedQuery($sort, $direction, $filters), $visibleColumns);
        $projects = $query->skip($offset)->take($take)->get();

        $html = view('projekte.partials.rows', [
            ...$this->rowData($projects, $visibleColumns, $user),
            'columns' => $visibleColumns,
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $filters,
        ])->render();

        return response($html)->header('X-Has-More', $projects->count() === $take ? '1' : '0');
    }

    public function ganttProjects(Request $request): JsonResponse
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = ProjectFilterCatalog::filtersFromRequest($request);
        $query = $this->orderedQuery($sort, $direction, $filters);
        $limit = Setting::ganttMaxProjects();
        $count = $query->count();

        if ($count > $limit) {
            return response()->json([
                'code' => 'project_limit_exceeded',
                'message' => __(':count Projekte gefunden. Bitte den Projektfilter so einstellen, dass höchstens :limit Projekte angezeigt werden, oder die max. Projektanzahl in der Gantt-Anzeige durch einen Admin ändern lassen.', [
                    'count' => $count,
                    'limit' => $limit,
                ]),
            ], 422);
        }

        $projects = $query
            ->get(['projects.id', 'projects.source_pn', 'projects.title', 'projects.start_date', 'projects.end_date']);

        return response()->json(['projects' => $projects]);
    }

    private function eagerLoadForColumns(Builder $query, array $visibleColumns): Builder
    {
        // "Projektverbund" (Ralf, 2026-09-14): Haupt-/Unterprojekt-Icon in
        // der PN-Zelle ist immer sichtbar, keine abwählbare Spalte - deshalb
        // unconditional statt an eine Spalten-Sichtbarkeit gekoppelt.
        $query->with('hauptprojekt');

        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'markets')) {
            $query->with('markets');
        }
        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'workflow')) {
            $query->with('workflow');
        }
        if (array_any($visibleColumns, fn (array $column) => in_array($column['key'], ['progress', 'workflow'], true))) {
            $query->with('projectWorkflowSteps.workflowStep');
        }
        if (array_any($visibleColumns, fn (array $column) => in_array($column['key'], ['system_model', 'product_group_number', 'product_group_name'], true))) {
            $query->with('products.productGroup');
        }
        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'project_groups')) {
            $query->with(['projectGroups' => fn ($q) => $q->visibleTo(Auth::user())->orderBy('name')]);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array{favoriteProjectIds: array, graphicOrderSummaries: Collection, directoryStatuses: array}
     */
    private function rowData(Collection $projects, array $visibleColumns, $user): array
    {
        $graphicOrderSummaries = array_any($visibleColumns, fn (array $column) => $column['key'] === 'graphic_orders_summary')
            ? $this->graphicOrderSummaries($projects->pluck('id'))
            : collect();

        // Stamm-Version (Kettenposition): eine Abfrage für die ganze Seite statt je Zeile.
        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'stamm_version')) {
            Project::preloadStammInfo($projects);
        }

        // Verzeichnis-Symbol zeigt das erste für diesen Nutzer sichtbare
        // Verzeichnis (AV, sonst SV nur mit Recht, sonst gar keins).
        $directorySources = $this->directoryLocator->visibleSources(CurrentTenant::id(), $user);
        $directorySource = $directorySources[0] ?? null;

        // Zeigt das Symbol das AV, aber der Ordner liegt (noch) nur im SV: Symbol soll dann das
        // SV im Overlay öffnen können (nur mit Recht) - dafür der SV-Status je Zeile.
        $directorySvStatuses = $directorySource === ProjectDirectoryLocator::SOURCE_AV && in_array(ProjectDirectoryLocator::SOURCE_SV, $directorySources, true)
            ? $this->directoryLocator->statusesForProjects($projects, CurrentTenant::id(), ProjectDirectoryLocator::SOURCE_SV)
            : [];

        return [
            'projects' => $projects,
            'favoriteProjectIds' => Favorite::where('user_id', $user->id)->pluck('project_id')->all(),
            'graphicOrderSummaries' => $graphicOrderSummaries,
            'directorySource' => $directorySource,
            'directorySvStatuses' => $directorySvStatuses,
            'directoryStatuses' => $directorySource ? $this->directoryLocator->statusesForProjects($projects, CurrentTenant::id(), $directorySource) : [],
        ];
    }

    /**
     * Schnellsuche-Dropdown (Sidebar): AJAX-Vorschau ab 3 Zeichen, wie in
     * Vietto. Nutzt denselben Feldkatalog wie die "alle Treffer"-Liste
     * (applyQuickSearchTerm), aber ohne Bemerkungen - siehe dortigen
     * Kommentar. Absichtlich nur auf bereits nach Vectory migrierte Felder
     * beschränkt (Vietto durchsucht zusätzlich ODN/CosimaID/VideoPN/
     * DevProjNr/OEM/Bogengröße/verstecktes Modellfeld - die existieren hier
     * noch nicht).
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 3) {
            return response()->json([]);
        }

        $query = Project::query();
        $this->applyQuickSearchTerm($query, $term, includeRemarks: false);

        $projects = $query->orderByDesc('source_pn')->limit(50)->get();

        return response()->json($projects->map(fn (Project $project) => [
            'id' => $project->id,
            'pn' => $project->source_pn,
            'title' => $project->title,
            'status' => $project->status,
            'type_symbol' => $project->project_type_sub_model?->symbol,
            'type_name' => $project->project_type_sub_model?->name,
        ])->all());
    }

    /**
     * X/Y/Z für die "Illustration"-Spalte (Grafikaufträge/erledigt/Grafiken
     * gesamt), analog Viettos get_grafikauftraege()-Helferfunktionen -
     * "Verworfen" zählt in Vietto durchweg nicht mit. "erledigt" ist NICHT
     * gleich Status "Fertig und abgelegt", sondern ob je ein Erledigt-User
     * gesetzt wurde (siehe done_at-Kommentar im Import-Command) - kann bei
     * abweichendem aktuellem Status trotzdem gesetzt sein.
     *
     * @return Collection<int, object{total: int, done: int, images: int}>
     */
    private function graphicOrderSummaries(Collection $projectIds): Collection
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        $nonDiscardedStatusValues = array_map(fn ($status) => $status->value, array_filter(GraphicOrderStatus::cases(), fn ($status) => ! $status->isDiscarded()));

        return GraphicOrder::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('graphic_order_status_id', $nonDiscardedStatusValues)
            ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN done_at IS NOT NULL THEN 1 ELSE 0 END) as done, GREATEST(SUM(image_count), 0) as images')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');
    }

    /**
     * Inhalt des "Projekt anlegen"-Modals (Fetch-Muster wie
     * activate-workflow-step) - zeigt die live vorgeschlagene PN (reine
     * Anzeige, keine Reservierung, siehe ProjectNumberAllocator) und die
     * "mich als Beteiligten eintragen"-Checkbox nur, wenn der anlegende
     * Nutzer überhaupt eine Person mit Funktionsgruppen-Mitgliedschaft ist.
     */
    public function createForm(Request $request): View
    {
        abort_unless($request->user()->can('project.create'), 403);

        $tenantId = CurrentTenant::id();
        $year = (int) now()->format('y');

        // Projektbeteiligung hängt im Datenmodell immer an einer
        // Funktionsgruppen-Rolle (siehe store()) - die Checkbox nur zeigen,
        // wenn der Ersteller in DIESEM Kunden überhaupt einer Gruppe
        // angehört, sonst würde sie angehakt trotzdem nichts bewirken (Ralfs
        // Bug-Report: Projekt bei einem Kunden angelegt, bei dem er in
        // keiner Funktionsgruppe ist - Checkbox tat scheinbar nichts).
        return view('projekte.partials.create-body', [
            'suggestedPn' => $this->numberAllocator->nextFreePn($year, $tenantId),
            'canAddCreatorAsParticipant' => $request->user()->person?->functionGroups()->exists() ?? false,
            // Projektart ist beim Anlegen Pflicht (Ralf, 2026-09-21): Geltung von Feldern und Vorbelegungen hängt an ihr.
            'projectTypeCategories' => ProjectTypeMain::query()->where('tenant_id', $tenantId)->orderBy('sort')->with(['subs' => fn ($query) => $query->orderBy('sort')])->get(),
        ]);
    }

    /**
     * Legt ein neues, leeres Projekt an - bewusst kein Assistent/Kopieren-
     * Modus wie Vietto (siehe Roadmap-Entscheidung), nur Titel + PN + (auf
     * Wunsch) Ersteller als Projektbeteiligter. Workflow/Markt/Projektart
     * werden bewusst NICHT hier gesetzt, sondern erst danach in den
     * Projektdetails - genau wie bei einem frischen Vietto-Projekt auch.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('project.create'), 403);

        $tenantId = CurrentTenant::id();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_type_sub_id' => ['required', 'integer', Rule::exists('project_type_subs', 'id')->where('tenant_id', $tenantId)],
        ]);

        $typeSub = ProjectTypeSub::query()->where('tenant_id', $tenantId)->findOrFail($validated['project_type_sub_id']);
        $year = (int) now()->format('y');
        $addAsParticipant = $request->boolean('add_as_participant');
        $creator = $request->user()->person;

        $project = DB::transaction(function () use ($validated, $typeSub, $tenantId, $year, $addAsParticipant, $creator) {
            // Zeilen-Lock auf den Mandanten als Mutex - serialisiert
            // gleichzeitiges Anlegen für denselben Kunden, damit zwei
            // Nutzer nie dieselbe PN vorgeschlagen bekommen UND zugewiesen
            // erhalten (die Live-Vorschau im Dialog selbst ist bewusst
            // unlocked, siehe ProjectNumberAllocator-Docblock).
            Tenant::query()->where('id', $tenantId)->lockForUpdate()->first();

            $project = Project::query()->create([
                'tenant_id' => $tenantId,
                'source_pn' => $this->numberAllocator->nextFreePn($year, $tenantId),
                'title' => trim($validated['title']),
                'project_type_sub_id' => $typeSub->id,
                'project_type_main_id' => $typeSub->project_type_main_id,
                // Ralf, 2026-09-20: ein neu angelegtes Projekt ist eine Neuerstellung.
                'creation_type' => 1,
            ]);

            // Vorbelegung (Ralf, 2026-09-21, "Weitere Optionen" am Zusatzfeld): die Projektart steht beim Anlegen fest,
            // es greifen alle Vorbelegungen der für diese Art geltenden Felder (z.B. Kundenversion = 1).
            $defaults = Attribute::defaultsFor($tenantId, $typeSub->id);
            if ($defaults !== []) {
                $project->update(['attributes' => $defaults]);
            }

            Activity::log($project, ActivityType::ProjectCreated, __('Projekt neu angelegt.'));

            if ($addAsParticipant && $creator) {
                foreach ($creator->functionGroups as $functionGroup) {
                    ProjectPerson::query()->create([
                        'tenant_id' => $tenantId,
                        'project_id' => $project->id,
                        'function_group_id' => $functionGroup->id,
                        'person_id' => $creator->id,
                        'is_primary' => true,
                    ]);
                }
            }

            $basePath = $this->directoryLocator->basePath($tenantId);
            if ($basePath !== null && is_dir($basePath)) {
                $this->directoryLocator->create($basePath, $this->directoryLocator->suggestedFolderName($project));
            }

            return $project;
        });

        return response()->json(['id' => $project->id]);
    }

    /**
     * Inhalt des "Projektanfrage"-Modals - für Nutzer ohne project.create,
     * siehe Migration fix_project_create_and_add_project_request_permission.
     */
    public function requestForm(Request $request): View
    {
        abort_unless($request->user()->can('project.request'), 403);

        return view('projekte.partials.request-body');
    }

    /**
     * Verschickt die Projektanfrage als Mail an die für den aktiven Kunden
     * hinterlegte Info-E-Mail (Admin > Stammdaten oder Admin > Kunden, je
     * nach Mandantenfähigkeit, siehe SystemSetting::tenantConfigLocation())
     * - legt selbst KEIN Projekt
     * an, das übernimmt die TR nach Rücksprache manuell über "Projekt neu
     * anlegen". Ralf-Vorbild: Vietto verschickt genau so eine formlose
     * Anfrage-Mail statt selbst einen Datensatz anzulegen.
     */
    public function submitRequest(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('project.request'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'model_or_system' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $requester = $request->user()->person;
        abort_if($requester === null, 422, __('Ihr Konto ist keiner Person zugeordnet.'));

        $tenant = Tenant::query()->where('id', CurrentTenant::id())->first();
        abort_if($tenant?->notification_email === null, 422, __('Für diesen Kunden ist noch keine Info-E-Mail hinterlegt (:location).', ['location' => SystemSetting::tenantConfigLocation()]));

        Mail::to($tenant->notification_email)->send(new ProjectRequestMail(
            $requester,
            trim($validated['title']),
            isset($validated['model_or_system']) ? trim($validated['model_or_system']) : null,
            isset($validated['due_date']) ? \Illuminate\Support\Carbon::parse($validated['due_date'])->format('d.m.Y') : null,
            isset($validated['remarks']) ? trim($validated['remarks']) : null,
        ));

        return response()->json(['sent' => true]);
    }

    /**
     * Direkt aufrufbar (z.B. Link aus einer Status-E-Mail) -> volle Seite.
     * Aufruf aus dem Tool selbst (Klick auf eine PN) -> nur der Formular-Teil
     * fürs Overlay (erkannt am X-Overlay-Header).
     */
    public function show(Request $request, Project $project): View|Response
    {
        abort_unless($request->user()->can('project.view'), 403);

        RecentlyViewedProject::record($project, $request->user());

        $data = $this->detailData($request, $project);

        if ($this->isOverlayRequest($request)) {
            return response()->view('projekte.partials.detail', [...$data, 'overlay' => true]);
        }

        return view('projekte.show', $data);
    }

    /**
     * Lädt NUR "Projektbeteiligte Personen" neu (Ralf, 2026-09-12: eine
     * über den WFS-Personen-Picker hinzugefügte Person landet sofort in
     * project_people, siehe ProjectWorkflowStepController::updatePeople() -
     * dieses Feld sitzt aber in einem anderen Tab und gleicht sich sonst
     * nicht von selbst ab, gleiches Live-Abgleich-Muster wie bei den
     * Bemerkungen/Änderungsprotokoll-Boxen).
     */
    public function peopleField(Request $request, Project $project): View
    {
        abort_unless($request->user()->can('project.view'), 403);

        $project->loadMissing(['projectPeople.person', 'projectPeople.functionGroup', 'functionGroupHours', 'projectTemplate.functionGroups']);

        return view('projekte.partials.system-fields.project_people', [
            'project' => $project,
            'allFunctionGroups' => $this->functionGroupsWithEligibleMembers($project, $request),
            'availableWorkflows' => $this->availableWorkflows($project),
            'secondaryBtn' => 'inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover',
        ]);
    }

    /**
     * Ralf, 2026-09-19: kleiner Info-Button neben dem Projektschablone-
     * Pulldown in den Projektdetails - zeigt die Merkmale/Stunden der
     * gewählten Schablone rein lesend im globalen Fetch-Overlay (gleiches
     * Prinzip wie das Projekt-Overlay selbst). Mandantengrenze kommt schon
     * über ProjectTemplates BelongsToTenant-Scope beim Route-Model-Binding.
     */
    public function projectTemplateInfo(Request $request, ProjectTemplate $projectTemplate): View
    {
        abort_unless($request->user()->can('project.view'), 403);

        $projectTemplate->loadMissing(['workflow', 'functionGroups']);

        return view('projekte.partials.project-template-info', [
            'template' => $projectTemplate,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse|Response
    {
        abort_unless($request->user()->can('project.edit'), 403);

        // Alle drei Bereiche zusammen: typspezifische (nach Projektart
        // gefiltert) + Stammdaten/Ablaufdaten-Zusatzattribute (gelten
        // immer, siehe Project::sectionAttributes()). "format" ist zwar
        // typspezifisch, aber ein reines System-Feld mit eigenen Spalten
        // (paper_format_combination_id/input_format_free_text/
        // output_format_free_text) statt des generischen attributes-JSON -
        // dieselbe Ausnahme wie bei den System-Feldern der anderen beiden
        // Bereiche (siehe Project::customSectionAttributes()).
        $relevantAttributes = $project->relevantAttributes()
            ->filter(fn (Attribute $attribute) => ! $attribute->system || $attribute->label_editable)
            ->concat($project->customSectionAttributes(Attribute::SECTION_STAMMDATEN))
            ->concat($project->customSectionAttributes(Attribute::SECTION_ABLAUFDATEN));
        $isOverlay = $this->isOverlayRequest($request);

        // Sprechende Feldnamen in Fehlermeldungen (z.B. Pflichtfeld: "Das Feld Auflage ist erforderlich.").
        $attributeNames = $relevantAttributes->mapWithKeys(fn (Attribute $attribute) => ['attributes.'.$attribute->key => $attribute->label])->all();

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'project_type_sub_id' => ['nullable', 'integer', Rule::exists('project_type_subs', 'id')->where('tenant_id', $project->tenant_id)],
            'project_template_id' => ['nullable', 'integer', Rule::exists('project_templates', 'id')->where('tenant_id', $project->tenant_id)],
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'creation_type' => ['nullable', 'integer', 'in:1,2'],
            'archived' => ['boolean'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->where('tenant_id', $project->tenant_id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'publication_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'input_format_free_text' => ['nullable', 'string', 'max:30'],
            'output_format_free_text' => ['nullable', 'string', 'max:30'],
            'markets' => ['array'],
            'markets.*' => ['integer', Rule::exists('markets', 'id')->where('tenant_id', $project->tenant_id)],
            'project_people' => ['array'],
            'project_people.*' => ['array'],
            'project_people.*.*' => ['integer', Rule::exists('people', 'id')->where(
                fn ($query) => $query->where('tenant_id', $project->tenant_id)
                    ->orWhereIn('id', DB::table('person_tenant')->where('tenant_id', $project->tenant_id)->pluck('person_id'))
            )],
            'project_people_hours' => ['array'],
            'project_people_hours.*' => ['array'],
            'project_people_hours.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'project_people_primary' => ['array'],
            'project_people_primary.*' => ['nullable', 'integer'],
            'attributes' => ['array'],
            ...$this->attributeValidationRules($relevantAttributes),
        ], [], $attributeNames);

        if ($validator->fails()) {
            if ($isOverlay) {
                // Ralf, 2026-09-21: bei einem Validierungsfehler (z.B. leeres Pflichtfeld) dürfen die bereits
                // eingegebenen Werte nicht verloren gehen. Anders als beim normalen Redirect gibt es hier keinen
                // Folge-Request, der old() füllt - die Eingaben werden deshalb nur für DIESE Antwort bereitgestellt.
                $request->session()->now('_old_input', $request->all());

                return response()
                    ->view('projekte.partials.detail', [
                        ...$this->detailData($request, $project),
                        'overlay' => true,
                        'errors' => $validator->errors(),
                    ])
                    ->setStatusCode(422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Auf Beendet/Verworfen setzen braucht project.complete - bei
        // Projekten mit aktuellem WFS-Schritt läuft das normalerweise über
        // activate() (siehe ProjectWorkflowStepController), das Statusfeld
        // hier ist dann nur ein schreibgeschütztes Hidden-Feld; die Prüfung
        // greift trotzdem als zweite Absicherung gegen manipulierte Requests.
        if (in_array($validated['status'], [2, 3], true) && $validated['status'] !== $project->status) {
            abort_unless($request->user()->can('project.complete'), 403);
        }

        $validated['archived'] = $request->boolean('archived');
        // Projektkategorie/-art (Ralf: "fehlt noch bei den Stammdaten") -
        // project_type_main_id trägt keine eigene Formularauswahl, sondern
        // wird aus der gewählten Unterart abgeleitet (wie project_type_sub()
        // das für die Anzeige schon tut).
        if (array_key_exists('project_type_sub_id', $validated)) {
            $validated['project_type_main_id'] = $validated['project_type_sub_id']
                ? ProjectTypeSub::query()->where('tenant_id', $project->tenant_id)->find($validated['project_type_sub_id'])?->project_type_main_id
                : null;
        }
        $marketIds = $validated['markets'] ?? [];
        $projectPeopleInput = $validated['project_people'] ?? [];
        $projectPeopleHoursInput = $validated['project_people_hours'] ?? [];
        $primaryInput = $validated['project_people_primary'] ?? [];
        unset($validated['markets'], $validated['project_people'], $validated['project_people_hours'], $validated['project_people_primary']);

        // Nur die für dieses Projekt relevanten Attribute überschreiben (Typspezifisch nach
        // Projektart gefiltert + Stammdaten/Ablaufdaten immer dabei), Rest im JSON unangetastet lassen.
        $attributes = $project->attributes ?? [];
        $oldAttributes = $attributes;
        $changedAttributes = [];
        foreach ($relevantAttributes as $attribute) {
            $key = $attribute->key;

            // Checkbox: bei Nicht-Ankreuzen fehlt das Feld im Request
            // komplett (kein leerer String) - genau wie beim "archived"-Feld
            // oben zählt das als "false", nie als "unverändert lassen".
            if ($attribute->data_type === Attribute::DATA_TYPE_BOOLEAN) {
                $attributes[$key] = $request->boolean("attributes.$key");

                continue;
            }

            $value = $validated['attributes'][$key] ?? null;
            if ($attribute->data_type === Attribute::DATA_TYPE_SELECT && $attribute->multiple) {
                $value = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''));
            }

            if ($value === null || $value === '' || $value === []) {
                unset($attributes[$key]);
            } else {
                $attributes[$key] = $value;
            }

            if ($attribute->log_changes && ($oldAttributes[$key] ?? null) != ($attributes[$key] ?? null)) {
                $changedAttributes[] = [$attribute, $oldAttributes[$key] ?? null, $attributes[$key] ?? null];
            }
        }
        $validated['attributes'] = $attributes;

        // Start/Ende werden einseitig aus dem als Start/Ende markierten
        // Workflow-Schritt übernommen (ProjectWorkflowStepObserver), sobald
        // ein solcher Schritt existiert - das Feld ist dann im Formular
        // disabled, das hier ist die serverseitige Absicherung gegen einen
        // manipulierten Request (gleiches Muster wie beim Publikationsdatum
        // oben). Einfach unsetten statt abzulehnen: es gibt hier kein
        // fehlendes Recht, das Feld ist schlicht nicht die Datenquelle.
        if ($project->projectWorkflowSteps->contains(fn ($pws) => $pws->isScheduleStepForCurrentWorkflow() && $pws->effectiveIsStart())) {
            unset($validated['start_date']);
        }
        if ($project->projectWorkflowSteps->contains(fn ($pws) => $pws->isScheduleStepForCurrentWorkflow() && $pws->effectiveIsEnd())) {
            unset($validated['end_date']);
        }

        // Publikationsdatum nur mit eigenem Recht änderbar (Ralf: "nur für
        // TR, sie ist schließlich für das Publizieren zuständig") - Feld ist
        // im Formular bei fehlendem Recht disabled, das hier ist die
        // serverseitige Absicherung gegen einen manipulierten Request
        // (gleiches Muster wie project.complete beim Statusfeld oben).
        // Wichtig: request->has() statt validated[]-Zugriff, denn ein
        // disabled-Feld fehlt im Request komplett (kein leerer String) -
        // sonst würde jeder Speichervorgang ohne dieses Recht fälschlich
        // als "Publikationsdatum geändert" erkannt, sobald bereits eines
        // gesetzt ist. Nach Vietto-Vorbild wird jede tatsächliche Änderung
        // protokolliert.
        $publicationDateChanged = $request->has('publication_date')
            && $validated['publication_date'] != $project->publication_date?->format('Y-m-d');
        if ($publicationDateChanged) {
            abort_unless($request->user()->can('project.publication_date.edit'), 403);
        }

        $oldStatus = $project->status;

        $project->update($validated);

        // Ralf, 2026-09-28: bewusste Rückkehr zur Schablonen-Verknüpfung nach "Lösen" (siehe
        // project_template.blade.php, Schloss-UI). Das Aufschließen dort löst noch nichts aus -
        // erst dieses Speichern verwirft die eigenen Planstunden, unabhängig davon, ob die
        // Auswahl sich dabei tatsächlich geändert hat (der bestätigte Hinweis beim Aufschließen
        // ist die eigentliche Freigabe, nicht der gewählte Wert).
        if ($request->boolean('relink_template') && $project->functionGroupHours()->exists()) {
            $discarded = $project->effectivePlannedHours();
            $project->functionGroupHours()->sync([]);

            Activity::log($project, ActivityType::PlannedHoursChanged, $project->project_template_id
                ? __('Planstunden wieder mit der Schablone „:template" verknüpft (eigene Werte, :hours h, verworfen).', [
                    'template' => $project->projectTemplate->name,
                    'hours' => number_format($discarded ?? 0, 2, ',', '.'),
                ])
                : __('Eigene Planstunden (:hours h) verworfen, keine Schablone mehr zugewiesen.', [
                    'hours' => number_format($discarded ?? 0, 2, ',', '.'),
                ]));
        }

        // "Weitere Optionen" am Zusatzfeld (Ralf, 2026-09-21): Felder mit Protokollierung schreiben jede Änderung
        // (alt -> neu) in die Vorgänge.
        foreach ($changedAttributes as [$changedAttribute, $oldValue, $newValue]) {
            Activity::log(
                $project,
                ActivityType::AttributeChanged,
                __(':field von „:old“ auf „:new“ geändert.', [
                    'field' => $changedAttribute->label,
                    'old' => $this->attributeValueForLog($changedAttribute, $oldValue),
                    'new' => $this->attributeValueForLog($changedAttribute, $newValue),
                ])
            );
        }

        // Ralf, 2026-09-20: manuelles Ändern des Status (z.B. auf "Beendet", nur ohne aktuellen
        // Workflow-Schritt möglich) gehört in die Vorgänge. Der Erstellungsstatus wird bewusst NICHT protokolliert.
        if ($project->wasChanged('status')) {
            $statusLabel = fn (int $status) => match ($status) {
                0 => __('Geplant'),
                1 => __('In Bearbeitung'),
                2 => __('Beendet'),
                3 => __('Verworfen'),
                default => __('Unbekannt'),
            };
            Activity::log(
                $project,
                ActivityType::StatusChanged,
                __('Status von „:old“ auf „:new“ geändert.', ['old' => $statusLabel((int) $oldStatus), 'new' => $statusLabel((int) $project->status)])
            );
        }

        if ($publicationDateChanged) {
            Activity::log($project, ActivityType::PublicationDateChanged, $project->publication_date
                ? __('Publikationsdatum auf :date gesetzt.', ['date' => $project->publication_date->format('d.m.Y')])
                : __('Publikationsdatum entfernt.'));
        }

        // Beim (Neu-)Zuweisen eines Workflows die Schritt-Vorlagen einmalig
        // in projekteigene Instanzen kopieren. Schritte eines zuvor
        // zugewiesenen anderen Workflows bleiben unangetastet in der DB
        // stehen (kollidieren nie, da workflow_step_id je Workflow eindeutig
        // ist) - kein Datenverlust beim Wechsel, wie in Vietto.
        if ($project->wasChanged('workflow_id')) {
            // Ralf-Bug-Report, 2026-09-14: nach einem Workflow-Wechsel blieb
            // ein zuvor aktueller Schritt (egal welchen Workflows) als
            // Karteileiche mit is_current=true stehen - project.workflow_id
            // zeigte dann auf einen anderen/keinen Workflow, während der
            // "aktuelle" Schritt noch auf dem alten stand. Gleicher Fix wie
            // in MultichangeController::applyWorkflow(): alten aktuellen
            // Schritt explizit deaktivieren, unabhängig davon, ob überhaupt
            // ein neuer Workflow zugewiesen wird.
            $project->projectWorkflowSteps()->where('is_current', true)->update(['is_current' => false]);

            if ($project->workflow_id) {
                WorkflowStep::query()
                    ->where('workflow_id', $project->workflow_id)
                    ->get()
                    ->each(fn (WorkflowStep $step) => ProjectWorkflowStep::firstOrCreate(
                        ['project_id' => $project->id, 'workflow_step_id' => $step->id],
                        ['tenant_id' => $project->tenant_id, 'sort' => $step->sort]
                    ));

                // Ralf, 2026-09-14: "ich bin eigentlich davon ausgegangen,
                // dass wenn ein neuer WF zugewiesen wird, immer der WFS 'in
                // Planung' aktiviert wird" - gleiches Verhalten wie
                // Multichange und "Projekt kopieren" (ProjectCopyController),
                // hier bisher als einziger der drei Zuweisungs-Wege gefehlt.
                $plannedStep = $project->projectWorkflowSteps()
                    ->whereHas('workflowStep', fn ($query) => $query->where('workflow_id', $project->workflow_id)->where('lifecycle_status', 1))
                    ->first();

                if ($plannedStep) {
                    $plannedStep->update(['is_current' => true, 'started_at' => now()]);
                    $project->update(['status' => 0]);
                }

                Activity::log($project, ActivityType::WorkflowAssigned, __('Workflow ":name" zugewiesen.', ['name' => $project->workflow->name]));
            } else {
                Activity::log($project, ActivityType::WorkflowUnassigned, __('Workflowzuweisung entfernt.'));
            }
        }

        // Geltung nach Projektart (Ralf, 2026-09-20): gilt das Feld "Markt" für die Projektart nicht,
        // fehlt es im Formular - ein Sync mit leerer Liste würde die vorhandenen Märkte löschen.
        // Werte bleiben bei nicht geltenden Feldern erhalten, also dann gar nicht anfassen.
        if ($project->fieldApplies('markets')) {
            $project->markets()->sync(collect($marketIds)->mapWithKeys(fn (int $marketId) => [
                $marketId => ['tenant_id' => $project->tenant_id],
            ])->all());
        }

        // Projektbeteiligte Personen: komplett aus der Formularauswahl neu aufbauen.
        // project.people.manage nur einfordern, wenn sich dabei wirklich
        // etwas ändert - wer nur den Titel o.ä. speichert, braucht dieses
        // Recht nicht extra (analog project.complete beim Statusfeld oben).
        $currentProjectPeople = ProjectPerson::where('project_id', $project->id)->get();
        if (! $request->user()->can('planning.view')) {
            $projectPeopleHoursInput = $currentProjectPeople
                ->groupBy('function_group_id')
                ->map(fn ($rows) => $rows->mapWithKeys(fn (ProjectPerson $row) => [
                    $row->person_id => $row->planned_hours,
                ])->all())
                ->all();
        }
        $currentPeopleByGroup = $currentProjectPeople
            ->groupBy('function_group_id')
            ->map(fn ($rows) => $rows->pluck('person_id')->sort()->values()->all())
            ->all();
        $incomingPeopleByGroup = collect($projectPeopleInput)
            ->map(fn ($personIds) => collect($personIds)->map(fn ($id) => (int) $id)->sort()->values()->all())
            ->all();
        $currentHours = $currentProjectPeople->mapWithKeys(fn (ProjectPerson $row) => [
            $row->function_group_id.'.'.$row->person_id => $row->planned_hours === null ? null : round((float) $row->planned_hours, 2),
        ])->sortKeys();
        $incomingHours = collect($projectPeopleInput)->flatMap(function ($personIds, $groupId) use ($projectPeopleHoursInput) {
            return collect($personIds)->mapWithKeys(function ($personId) use ($groupId, $projectPeopleHoursInput) {
                $value = data_get($projectPeopleHoursInput, $groupId.'.'.$personId);

                return [$groupId.'.'.$personId => $value === null || $value === '' ? null : round((float) $value, 2)];
            });
        })->sortKeys();
        if ($currentPeopleByGroup !== $incomingPeopleByGroup || $currentHours->all() !== $incomingHours->all()) {
            abort_unless($request->user()->can('project.people.manage'), 403);
        }

        $effectiveGroupHours = $project->functionGroupHours->isNotEmpty()
            ? $project->functionGroupHours->pluck('pivot.planned_hours', 'id')
            : ($project->projectTemplate?->functionGroups?->pluck('pivot.planned_hours', 'id') ?? collect());

        // Einzeln statt per Bulk-delete() löschen - ein Bulk-Query löst keine
        // Model-Events aus, das würde den Aufgaben-Rebuild (ProjectPersonObserver)
        // genau dann NICHT anstoßen, wenn alle Personen entfernt werden und
        // keine einzige neue Zeile mehr erzeugt wird (danach fiele kein
        // einziges create() mehr an, das den Sync sonst mit anstößt).
        ProjectPerson::where('project_id', $project->id)->get()->each->delete();
        foreach ($projectPeopleInput as $groupId => $personIds) {
            $explicit = collect($personIds)->mapWithKeys(function ($personId) use ($groupId, $projectPeopleHoursInput) {
                $value = data_get($projectPeopleHoursInput, $groupId.'.'.$personId);

                return [(int) $personId => $value === null || $value === '' ? null : (float) $value];
            });
            $missingIds = $explicit->filter(fn ($value) => $value === null)->keys();
            $remaining = max(0, (float) ($effectiveGroupHours->get((int) $groupId) ?? 0) - (float) $explicit->filter(fn ($value) => $value !== null)->sum());
            $suggestedHours = $missingIds->isNotEmpty() && $remaining > 0 ? round($remaining / $missingIds->count(), 2) : null;
            foreach ($personIds as $personId) {
                ProjectPerson::create([
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'function_group_id' => $groupId,
                    'person_id' => $personId,
                    'is_primary' => (int) ($primaryInput[$groupId] ?? null) === (int) $personId,
                    'planned_hours' => $explicit->get((int) $personId) ?? $suggestedHours,
                ]);
            }
        }

        // Wechselwirkung in die andere Richtung (Ralf, 2026-09-12,
        // Screenshot-Bug-Report): wird eine Person hier komplett aus einer
        // Funktionsgruppe entfernt, muss sie auch aus jedem Schritt-Override
        // (project_workflow_step_people) dieser Gruppe verschwinden - sonst
        // bleibt sie an genau dem Schritt "hängen", an dem sie mal per
        // WFS-Picker explizit zugewiesen wurde, obwohl sie projektweit
        // gerade entfernt wurde (wirkte wie eine Karteileiche). Einzeln
        // statt Bulk-delete(), damit ProjectWorkflowStepPersonObserver
        // (Aufgaben-Rebuild) pro Zeile feuert.
        foreach ($currentPeopleByGroup as $groupId => $oldPersonIds) {
            $removedPersonIds = array_diff($oldPersonIds, $incomingPeopleByGroup[$groupId] ?? []);
            if (empty($removedPersonIds)) {
                continue;
            }

            ProjectWorkflowStepPerson::query()
                ->where('function_group_id', $groupId)
                ->whereIn('person_id', $removedPersonIds)
                ->whereHas('projectWorkflowStep', fn ($query) => $query->where('project_id', $project->id))
                ->get()
                ->each->delete();
        }

        return $this->respondAfterSave($request, $project);
    }

    /**
     * Antwort nach erfolgreichem Speichern des Projekt-Detailformulars - im
     * Overlay-Kontext muss der Detail-Partial zurückkommen, sonst fängt der
     * globale Submit-Interceptor (siehe layouts/app.blade.php) die Antwort
     * ab und kippt eine komplette Seite ins Overlay-DIV statt des
     * erwarteten Fragments. Ein normales redirect()/back() funktioniert
     * hier NICHT, weil fetch() den X-Overlay-Header beim automatischen
     * Folgen eines 302 nicht mitschickt.
     */
    private function respondAfterSave(Request $request, Project $project): RedirectResponse|Response
    {
        if ($this->isOverlayRequest($request)) {
            return response()->view('projekte.partials.detail', [
                ...$this->detailData($request, $project->fresh()),
                'overlay' => true,
                'justSaved' => true,
            ]);
        }

        return redirect()->route('projekte.show', $project)->with('status', 'project-updated');
    }

    /**
     * @return array{project: Project, attributes: Collection, sort: ?string, direction: string, filters: array, previousProject: ?Project, nextProject: ?Project}
     */
    private function detailData(Request $request, Project $project): array
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = ProjectFilterCatalog::filtersFromRequest($request);
        $directorySources = $this->directoryLocator->visibleSources($project->tenant_id, $request->user());
        $directorySource = $directorySources[0] ?? null;
        $directoryStatus = $directorySource ? $this->directoryLocator->statusForProject($project, $directorySource) : null;

        // Zeigt das Symbol das AV und der Ordner fehlt dort, ist das normal (kommt erst per
        // Auschecken). Fehlt er aber AUCH im SV und darf der Nutzer das SV sehen, bietet das
        // Symbol an, den Ordner im SV anzulegen (Ralf, 2026-09-26).
        // Liegt er dagegen schon im SV, öffnet das Symbol das SV im Overlay.
        $svStatus = $directorySource === ProjectDirectoryLocator::SOURCE_AV
            && $directoryStatus['status'] === 'not_found'
            && in_array(ProjectDirectoryLocator::SOURCE_SV, $directorySources, true)
            ? $this->directoryLocator->statusForProject($project, ProjectDirectoryLocator::SOURCE_SV)['status']
            : null;
        $directoryCreateInSv = $svStatus === 'not_found';
        $directoryOpenSv = $svStatus === 'found';

        $project->loadMissing(['hauptprojekt', 'unterprojekte' => fn ($query) => $query->orderBy('source_pn'), 'markets', 'projectPeople.person.calendarEntries', 'projectPeople.functionGroup', 'functionGroupHours', 'projectTemplate.functionGroups', 'workflow', 'activities.user', 'projectWorkflowSteps.workflowStep.functionGroups', 'projectWorkflowSteps.people.functionGroup', 'projectWorkflowSteps.people.person', 'graphicOrders.initiatedBy', 'graphicOrders.illustrator', 'projectChecklists.checklist.sections.points', 'projectChecklists.activatedBy', 'projectChecklistPoints.doneBy', 'products.productGroup', 'products.projects:id,source_pn,title']);
        $criticalFindings = in_array((int) $project->status, [0, 1], true)
            ? $this->criticalProjectEvaluator->evaluate(
                $project,
                (float) DB::table('job_hours')->where('project_id', $project->id)->sum('hours'),
            )
            : collect();

        return [
            'project' => $project,
            'criticalFindings' => $criticalFindings,
            // Für den "Checklisten auswählen"-Dialog - der aktuell zugewiesene
            // Katalog, gleiches Prinzip wie bei availableWorkflows unten (auch
            // inaktive Checklisten bleiben sichtbar, wenn schon zugewiesen).
            'allChecklists' => Checklist::query()->where('tenant_id', $project->tenant_id)
                ->where(fn (Builder $query) => $query->where('active', true)->orWhereIn('id', $project->projectChecklists->pluck('checklist_id')))
                ->orderBy('sort')->get(),
            'attributes' => $project->relevantAttributes(),
            'stammdatenAttributes' => $project->sectionAttributes(Attribute::SECTION_STAMMDATEN),
            'ablaufdatenAttributes' => $project->sectionAttributes(Attribute::SECTION_ABLAUFDATEN),
            'projectTypeCategories' => ProjectTypeMain::query()->where('tenant_id', $project->tenant_id)->orderBy('sort')->with(['subs' => fn ($query) => $query->orderBy('sort')])->get(),
            'allMarkets' => Market::query()->where('tenant_id', $project->tenant_id)->orderBy('sort')->get(),
            'marketSets' => MarketSet::query()->where('tenant_id', $project->tenant_id)->with('markets:id')->orderBy('sort')->get(),
            'allFunctionGroups' => $this->functionGroupsWithEligibleMembers($project, $request),
            // Der aktuell zugewiesene Workflow muss immer in der Liste auftauchen, auch wenn er
            // inzwischen inaktiv/ersetzt ist - sonst würde ein Speichern ohne bewusste Auswahl
            // den Workflow fälschlich entfernen, weil kein <option> mehr dazu passt.
            'availableWorkflows' => $this->availableWorkflows($project),
            // Gleiches Prinzip wie bei availableWorkflows oben - die aktuell
            // zugewiesene Schablone muss auch inaktiv in der Liste bleiben.
            'availableProjectTemplates' => ProjectTemplate::query()
                ->where('tenant_id', $project->tenant_id)
                ->where(fn (Builder $query) => $query->where('active', true)->orWhere('id', $project->project_template_id))
                ->orderBy('sort')->orderBy('name')
                ->get(),
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $filters,
            'previousProject' => $this->adjacentProject($sort, $direction, $filters, $project, 'previous'),
            'nextProject' => $this->adjacentProject($sort, $direction, $filters, $project, 'next'),
            'directorySource' => $directorySource,
            'directoryStatus' => $directoryStatus,
            'directoryCreateInSv' => $directoryCreateInSv,
            'directoryOpenSv' => $directoryOpenSv,
            'directorySuggestedFolderName' => $this->directoryLocator->suggestedFolderName($project),
            'zeiten' => $this->zeitenData($project),
        ];
    }

    /**
     * Funktionsgruppen des Projekts mit allen dort auswählbaren Personen.
     * Beim Kunden schließt das freigegebene Mitglieder der entsprechenden
     * Heimat-Funktionsgruppe ein, genau wie der Workflow-Personen-Picker.
     *
     * @return Collection<int, FunctionGroup>
     */
    private function functionGroupsWithEligibleMembers(Project $project, Request $request): Collection
    {
        $currentPeopleByGroup = $project->projectPeople->groupBy('function_group_id');

        return FunctionGroup::query()
            ->availableForTenant($project->tenant_id)
            ->orderBy('sort')
            ->get()
            ->each(fn (FunctionGroup $group) => $group->setRelation(
                'members',
                $group->eligibleMembersInTenant($project->tenant_id, $request->user()->role)
                    ->concat(($currentPeopleByGroup->get($group->id) ?? collect())->pluck('person'))
                    ->unique('id')
                    ->sortBy(fn ($person) => [$person->sort, mb_strtolower((string) $person->last_name), mb_strtolower((string) $person->first_name)])
                    ->values(),
            ));
    }

    private function availableWorkflows(Project $project): Collection
    {
        return Workflow::query()
            ->where('tenant_id', $project->tenant_id)
            ->where(fn (Builder $query) => $query->where('active', true)->orWhere('id', $project->workflow_id))
            ->with('steps.functionGroups')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /**
     * Reiter "Zeiten" (Ralf, 2026-09-27, siehe Roadmap-Backlog): Überblick über die am
     * Projekt gebuchten Stunden - am Hauptprojekt zusätzlich je Unterprojekt aufgeschlüsselt,
     * damit man nicht durch jedes Unterprojekt einzeln klicken muss, um zu sehen, wo die per
     * prozentualer Aufteilung verteilten Stunden gelandet sind. Aggregiert über ALLE Personen
     * (Projektleiter-Sicht), nicht nur die eigenen Buchungen wie im Zeiterfassung-Overlay.
     */
    /**
     * Beteiligte Projekt-IDs für die Zeiten-Auswertungen: das Projekt selbst, am
     * Hauptprojekt zusätzlich alle Unterprojekte. Ein "Sammelprojekt" ist dabei
     * nur ein Hauptprojekt mit besonderer Schablonen-Eigenschaft (siehe
     * ProjectTemplate) - keine eigene Verbund-Rolle, braucht also keine
     * Sonderbehandlung hier.
     *
     * @return array<int, int>
     */
    private function projectFamilyIds(Project $project): array
    {
        $ids = [$project->id];
        if ($project->verbund_rolle === 1) {
            $ids = array_merge($ids, DB::table('projects')
                ->where('hauptprojekt_id', $project->id)->where('verbund_rolle', 2)->pluck('id')->all());
        }

        return $ids;
    }

    private function zeitenData(Project $project): array
    {
        $participantIds = $this->projectFamilyIds($project);

        $hoursByProject = DB::table('job_hours')
            ->whereIn('project_id', $participantIds)
            ->select('project_id', DB::raw('SUM(hours) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        // Planstunden (Ralf, 2026-09-27, siehe Roadmap-Backlog): jedes Projekt trägt seine
        // eigenen (Project::effectivePlannedHours()) - im Verbund also NUR dann eigene, wenn
        // dem Unterprojekt selbst eine Schablone/ein eigener Wert zugewiesen wurde. Ohne das
        // bleibt es implizit beim gemeinsamen Plan des Hauptprojekts, das sich hier einfach
        // daraus ergibt, dass nur die HP-Zeile einen Plan zeigt.
        $participants = Project::withoutGlobalScope('tenant')
            ->whereIn('id', $participantIds)
            ->with(['projectTemplate.functionGroups', 'functionGroupHours'])
            ->get()
            ->keyBy('id');

        $orderedIds = $project->verbund_rolle === 1
            ? collect([$project->id])->concat($participants->except($project->id)->sortBy('source_pn')->pluck('id'))
            : collect([$project->id]);

        $perProject = $orderedIds->map(function ($id) use ($participants, $project, $hoursByProject) {
            $p = $participants[$id];
            $hours = (float) ($hoursByProject[$p->id] ?? 0);
            $plan = $p->effectivePlannedHours();

            return [
                'id' => $p->id, 'label' => $p->source_pn, 'title' => $p->title,
                'isHauptprojekt' => $p->id === $project->id && $project->verbund_rolle === 1,
                'hours' => $hours,
                'plan' => $plan,
                'isOverbooked' => $plan !== null && $hours > (float) $plan,
                // Ralf, 2026-09-27: "da ist farblich wenig Unterschied zu erkennen zwischen den
                // Stunden, die noch nach Schablone sind und denen, die schon gelöst sind" -
                // je Zeile mitgeben, damit die "Je Projekt"-Tabelle das kennzeichnen kann.
                'planLinked' => $p->plannedHoursLinkedToTemplate(),
            ];
        });

        $total = (float) $perProject->sum('hours');
        $planRows = $perProject->filter(fn ($row) => $row['plan'] !== null);
        $planTotal = $planRows->isNotEmpty() ? (float) $planRows->sum('plan') : null;
        $projectHoursChartMax = max(1, (float) $perProject->max(
            fn ($row) => max((float) ($row['plan'] ?? 0), (float) $row['hours'])
        ));

        $byJob = DB::table('job_hours')
            ->join('job_types', 'job_types.id', '=', 'job_hours.job_type_id')
            ->whereIn('job_hours.project_id', $participantIds)
            ->select('job_types.id', 'job_types.code', 'job_types.name', DB::raw('SUM(job_hours.hours) as total'))
            ->groupBy('job_types.id', 'job_types.code', 'job_types.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => ($row->code ? $row->code.' – ' : '').$row->name,
                'hours' => (float) $row->total,
            ]);

        // Für den "Lösen"-Editor (Ralf, 2026-09-27: "dadurch habe ich keine Möglichkeit mehr,
        // zu erkennen, aus welchen Stundenpaketen es sich rekrutiert") - der aktuell geltende
        // Stand je Funktionsgruppe, egal ob (noch) aus der Schablone oder schon eigene Zeilen,
        // UND alle über den Workflow relevanten Fktgrp, damit sich auch eine bislang auf 0
        // stehende noch eintragen lässt (gleiches Prinzip wie bei der Schablone selbst).
        $ownBreakdown = $project->functionGroupHours->isNotEmpty()
            ? $project->functionGroupHours->mapWithKeys(fn ($fg) => [(string) $fg->id => (string) $fg->pivot->planned_hours])
            : ($project->projectTemplate?->functionGroups ?? collect())->mapWithKeys(fn ($fg) => [(string) $fg->id => (string) $fg->pivot->planned_hours]);
        $ownRelevantFunctionGroups = $project->relevantFunctionGroups()
            ->concat($project->functionGroupHours)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            'perProject' => $perProject,
            'byJob' => $byJob,
            'total' => $total,
            'isHauptprojekt' => $project->verbund_rolle === 1,
            'planTotal' => $planTotal,
            'projectHoursChartMax' => $projectHoursChartMax,
            'hasDetachedProjectPlan' => $perProject->contains(fn ($row) => $row['plan'] !== null && ! $row['planLinked']),
            'hasOverbookedProject' => $perProject->contains('isOverbooked', true),
            'ownPlan' => $project->effectivePlannedHours(),
            'ownPlanLinked' => $project->plannedHoursLinkedToTemplate(),
            'ownTemplateName' => $project->projectTemplate?->name,
            'ownBreakdown' => $ownBreakdown,
            'ownRelevantFunctionGroups' => $ownRelevantFunctionGroups,
            // "Personen & Tage" wird nur mit dem erweiterten Planungsrecht berechnet
            // (sonst unnötige Query, das Fragment
            // wird im View ohnehin nicht gerendert). auth() statt eines durchgereichten
            // Request, da zeitenData() auch aus plannedHoursEditorResponse() (kein Request-Param)
            // aufgerufen wird.
            'personBreakdownWeek' => CarbonImmutable::today()->startOfWeek(),
            'personBreakdownSort' => 'person',
            'personBreakdown' => auth()->user()?->can('planning.view')
                ? $this->zeitenPersonBreakdownData($project, CarbonImmutable::today()->startOfWeek())
                : null,
            // "Zeitverlauf" startet aggregiert nach Projekten; personenbezogene Modi
            // sind in zeitenGesamtansicht() durch planning.view geschützt.
            'gesamtansicht' => $this->zeitenGesamtansichtData($project, 'project', null, null, false, defaultToOwnPerson: true),
        ];
    }

    /**
     * Zeiten-Tab, "Personen & Tage" - planning.view, bewusst getrennt von project.view:
     * eine Personen×Tage-Aufschlüsselung ist personenbezogen (Leistungskontrolle-
     * Sensibilität, siehe Rechtekonzept-Diskussion), die restliche Zeiten-Ansicht
     * (Summen, kein Personenbezug) bleibt für alle Projektbeteiligten offen.
     * Der Unterreiter selbst erscheint im View nur, wenn dieses Recht vorliegt -
     * kein sichtbares, nur gesperrtes Element für alle anderen.
     */
    public function zeitenPersonBreakdown(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.view'), 403);
        abort_unless($request->user()->can('planning.view'), 403);

        $week = $this->parseIsoWeek($request->query('week'));
        $sortBy = $request->query('sort') === 'project' ? 'project' : 'person';

        return response(view('projekte.partials.zeiten-personen-body', [
            'project' => $project,
            'week' => $week,
            'sortBy' => $sortBy,
            'breakdown' => $this->zeitenPersonBreakdownData($project, $week, $sortBy),
        ])->render());
    }

    /**
     * @return array{isHauptprojekt: bool, days: \Illuminate\Support\Collection, rows: \Illuminate\Support\Collection, dayTotals: array<string, float>, total: float}
     */
    private function zeitenPersonBreakdownData(Project $project, CarbonImmutable $week, string $sortBy = 'person'): array
    {
        $isHauptprojekt = $project->verbund_rolle === 1;
        $participantIds = [$project->id];
        // HP zuerst, dann UP nach PN (Ralf, 2026-09-27, gleiche Reihenfolge wie in
        // der "Je Projekt"-Tabelle/zeitenData()) - eigene Order-Nummer je Projekt-ID
        // für den Sortiermodus "Projekt - Personen" unten.
        $projectOrder = [$project->id => 0];
        // Ralf, 2026-09-28: "bei den Projekten die Bezeichnung" als Tooltip -
        // Titel gleich mit einsammeln, nicht nur die PN.
        $projectInfo = collect([$project->id => (object) ['source_pn' => $project->source_pn, 'title' => $project->title]]);
        if ($isHauptprojekt) {
            $unterprojekte = DB::table('projects')->where('hauptprojekt_id', $project->id)
                ->where('verbund_rolle', 2)->orderBy('source_pn')->get(['id', 'source_pn', 'title']);
            foreach ($unterprojekte as $index => $up) {
                $participantIds[] = $up->id;
                $projectOrder[$up->id] = $index + 1;
                $projectInfo[$up->id] = $up;
            }
        }

        $days = collect(range(0, 6))->map(fn ($offset) => $week->addDays($offset));

        // Ralf, 2026-09-28: "bei den Personen die Abteilung" als Tooltip.
        $entries = DB::table('job_hours')
            ->join('people', 'people.id', '=', 'job_hours.person_id')
            ->leftJoin('departments', 'departments.id', '=', 'people.department_id')
            ->whereIn('job_hours.project_id', $participantIds)
            ->whereBetween('job_hours.work_date', [$week->toDateString(), $week->addDays(6)->toDateString()])
            ->where('job_hours.hours', '>', 0)
            ->get(['job_hours.person_id', 'job_hours.project_id', 'job_hours.work_date', 'job_hours.hours', 'people.first_name', 'people.last_name', 'departments.name as department_name']);

        // Am Hauptprojekt zusätzlich nach Unterprojekt aufgeschlüsselt (Ralf, 2026-09-28:
        // "+ UP beim HP") - dieselbe Person taucht dann pro beteiligtem (Unter-)Projekt
        // in einer eigenen Zeile auf, statt über alle hinweg zusammengefasst zu werden.
        $rows = [];
        foreach ($entries as $entry) {
            $key = $isHauptprojekt ? $entry->person_id.'|'.$entry->project_id : (string) $entry->person_id;
            if (! isset($rows[$key])) {
                $entryProject = $projectInfo->get((int) $entry->project_id);
                $rows[$key] = [
                    'name' => trim($entry->last_name.', '.$entry->first_name, ', '),
                    'departmentName' => $entry->department_name,
                    'projectId' => (int) $entry->project_id,
                    'projectLabel' => $isHauptprojekt ? $entryProject?->source_pn : null,
                    'projectTitle' => $entryProject?->title,
                    'days' => [],
                    'total' => 0.0,
                ];
            }
            $rows[$key]['days'][$entry->work_date] = ($rows[$key]['days'][$entry->work_date] ?? 0) + (float) $entry->hours;
            $rows[$key]['total'] += (float) $entry->hours;
        }

        // German-Kollation statt sortBy() (siehe [[vectory_german_collation_sorting]]) -
        // sonst landen Ä/Ö/Ü/ß hinter Z statt an der richtigen alphabetischen Stelle.
        // Zwei Sortiermodi (Ralf, 2026-09-28): "person" (Standard) = Person zuerst,
        // Projekt nur als Tiebreaker; "project" = Projekt zuerst (HP vor UP nach PN,
        // siehe $projectOrder oben), Person innerhalb des Projekts alphabetisch.
        $collator = new \Collator('de_DE');
        $rows = collect($rows)->values()->sort(function ($a, $b) use ($collator, $projectOrder, $sortBy) {
            if ($sortBy === 'project') {
                return ($projectOrder[$a['projectId']] <=> $projectOrder[$b['projectId']]) ?: $collator->compare($a['name'], $b['name']);
            }

            return $collator->compare($a['name'], $b['name']) ?: ($projectOrder[$a['projectId']] <=> $projectOrder[$b['projectId']]);
        })->values();

        $dayTotals = $days->mapWithKeys(fn ($day) => [$day->toDateString() => 0.0])->all();
        foreach ($rows as $row) {
            foreach ($row['days'] as $date => $hours) {
                $dayTotals[$date] = ($dayTotals[$date] ?? 0) + $hours;
            }
        }

        return [
            'isHauptprojekt' => $isHauptprojekt,
            'days' => $days,
            'rows' => $rows,
            'dayTotals' => $dayTotals,
            'total' => array_sum($dayTotals),
        ];
    }

    /**
     * Zeiten-Tab, Unterreiter "Gesamtansicht" (Ralf, 2026-09-28) - Nachbau der
     * Zeiterfassungs-Übersicht (JobloadOverviewController::index(), Modi "Nach
     * Personen"/"Nach Jobs"), aber auf die Jobs der beteiligten Projekte (HP+UP)
     * eingeschränkt und über deren Laufzeit statt über ein Kalenderjahr, plus ein
     * dritter, hier eigener Modus "Nach Projekten" (ergibt nur im Projekt-Kontext
     * Sinn, deshalb nicht im Hauptmenü). Modus "Projekte" zeigt nie Personen,
     * Modus "Personen" mit einer gewählten Person bzw. Modus "Jobs" beim
     * Draufklicken auf einen einzelnen Job zeigen personenbezogene Zeilen.
     */
    public function zeitenGesamtansicht(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.view'), 403);
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['project', 'person', 'job'])],
            'person_id' => ['nullable', 'integer'],
            'job_id' => ['nullable', 'integer'],
        ]);
        $mode = $data['mode'] ?? 'project';
        $showInactive = $request->boolean('show_inactive');
        $canViewPeople = $request->user()->can('planning.view');
        if (! $canViewPeople) {
            $mode = in_array($mode, ['project', 'job'], true) ? $mode : 'project';
            $data['person_id'] = null;
            $data['job_id'] = null;
            $showInactive = false;
        }

        return response(view('projekte.partials.zeiten-gesamt-body', [
            'project' => $project,
            'canViewPeople' => $canViewPeople,
            // defaultToOwnPerson=false: ein Reload aus der Filterleiste trägt den
            // zuletzt gültigen Stand (auch "– Alle –" = null) immer explizit weiter,
            // siehe zeitenGesamtansichtData().
            'gesamt' => $this->zeitenGesamtansichtData($project, $mode, $data['person_id'] ?? null, $data['job_id'] ?? null, $showInactive),
        ])->render());
    }

    /**
     * @return array<string, mixed>
     */
    private function zeitenGesamtansichtData(Project $project, string $mode, ?int $personId, ?int $jobId, bool $showInactive, bool $defaultToOwnPerson = false): array
    {
        $participantIds = $this->projectFamilyIds($project);
        $tenantId = $project->tenant_id;
        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $hourDecimals = match ($timeGrid) { 60 => 0, 30 => 1, default => 2 };

        // Zeitraum: frühestes Start- bis spätestes Enddatum unter den beteiligten
        // Projekten (Ralf, 2026-09-28: "Start ist der erste Tag, der in einem der
        // beteiligten Projekte als Startdatum eingetragen ist, analog das Ende").
        $dates = Project::withoutGlobalScope('tenant')->whereIn('id', $participantIds)->get(['start_date', 'end_date']);
        $starts = $dates->pluck('start_date')->filter();
        $ends = $dates->pluck('end_date')->filter();
        if ($starts->isEmpty() || $ends->isEmpty()) {
            return ['hasRange' => false];
        }
        $start = CarbonImmutable::parse((string) $starts->min())->startOfWeek();
        $end = CarbonImmutable::parse((string) $ends->max())->endOfWeek();

        $collator = new \Collator('de_DE');
        $ownPersonId = (int) (auth()->user()->person_id ?? 0);

        $people = DB::table('people')
            ->whereIn('id', DB::table('job_hours')->select('person_id')->distinct()->whereIn('project_id', $participantIds))
            ->get(['id', 'first_name', 'last_name', 'active']);
        $people = $people->filter(fn ($p) => $showInactive || $p->active || (int) $p->id === $ownPersonId)
            ->sort(fn ($a, $b) => $collator->compare($a->last_name.', '.$a->first_name, $b->last_name.', '.$b->first_name))
            ->values();
        $peopleById = $people->keyBy('id');
        // Ralf, 2026-09-28: "– Alle –" im Personen-Dropdown - personId=null ist ab
        // hier ein GÜLTIGER, expliziter Zustand (Zeilen dann nach Person
        // aufgeschlüsselt statt nach Job), kein "nicht gesetzt" mehr. Nur der
        // allererste, noch nicht interaktive Aufbau (zeitenData()) bekommt
        // defaultToOwnPerson=true und damit einen sinnvollen Startwert statt
        // gleich alle Personen offenzulegen.
        if ($personId !== null && ! $peopleById->has($personId)) {
            $personId = null;
        }
        if ($personId === null && $defaultToOwnPerson) {
            $personId = $peopleById->has($ownPersonId) ? $ownPersonId : (int) ($people->first()->id ?? 0);
        }

        $jobs = DB::table('project_job_types')
            ->join('job_types', 'job_types.id', '=', 'project_job_types.job_type_id')
            ->whereIn('project_job_types.project_id', $participantIds)
            ->where('job_types.tenant_id', $tenantId)
            ->distinct()
            ->orderBy('job_types.code')->orderBy('job_types.name')
            ->get(['job_types.id', 'job_types.code', 'job_types.name']);
        $jobsById = $jobs->keyBy('id');
        $jobId = $jobId !== null && $jobsById->has($jobId) ? $jobId : null;

        $weeks = collect();
        $monthSegments = collect();
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addWeek()) {
            $key = sprintf('%04d-W%02d', $day->isoWeekYear(), $day->isoWeek());
            $month = $day->addDays(3)->format('Y-m');
            $weeks->push(['key' => $key, 'number' => $day->isoWeek(), 'start' => $day, 'end' => $day->addDays(6), 'month' => $month]);
            if ($monthSegments->isNotEmpty() && $monthSegments->last()['key'] === $month) {
                $segment = $monthSegments->pop();
                $segment['count']++;
                $monthSegments->push($segment);
            } else {
                $monthSegments->push(['key' => $month, 'label' => $day->addDays(3)->translatedFormat('F Y'), 'count' => 1]);
            }
        }

        // "Nach Projekten" (Ralf, 2026-09-28): eigene, projektweise Zeilen statt
        // Job-/Personen-Zeilen - deshalb ohne Personen-/Job-Filter, dafür mit
        // fester HP-zuerst-dann-UP-nach-PN-Reihenfolge (gleiches Prinzip wie
        // zeitenData()/zeitenPersonBreakdownData()) statt alphabetischer Sortierung.
        $projectOrder = [$project->id => 0];
        $projectInfo = collect([$project->id => (object) ['source_pn' => $project->source_pn, 'title' => $project->title, 'isHauptprojekt' => $project->verbund_rolle === 1]]);
        $milestonesByProjectWeek = [];
        if ($mode === 'project') {
            $unterprojekte = DB::table('projects')->whereIn('id', $participantIds)->where('id', '!=', $project->id)
                ->orderBy('source_pn')->get(['id', 'source_pn', 'title']);
            foreach ($unterprojekte as $index => $up) {
                $projectOrder[$up->id] = $index + 1;
                $projectInfo[$up->id] = (object) ['source_pn' => $up->source_pn, 'title' => $up->title, 'isHauptprojekt' => false];
            }

            // Meilenstein-Punkte (Ralf, 2026-09-28): je Projekt-Zeile die EIGENEN,
            // aktuell gültigen Workflow-Termine (has_due_date=true, nur die aktuell
            // zugewiesene Workflow-Generation - gleiches Prinzip wie
            // ProjectScheduleController::scheduleStepsFor()), gruppiert nach KW.
            $familyProjects = Project::withoutGlobalScope('tenant')->whereIn('id', $participantIds)
                ->with(['projectWorkflowSteps' => fn ($query) => $query->whereNotNull('due_date')->whereHas('workflowStep', fn ($q) => $q->where('has_due_date', true)), 'projectWorkflowSteps.workflowStep'])
                ->get();
            foreach ($familyProjects as $fp) {
                foreach ($fp->projectWorkflowSteps as $pws) {
                    if ($pws->workflowStep->workflow_id !== $fp->workflow_id) {
                        continue;
                    }
                    $date = CarbonImmutable::parse($pws->due_date);
                    $weekKey = sprintf('%04d-W%02d', $date->isoWeekYear(), $date->isoWeek());
                    $milestonesByProjectWeek[$fp->id][$weekKey][] = [
                        'date' => $date->format('d.m.Y'),
                        'title' => $pws->effectiveMilestoneTitle() ?: $pws->workflowStep->title,
                    ];
                }
            }
        }

        $entries = DB::table('job_hours')
            ->whereIn('project_id', $participantIds)
            ->where('work_date', '>=', $start->toDateString())
            ->where('work_date', '<=', $end->toDateString())
            ->where('hours', '>', 0);
        if ($mode === 'person' && $personId !== null) {
            $entries->where('person_id', $personId);
        } elseif ($mode === 'job' && $jobId !== null) {
            $entries->where('job_type_id', $jobId);
        }

        // rowKind bestimmt, wonach die Zeilen gruppiert werden: "project" (neuer
        // Modus), "person" (Modus "Personen" mit "– Alle –", oder Modus "Jobs" mit
        // einem einzelnen gewählten Job - dann personenbezogen) oder "job" (Modus
        // "Jobs" ohne Auswahl, oder Modus "Personen" mit einer gewählten Person).
        $rowKind = match (true) {
            $mode === 'project' => 'project',
            $mode === 'person' => $personId === null ? 'person' : 'job',
            default => $jobId !== null ? 'person' : 'job',
        };

        $jobOrder = $jobs->pluck('id')->flip();
        $rows = [];
        $weekTotals = $weeks->pluck('key')->mapWithKeys(fn ($key) => [$key => 0.0])->all();
        $total = 0.0;
        foreach ($entries->select('person_id', 'job_type_id', 'project_id', 'work_date', 'hours')->cursor() as $entry) {
            $date = CarbonImmutable::parse($entry->work_date);
            $weekKey = sprintf('%04d-W%02d', $date->isoWeekYear(), $date->isoWeek());
            $rowId = match ($rowKind) {
                'project' => (int) $entry->project_id,
                'person' => (int) $entry->person_id,
                'job' => (int) $entry->job_type_id,
            };
            if (! isset($rows[$rowId])) {
                if ($rowKind === 'project') {
                    $proj = $projectInfo->get($rowId);
                    if (! $proj) {
                        continue;
                    }
                    $label = $proj->source_pn.' – '.$proj->title;
                    $sort = sprintf('%08d', $projectOrder[$rowId] ?? 99999999);
                } elseif ($rowKind === 'person') {
                    $person = $peopleById->get($rowId);
                    if (! $person) {
                        continue;
                    }
                    $label = trim($person->last_name.', '.$person->first_name, ', ');
                    $sort = mb_strtolower($label);
                } else {
                    $job = $jobsById->get($rowId);
                    if (! $job) {
                        continue;
                    }
                    $label = ($job->code ? $job->code.' – ' : '').$job->name;
                    $sort = sprintf('%08d', $jobOrder->get($rowId, 99999999));
                }
                $rows[$rowId] = ['id' => $rowId, 'label' => $label, 'sort' => $sort, 'weeks' => [], 'total' => 0.0];
            }
            $hours = (float) $entry->hours;
            $rows[$rowId]['weeks'][$weekKey] = ($rows[$rowId]['weeks'][$weekKey] ?? 0) + $hours;
            $rows[$rowId]['total'] += $hours;
            $weekTotals[$weekKey] += $hours;
            $total += $hours;
        }
        $rows = collect($rows)->sortBy('sort')->values();

        return [
            'hasRange' => true, 'mode' => $mode, 'rowKind' => $rowKind, 'people' => $people, 'personId' => $personId,
            'showInactive' => $showInactive, 'jobs' => $jobs, 'jobId' => $jobId,
            'weeks' => $weeks, 'monthSegments' => $monthSegments, 'rows' => $rows,
            'weekTotals' => $weekTotals, 'total' => $total, 'hourDecimals' => $hourDecimals,
            'milestonesByProjectWeek' => $milestonesByProjectWeek,
        ];
    }

    private function parseIsoWeek(?string $value): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::today()->startOfWeek();
        }
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches)) {
            return CarbonImmutable::today()->startOfWeek();
        }
        $week = CarbonImmutable::now()->setISODate((int) $matches[1], (int) $matches[2])->startOfWeek();

        return $week->isoWeekYear() === (int) $matches[1] && $week->isoWeek() === (int) $matches[2]
            ? $week
            : CarbonImmutable::today()->startOfWeek();
    }

    /**
     * "Lösen" (Ralf, 2026-09-27, siehe Roadmap-Backlog): kopiert den JETZT gültigen
     * Schablonen-Stand je Funktionsgruppe in eigene, projektgebundene Zeilen - ab da
     * unabhängig von der Schablone (die selbst NIE geändert wird), aber mit derselben
     * Aufschlüsselung als Startpunkt, damit man weiterhin sieht/anpasst, woraus sich die
     * Planstunden zusammensetzen, statt nur einen einzelnen Gesamt-Wert zu bekommen.
     */
    public function breakPlannedHoursLink(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.edit'), 403);
        abort_unless($request->user()->can('planning.view'), 403);
        abort_unless($project->plannedHoursLinkedToTemplate(), 409);

        $templateName = $project->projectTemplate->name;
        $previous = $project->effectivePlannedHours();

        $syncData = $project->projectTemplate->functionGroups->mapWithKeys(fn ($fg) => [
            $fg->id => ['tenant_id' => $project->tenant_id, 'planned_hours' => $fg->pivot->planned_hours],
        ]);
        $project->functionGroupHours()->sync($syncData);

        Activity::log($project, ActivityType::PlannedHoursChanged, __(
            'Planstunden von der Schablone „:template" gelöst (:hours h übernommen) - ab jetzt unabhängig je Funktionsgruppe änderbar.',
            ['template' => $templateName, 'hours' => number_format($previous ?? 0, 2, ',', '.')]
        ));

        return $this->plannedHoursEditorResponse($project->fresh());
    }

    /**
     * Planstunden je Funktionsgruppe speichern (Ralf, 2026-09-27) - nur möglich, wenn die
     * Schablonen-Verbindung bereits gelöst ist (siehe breakPlannedHoursLink() oben). Gleiches
     * Muster wie ProjectTemplateController::updateFunctionGroups(): eine leere/0-Eingabe
     * entfernt die Fktgrp aus dem Plan, statt sie als "0 h" stehen zu lassen.
     */
    public function updatePlannedFunctionGroupHours(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.edit'), 403);
        abort_unless($request->user()->can('planning.view'), 403);
        abort_if($project->plannedHoursLinkedToTemplate(), 409);

        $request->validate(['hours' => ['nullable', 'array'], 'hours.*' => ['nullable', 'numeric', 'min:0', 'max:999']]);

        $previous = $project->effectivePlannedHours();

        $hours = collect($request->array('hours'))
            ->mapWithKeys(fn ($value, $functionGroupId) => [(int) $functionGroupId => $value])
            ->filter(fn ($value) => $value !== null && $value !== '' && (float) $value > 0);

        $validIds = $project->relevantFunctionGroups()
            ->concat($project->functionGroupHours)
            ->pluck('id')->unique()->intersect($hours->keys());

        $syncData = $validIds->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $project->tenant_id, 'planned_hours' => (float) $hours[$id]],
        ]);
        $project->functionGroupHours()->sync($syncData);

        $new = $project->fresh()->effectivePlannedHours();
        Activity::log($project, ActivityType::PlannedHoursChanged, __(
            'Planstunden von :previous h auf :new h geändert.',
            ['previous' => number_format($previous ?? 0, 2, ',', '.'), 'new' => number_format($new ?? 0, 2, ',', '.')]
        ));

        return $this->plannedHoursEditorResponse($project->fresh());
    }

    private function plannedHoursEditorResponse(Project $project): Response
    {
        return response(view('projekte.partials.planned-hours-editor', [
            'project' => $project,
            'zeiten' => $this->zeitenData($project),
        ])->render());
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function sortFromRequest(Request $request): array
    {
        $requested = $request->query('sort');
        $sort = (is_string($requested) && (in_array($requested, self::SORTABLE_COLUMNS, true) || in_array($requested, self::sortableAttributeColumns(), true)))
            ? $requested
            : null;
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return [$sort, $direction];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($key === 'project_person') {
                $query->whereHas('projectPeople', function (Builder $peopleQuery) use ($value) {
                    $peopleQuery->where('person_id', $value['person_id']);
                    if (! empty($value['function_group_id'])) {
                        $peopleQuery->where('function_group_id', $value['function_group_id']);
                    }
                });

                continue;
            }

            if ($key === 'schnellsuche') {
                $this->applyQuickSearchTerm($query, $value);

                continue;
            }

            if (str_starts_with($key, 'attribute:')) {
                $attributeKey = substr($key, strlen('attribute:'));
                $query->where("attributes->{$attributeKey}", 'like', "%{$value}%");

                continue;
            }

            if (in_array($key, self::DATE_RANGE_FIELDS, true)) {
                if (! empty($value['from'])) {
                    $query->whereDate($key, '>=', $value['from']);
                }
                if (! empty($value['to'])) {
                    $query->whereDate($key, '<=', $value['to']);
                }

                continue;
            }

            if (in_array($key, self::BOOL_FIELDS, true)) {
                $query->where($key, (bool) $value);

                continue;
            }

            if ($key === 'source_pn') {
                // Präfix-Suche statt Teilstring - eine PN-Eingabe wie "100"
                // soll nur Projekte finden, deren PN damit ANFÄNGT, nicht
                // irgendwo "100" enthält (sonst treffen z.B. Jahres- und
                // Mittelziffern beliebiger anderer PN mit rein). Wer
                // wirklich einen Teilstring/eine andere Position sucht,
                // kann "*" als Wildcard direkt an die gewünschte Stelle
                // setzen (z.B. "*100" oder "26*01").
                $pattern = str_contains($value, '*') ? str_replace('*', '%', $value) : "{$value}%";
                $query->where('source_pn', 'like', $pattern);

                continue;
            }

            if ($key === 'creation_type') {
                match ((string) $value) {
                    '1', '2' => $query->where('creation_type', (int) $value),
                    'gesetzt' => $query->whereNotNull('creation_type'),
                    'leer' => $query->whereNull('creation_type'),
                    default => null,
                };

                continue;
            }

            if ($key === 'stamm_id') {
                // Gespeichert wird die kanonische Form ohne Trenner - Eingabe
                // wie "apek-ha4c" oder "APEK HA4C" muss deshalb erst
                // normalisiert werden (sonst träfe der Teilstring nie).
                $normalized = StammId::normalize($value);
                if ($normalized !== '') {
                    $query->where('stamm_id', 'like', "%{$normalized}%");
                }

                continue;
            }

            if ($key === 'favorite') {
                $favoritesQuery = fn (Builder $query) => $query->where('user_id', Auth::id());
                (bool) $value ? $query->whereHas('favorites', $favoritesQuery) : $query->whereDoesntHave('favorites', $favoritesQuery);

                continue;
            }

            if ($key === 'project_type') {
                $query->whereIn('project_type_sub_id', $value);

                continue;
            }

            if ($key === 'project_year') {
                $query->where(function (Builder $query) use ($value) {
                    foreach ($value as $year) {
                        $query->orWhere('source_pn', 'like', substr((string) $year, -2).'%');
                    }
                });

                continue;
            }

            if ($key === 'markets') {
                $query->whereHas('markets', fn (Builder $query) => $query->whereIn('markets.id', $value));

                continue;
            }

            if ($key === 'workflow_id') {
                $query->where('workflow_id', $value);

                continue;
            }

            if ($key === 'system_model') {
                $query->whereHas('products', fn (Builder $query) => $query->where('name', 'like', "%{$value}%")->orWhere('product_number', 'like', "%{$value}%"));

                continue;
            }

            if ($key === 'graphic_orders') {
                $openStatusValues = array_map(fn ($status) => $status->value, array_filter(GraphicOrderStatus::cases(), fn ($status) => $status->isOpen()));
                $nonDiscardedStatusValues = array_map(fn ($status) => $status->value, array_filter(GraphicOrderStatus::cases(), fn ($status) => ! $status->isDiscarded()));

                match ($value) {
                    'ohne' => $query->whereDoesntHave('graphicOrders'),
                    'mit' => $query->whereHas('graphicOrders', fn (Builder $query) => $query->whereIn('graphic_order_status_id', $nonDiscardedStatusValues)),
                    'offene' => $query->whereHas('graphicOrders', fn (Builder $query) => $query->whereIn('graphic_order_status_id', $openStatusValues)),
                    default => null,
                };

                continue;
            }

            if ($key === 'status') {
                $query->whereIn('status', $value);

                continue;
            }

            if ($key === 'project_group_id') {
                $query->whereHas('projectGroups', fn (Builder $query) => $query->where('project_groups.id', $value));

                continue;
            }

            if ($key === 'verbund') {
                match ($value) {
                    'ja' => $query->whereNotNull('verbund_rolle'),
                    'nein' => $query->whereNull('verbund_rolle'),
                    'haupt' => $query->where('verbund_rolle', 1),
                    default => null,
                };

                continue;
            }

            if ($key === 'product_group_id') {
                $query->whereHas('products', fn (Builder $query) => $query->where('products.product_group_id', $value));

                continue;
            }

            $query->where($key, 'like', "%{$value}%");
        }
    }

    /**
     * Feldkatalog der Schnellsuche (Dropdown UND "alle Treffer"-Liste,
     * orientiert an Viettos ajax_direktsuche.php): PN als Präfix, alles
     * andere als Teilstring. Projekt-Art wird über die Namen der
     * zugehörigen ProjectTypeSub-Datensätze aufgelöst (project_type_sub_id
     * auf Project trägt nur die ID, nicht den Namen selbst).
     *
     * Bemerkungen ist ein freier Fließtext - Teilstring-Suche darin trifft
     * bei kurzen Begriffen leicht rein zufällig (z.B. "test" in "könntest").
     * Vietto durchsucht dieses Feld deshalb nur in der "alle Treffer"-Liste,
     * nicht im Live-Dropdown - hier per $includeRemarks nachgebildet.
     */
    private function applyQuickSearchTerm(Builder $query, string $term, bool $includeRemarks = true): void
    {
        $typeIds = ProjectTypeSub::query()
            ->where('name', 'like', "%{$term}%")
            ->pluck('id')
            ->all();

        $query->where(function (Builder $query) use ($term, $typeIds, $includeRemarks) {
            $query->where('source_pn', 'like', "{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('codename', 'like', "%{$term}%")
                ->orWhere('attributes->initiator', 'like', "%{$term}%")
                ->orWhere('attributes->system_model', 'like', "%{$term}%")
                ->orWhere('attributes_material_number', 'like', "%{$term}%");

            // Stamm-ID (Ralf, 2026-09-20): auch mit Bindestrichen/Kleinschreibung
            // eingebbar, deshalb auf die kanonische Form normalisiert.
            $stammTerm = StammId::normalize($term);
            if (strlen($stammTerm) >= 4) {
                $query->orWhere('stamm_id', 'like', "%{$stammTerm}%");
            }

            if ($includeRemarks) {
                $query->orWhere('remarks', 'like', "%{$term}%");
            }

            if (! empty($typeIds)) {
                $query->orWhereIn('project_type_sub_id', $typeIds);
            }
        });
    }

    /**
     * Kein sort-Parameter -> fachlich fester Default (start_date, absteigend).
     * Die Übersicht bietet dafür keine asc/desc-Wahl an, also muss die
     * Blätter-Navigation dieselbe feste Richtung verwenden wie die Tabelle.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function effectiveOrder(?string $sort, string $direction): array
    {
        return $sort ? [$sort, $direction] : ['start_date', 'desc'];
    }

    /**
     * Projekt-IDs, die der aktuelle Übersichtsfilter liefert - für "Alle
     * angezeigten Projekte" in ProjectGroupController (siehe dort: bezieht
     * sich bewusst auf ALLE gefilterten Treffer, nicht nur die aktuelle
     * Seite).
     *
     * @return Collection<int, int>
     */
    public function filteredIdsForGroups(array $filters): Collection
    {
        $query = Project::query();
        $this->applyFilters($query, $filters);

        return $query->pluck('id');
    }

    private function orderedQuery(?string $sort, string $direction, array $filters = []): Builder
    {
        [$column, $dir] = $this->effectiveOrder($sort, $direction);

        $query = Project::query();
        [$sortColumn, $idColumn] = $this->resolveSortColumns($query, $column);
        $groupedSortColumn = $this->verbundGroupedSortColumn($column);
        $query->orderByRaw("{$groupedSortColumn} {$dir}")
            // Innerhalb einer Verbund-Gruppe immer Hauptprojekt (0) vor
            // Unterprojekt (1), unabhängig von $dir.
            ->orderByRaw('(projects.verbund_rolle = 2) asc')
            ->orderBy($sortColumn, $dir)
            ->orderBy($idColumn, $dir);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Nächstes/vorheriges Projekt in genau der Reihenfolge, die auch die
     * (ggf. gefilterte) Übersicht für diese Sortierung anzeigt (Keyset-Vergleich, seitenübergreifend).
     */
    private function adjacentProject(?string $sort, string $direction, array $filters, Project $current, string $way): ?Project
    {
        [$direct, $current, $excludeIds] = $this->verbundAdjacentDetour($current, $way);
        if ($direct !== false) {
            return $direct;
        }

        [$column, $primaryDir] = $this->effectiveOrder($sort, $direction);
        $queryDirection = $way === 'next' ? $primaryDir : ($primaryDir === 'asc' ? 'desc' : 'asc');
        $operator = $queryDirection === 'asc' ? '>' : '<';

        $query = Project::query();
        if ($excludeIds->isNotEmpty()) {
            // Normale Berechnung ab der Hauptprojekt-Position (siehe
            // verbundAdjacentDetour()) darf nicht zufällig auf eines der
            // eigenen Unterprojekte zurückspringen, nur weil dessen PN
            // natürlich als Nächstes käme - die wurden ja schon gezeigt.
            $query->whereNotIn('id', $excludeIds);
        }
        [$sortColumn, $idColumn] = $this->resolveSortColumns($query, $column);
        $value = match ($column) {
            'workflow' => $current->workflow?->sort,
            // Start/Ende sind in der Übersicht seit 2026-09-14 eine
            // zusammengeführte Spalte (siehe ProjectColumnCatalog) - sortiert
            // wird weiterhin nach der echten start_date-Spalte.
            'start_end' => $current->start_date,
            default => $current->{self::sortDbColumn($column)},
        };
        $this->applyFilters($query, $filters);

        // MySQL sortiert NULL wie den kleinsten Wert: bei ASC zuerst, bei
        // DESC zuletzt. Ein simpler </>-Vergleich im WHERE hätte NULL-
        // Zeilen aber IMMER ausgeschlossen (jeder Vergleich mit NULL ist in
        // SQL "unbekannt", selbst mit passendem Operator) - dadurch waren
        // Projekte mit leerem Sortierfeld (z.B. kein Start-Datum bei der
        // Standardsortierung "Start" DESC) über "weiter/zurück" nie
        // erreichbar, obwohl sie in der Liste selbst einen klaren Platz
        // haben (Ralf-Bug-Report: Blättern blieb beim vorletzten Projekt
        // stehen). $nullsSortLast folgt genau dieser MySQL-Regel.
        $nullsSortLast = $queryDirection === 'desc';

        return $query
            ->where(function ($query) use ($sortColumn, $idColumn, $operator, $value, $current, $nullsSortLast) {
                if ($value === null) {
                    // Aktuelles Projekt liegt schon in der NULL-Zone (zuletzt bei
                    // DESC, zuerst bei ASC) - "weiter" bleibt zunächst dort (andere
                    // NULL-Zeilen), erst danach kommen echte Werte (nur bei ASC,
                    // da dort die NULL-Zone VOR den echten Werten liegt).
                    $query->where(fn ($query) => $query->whereNull($sortColumn)->where($idColumn, $operator, $current->id));
                    if (! $nullsSortLast) {
                        $query->orWhereNotNull($sortColumn);
                    }

                    return;
                }

                $query->where($sortColumn, $operator, $value)
                    ->orWhere(function ($query) use ($sortColumn, $idColumn, $value, $operator, $current) {
                        $query->where($sortColumn, $value)->where($idColumn, $operator, $current->id);
                    });

                if ($nullsSortLast) {
                    // DESC: NULL-Zeilen sortieren nach JEDEM echten Wert - von
                    // einem echten Wert aus immer gültige "weiter"-Kandidaten.
                    $query->orWhereNull($sortColumn);
                }
            })
            ->orderBy($sortColumn, $queryDirection)
            ->orderBy($idColumn, $queryDirection)
            ->first();
    }

    /**
     * "Projektverbund", einfachste Variante (Ralf, 2026-09-15): statt die
     * eigentliche Sortierung Verbund-fest zu machen, nur ein kleiner Umweg
     * fürs Blättern selbst - von einem Hauptprojekt aus geht's zuerst durch
     * seine eigenen Unterprojekte (PN-Reihenfolge), erst danach normal
     * weiter; von einem Unterprojekt aus zum nächsten Geschwister, am
     * Ende/Anfang weiter an der Position, an der es beim HAUPTPROJEKT
     * sowieso weitergegangen wäre (nicht der eigenen Position des
     * Unterprojekts - die kann ganz woanders in der Sortierung liegen).
     *
     * @return array{0: Project|false, 1: Project} [direktes Ergebnis oder
     *                                             false für "kein Sonderfall", wirksames Ausgangsprojekt für die
     *                                             normale Berechnung, falls kein direktes Ergebnis]
     */
    private function verbundAdjacentDetour(Project $current, string $way): array
    {
        $noExclusion = collect();

        if ($current->verbund_rolle === 1) {
            if ($way === 'next') {
                $firstUnterprojekt = $current->unterprojekte()->orderBy('source_pn')->first();
                if ($firstUnterprojekt) {
                    return [$firstUnterprojekt, $current, $noExclusion];
                }
            }

            // Fällt auf normale Berechnung ab der eigenen (Hauptprojekt-)
            // Position zurück - eigene Unterprojekte dabei ausschließen,
            // falls eines zufällig natürlich als Nächstes/Vorheriges käme.
            return [false, $current, $current->unterprojekte()->pluck('id')];
        }

        if ($current->verbund_rolle === 2) {
            $siblings = Project::query()->where('hauptprojekt_id', $current->hauptprojekt_id)->orderBy('source_pn')->get();
            $index = $siblings->search(fn (Project $p) => $p->id === $current->id);

            if ($way === 'next') {
                if ($index !== false && $siblings->has($index + 1)) {
                    return [$siblings[$index + 1], $current, $noExclusion];
                }

                $hauptprojekt = $current->hauptprojekt;

                return [false, $hauptprojekt ?? $current, $siblings->pluck('id')];
            }

            if ($index !== false && $index > 0) {
                return [$siblings[$index - 1], $current, $noExclusion];
            }

            return [$current->hauptprojekt ?? false, $current, $noExclusion];
        }

        return [false, $current, $noExclusion];
    }

    /**
     * "workflow" sortiert nicht alphabetisch nach Name, sondern nach dem
     * internen Sortierschlüssel des zugewiesenen Workflows (workflows.sort)
     * - dafür nötig: Join + explizite Spaltenqualifizierung (sonst
     * mehrdeutig, da beide Tabellen u.a. "id" haben).
     *
     * @return array{0: string, 1: string} [Sortier-Spalte, id-Spalte]
     */
    /**
     * Spaltenschlüssel ("attribute:xyz") der Zusatzfelder, nach denen die Übersicht sortiert werden
     * kann (Ralf, 2026-09-20): einzeilige Text-, Zahl- und Datumsfelder mit eigener Datenbankspalte.
     * Mehrfachauswahl, Pulldown und Ja/Nein bleiben unsortierbar (Optionswert ist keine sinnvolle Reihenfolge).
     *
     * @return list<string>
     */
    public static function sortableAttributeColumns(): array
    {
        static $cache = [];
        $tenantId = CurrentTenant::id();

        return $cache[$tenantId] ??= Attribute::query()->where('tenant_id', $tenantId)
            ->whereIn('data_type', [Attribute::DATA_TYPE_TEXT, Attribute::DATA_TYPE_TEXTAREA, Attribute::DATA_TYPE_NUMBER, Attribute::DATA_TYPE_DATE])
            ->where(fn ($query) => $query->where('system', false)->orWhere('label_editable', true))
            ->where('key', '!=', 'system_model')
            ->pluck('key')
            ->map(fn (string $key) => 'attribute:'.$key)
            ->all();
    }

    /**
     * Echter Datenbank-Spaltenname für eine Sortierung. Zusatzfelder liegen im JSON und haben eine
     * generierte Spalte (siehe AttributeColumnManager); Zahlenfelder und hochzählende Textfelder
     * ("Kundenversion") sortieren über ihre numerische Sortierspalte, damit "V10" nach "V9" kommt.
     */
    private static function sortDbColumn(string $column): string
    {
        if (! str_starts_with($column, 'attribute:')) {
            return $column;
        }

        $key = substr($column, strlen('attribute:'));
        $manager = app(\App\Services\AttributeColumnManager::class);
        $attribute = Attribute::query()->where('tenant_id', CurrentTenant::id())->where('key', $key)->first();

        if ($attribute && $manager->needsSortColumn($attribute) && \Illuminate\Support\Facades\Schema::hasColumn('projects', $manager->sortColumnName($key))) {
            return $manager->sortColumnName($key);
        }

        return $manager->columnName($key);
    }

    private function resolveSortColumns(Builder $query, string $column): array
    {
        // Start/Ende sind in der Übersicht seit 2026-09-14 eine
        // zusammengeführte Spalte (siehe ProjectColumnCatalog) - sortiert
        // wird weiterhin nach der echten start_date-Spalte.
        if ($column === 'start_end') {
            return ['projects.start_date', 'projects.id'];
        }

        if ($column !== 'workflow') {
            // "projects."-Präfix nötig, seit orderedQuery() (Verbund-
            // Gruppierung) einen Self-Join auf "projects" (Alias
            // verbund_haupt) einführt - ohne Präfix wäre der Spaltenname
            // sonst zwischen beiden Tabellen mehrdeutig.
            return ['projects.'.self::sortDbColumn($column), 'projects.id'];
        }

        $query->leftJoin('workflows', 'workflows.id', '=', 'projects.workflow_id')->select('projects.*');

        return ['workflows.sort', 'projects.id'];
    }

    /**
     * "Projektverbund" (Ralf, 2026-09-14): Unterprojekte sollen in der
     * Übersicht IMMER direkt hinter ihrem Hauptprojekt stehen, unabhängig
     * von der gewählten Sortierspalte - dafür bekommt jede Zeile einen
     * "Gruppen-Sortierschlüssel": bei einem Unterprojekt der Wert SEINES
     * Hauptprojekts, sonst der eigene Wert. Bewusst als korrelierte
     * Subquery statt eines Self-Joins auf "projects" - ein Join würde jede
     * Spalte in applyFilters() (viele unqualifizierte $query->where(...)-
     * Aufrufe) zwischen "projects" und dem Join-Alias mehrdeutig machen und
     * die komplette Filterung brechen. Eine Subquery bleibt vom Rest der
     * Query isoliert.
     */
    private function verbundGroupedSortColumn(string $column): string
    {
        if ($column === 'workflow') {
            return 'COALESCE(
                (SELECT verbund_haupt_workflow.sort FROM projects AS verbund_haupt
                    LEFT JOIN workflows AS verbund_haupt_workflow ON verbund_haupt_workflow.id = verbund_haupt.workflow_id
                 WHERE verbund_haupt.id = projects.hauptprojekt_id),
                workflows.sort
            )';
        }

        $plainColumn = $column === 'start_end' ? 'start_date' : self::sortDbColumn($column);

        return "COALESCE(
            (SELECT verbund_haupt.{$plainColumn} FROM projects AS verbund_haupt WHERE verbund_haupt.id = projects.hauptprojekt_id),
            projects.{$plainColumn}
        )";
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    /**
     * @param  Collection<int, Attribute>  $attributes
     */
    /**
     * Lesbarer Feldwert für den Vorgänge-Eintrag: Optionslabel statt Wert, Ja/Nein, Mehrfachauswahl kommagetrennt,
     * leer als "leer".
     */
    private function attributeValueForLog(Attribute $attribute, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return __('leer');
        }

        if ($attribute->data_type === Attribute::DATA_TYPE_BOOLEAN) {
            return $value ? __('Ja') : __('Nein');
        }

        if ($attribute->data_type === Attribute::DATA_TYPE_SELECT) {
            $labels = $attribute->options->pluck('label', 'value');

            return collect((array) $value)->map(fn ($v) => $labels[$v] ?? $v)->implode(', ');
        }

        return (string) $value;
    }

    private function attributeValidationRules($attributes): array
    {
        $rules = [];

        foreach ($attributes as $attribute) {
            $key = 'attributes.'.$attribute->key;

            $rules[$key] = match ($attribute->data_type) {
                // Ralf, 2026-09-15: Zahl-Attribute können optional Mindest-/
                // Höchstwert + Dezimalstellen tragen (Attribute::number_min/
                // .../number_decimals) - hier in echte Validierungsregeln
                // übersetzt, statt nur als reine Anzeige-/Formular-Hinweise
                // zu existieren.
                Attribute::DATA_TYPE_NUMBER => array_filter([
                    'nullable',
                    'numeric',
                    $attribute->number_min !== null ? 'min:'.$attribute->number_min : null,
                    $attribute->number_max !== null ? 'max:'.$attribute->number_max : null,
                    $attribute->number_decimals !== null ? 'decimal:0,'.$attribute->number_decimals : null,
                ]),
                Attribute::DATA_TYPE_DATE => ['nullable', 'date'],
                Attribute::DATA_TYPE_BOOLEAN => ['boolean'],
                // Ralf, 2026-09-15: Max. Textlänge (Attribute::max_length)
                // optional konfigurierbar - unverändertes Verhalten, wenn
                // leer (Text weiter max. 255, Textarea weiter unbegrenzt).
                Attribute::DATA_TYPE_TEXT => ['nullable', 'string', 'max:'.($attribute->max_length ?? 255)],
                Attribute::DATA_TYPE_TEXTAREA => array_filter(['nullable', 'string', $attribute->max_length ? 'max:'.$attribute->max_length : null]),
                Attribute::DATA_TYPE_SELECT => $attribute->multiple
                    ? ['array']
                    : ['nullable', 'string', Rule::in($attribute->options->pluck('value'))],
                default => ['nullable', 'string', 'max:255'],
            };

            if ($attribute->data_type === Attribute::DATA_TYPE_SELECT && $attribute->multiple) {
                $rules[$key.'.*'] = ['string', Rule::in($attribute->options->pluck('value'))];
            }

            // Pflichtfeld (Ralf, 2026-09-21): aus "darf leer sein" wird "muss ausgefüllt sein". Ja/Nein-Felder haben
            // immer einen Wert (Checkbox), Mehrfachauswahl braucht mindestens eine Auswahl.
            if ($attribute->required && $attribute->data_type !== Attribute::DATA_TYPE_BOOLEAN) {
                $rules[$key] = array_map(fn ($rule) => $rule === 'nullable' ? 'required' : $rule, (array) $rules[$key]);
                if (! in_array('required', $rules[$key], true)) {
                    array_unshift($rules[$key], 'required');
                }
                if ($attribute->data_type === Attribute::DATA_TYPE_SELECT && $attribute->multiple) {
                    $rules[$key][] = 'min:1';
                }
            }
        }

        return $rules;
    }
}
