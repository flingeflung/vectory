<?php

namespace App\Http\Controllers;

use App\Models\DisplayFilterSet;
use App\Models\FunctionGroup;
use App\Models\Market;
use App\Models\MarketSet;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Favorite;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\RecentlyViewedProject;
use App\Models\ProjectTypeSub;
use App\Models\ProjectWorkflowStep;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\ProjectColumnCatalog;
use App\Support\ProjectFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
    private const SORTABLE_COLUMNS = ['source_pn', 'title', 'version', 'status', 'workflow'];

    private const DATE_RANGE_FIELDS = ['start_date', 'end_date', 'publication_date'];

    private const BOOL_FIELDS = ['archived'];

    public function index(Request $request): View
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = $this->filtersFromRequest($request);
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

        $allColumns = ProjectColumnCatalog::effectiveFor($user);
        $visibleColumns = array_values(array_filter($allColumns, fn (array $column) => $column['visible']));

        $query = $this->orderedQuery($sort, $direction, $filters);
        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'markets')) {
            $query->with('markets');
        }
        if (array_any($visibleColumns, fn (array $column) => $column['key'] === 'workflow')) {
            $query->with('workflow');
        }
        if (array_any($visibleColumns, fn (array $column) => in_array($column['key'], ['progress', 'workflow'], true))) {
            $query->with('projectWorkflowSteps.workflowStep');
        }
        $projects = $query->paginate(25)->withQueryString();

        $graphicOrderSummaries = array_any($visibleColumns, fn (array $column) => $column['key'] === 'graphic_orders_summary')
            ? $this->graphicOrderSummaries($projects->pluck('id'))
            : collect();

        $activeFilterFields = ! empty($filters) || $request->has('projektfilter_submitted')
            ? array_values(array_unique([...ProjectFilterCatalog::activeFieldsFor($user), ...array_keys($filters)]))
            : ProjectFilterCatalog::activeFieldsFor($user);

        return view('projekte.index', [
            'projects' => $projects,
            'columns' => $visibleColumns,
            'allColumns' => $allColumns,
            'sets' => DisplayFilterSet::query()->where('user_id', $user->id)->orderBy('name')->get(),
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $filters,
            'filterFields' => ProjectFilterCatalog::available($user->tenant_id),
            'activeFilterFields' => $activeFilterFields,
            'filterChips' => ProjectFilterCatalog::describeFilters($filters, $user->tenant_id),
            'totalCount' => Project::query()->count(),
            'favoriteProjectIds' => Favorite::where('user_id', $user->id)->pluck('project_id')->all(),
            'graphicOrderSummaries' => $graphicOrderSummaries,
        ]);
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
    public function quickSearch(Request $request): \Illuminate\Http\JsonResponse
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
            // Kein smallSymbol() ("_kl"-Variante) - die Dateien wurden (noch)
            // nicht aus Vietto importiert, nur die normalgroßen Icons.
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
     * @return \Illuminate\Support\Collection<int, object{total: int, done: int, images: int}>
     */
    private function graphicOrderSummaries(\Illuminate\Support\Collection $projectIds): \Illuminate\Support\Collection
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        return \App\Models\GraphicOrder::query()
            ->whereIn('project_id', $projectIds)
            ->whereHas('status', fn (Builder $query) => $query->where('is_discarded', false))
            ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN done_at IS NOT NULL THEN 1 ELSE 0 END) as done, GREATEST(SUM(image_count), 0) as images')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');
    }

    /**
     * Direkt aufrufbar (z.B. Link aus einer Status-E-Mail) -> volle Seite.
     * Aufruf aus dem Tool selbst (Klick auf eine PN) -> nur der Formular-Teil
     * fürs Overlay (erkannt am X-Overlay-Header).
     */
    public function show(Request $request, Project $project): View|Response
    {
        RecentlyViewedProject::record($project, $request->user());

        $data = $this->detailData($request, $project);

        if ($this->isOverlayRequest($request)) {
            return response()->view('projekte.partials.detail', [...$data, 'overlay' => true]);
        }

        return view('projekte.show', $data);
    }

    public function update(Request $request, Project $project): RedirectResponse|Response
    {
        $relevantAttributes = $project->relevantAttributes();
        $isOverlay = $this->isOverlayRequest($request);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'construction_year' => ['nullable', 'string', 'max:30'],
            'initiator' => ['nullable', 'string', 'max:255'],
            'system_model' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer'],
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'archived' => ['boolean'],
            'localization' => ['nullable', 'in:0,1'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->where('tenant_id', $project->tenant_id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'publication_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'markets' => ['array'],
            'markets.*' => ['integer', Rule::exists('markets', 'id')->where('tenant_id', $project->tenant_id)],
            'project_people' => ['array'],
            'project_people.*' => ['array'],
            'project_people.*.*' => ['integer', Rule::exists('people', 'id')->where('tenant_id', $project->tenant_id)],
            'project_people_primary' => ['array'],
            'project_people_primary.*' => ['nullable', 'integer'],
            'attributes' => ['array'],
            ...$relevantAttributes->mapWithKeys(fn ($attribute) => [
                'attributes.'.$attribute->key => $attribute->data_type === 'number'
                    ? ['nullable', 'numeric']
                    : ['nullable', 'string', 'max:255'],
            ])->all(),
        ]);

        if ($validator->fails()) {
            if ($isOverlay) {
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
        $validated['archived'] = $request->boolean('archived');
        // Tri-state: leere Auswahl = nicht zugewiesen (null), sonst Ja/Nein.
        // ConvertEmptyStringsToNull (globale Middleware) macht aus "" bereits null, bevor wir hier ankommen.
        $localizationInput = $request->input('localization');
        $validated['localization'] = $localizationInput === null || $localizationInput === '' ? null : (bool) $localizationInput;
        $marketIds = $validated['markets'] ?? [];
        $projectPeopleInput = $validated['project_people'] ?? [];
        $primaryInput = $validated['project_people_primary'] ?? [];
        unset($validated['markets'], $validated['project_people'], $validated['project_people_primary']);

        // Nur die für die Projektart relevanten Attribute überschreiben, Rest im JSON unangetastet lassen.
        $attributes = $project->attributes ?? [];
        foreach ($relevantAttributes as $attribute) {
            $value = $validated['attributes'][$attribute->key] ?? null;
            if ($value === null || $value === '') {
                unset($attributes[$attribute->key]);
            } else {
                $attributes[$attribute->key] = $value;
            }
        }
        $validated['attributes'] = $attributes;

        $project->update($validated);

        // Beim (Neu-)Zuweisen eines Workflows die Schritt-Vorlagen einmalig
        // in projekteigene Instanzen kopieren. Schritte eines zuvor
        // zugewiesenen anderen Workflows bleiben unangetastet in der DB
        // stehen (kollidieren nie, da workflow_step_id je Workflow eindeutig
        // ist) - kein Datenverlust beim Wechsel, wie in Vietto.
        if ($project->wasChanged('workflow_id')) {
            if ($project->workflow_id) {
                WorkflowStep::query()
                    ->where('workflow_id', $project->workflow_id)
                    ->get()
                    ->each(fn (WorkflowStep $step) => ProjectWorkflowStep::firstOrCreate(
                        ['project_id' => $project->id, 'workflow_step_id' => $step->id],
                        ['tenant_id' => $project->tenant_id, 'sort' => $step->sort]
                    ));

                Activity::log($project, ActivityType::WorkflowAssigned, __('Workflow ":name" zugewiesen.', ['name' => $project->workflow->name]));
            } else {
                Activity::log($project, ActivityType::WorkflowUnassigned, __('Workflowzuweisung entfernt.'));
            }
        }

        $project->markets()->sync(collect($marketIds)->mapWithKeys(fn (int $marketId) => [
            $marketId => ['tenant_id' => $project->tenant_id],
        ])->all());

        // Projektbeteiligte Personen: komplett aus der Formularauswahl neu aufbauen.
        // Einzeln statt per Bulk-delete() löschen - ein Bulk-Query löst keine
        // Model-Events aus, das würde den Aufgaben-Rebuild (ProjectPersonObserver)
        // genau dann NICHT anstoßen, wenn alle Personen entfernt werden und
        // keine einzige neue Zeile mehr erzeugt wird (danach fiele kein
        // einziges create() mehr an, das den Sync sonst mit anstößt).
        ProjectPerson::where('project_id', $project->id)->get()->each->delete();
        foreach ($projectPeopleInput as $groupId => $personIds) {
            foreach ($personIds as $personId) {
                ProjectPerson::create([
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'function_group_id' => $groupId,
                    'person_id' => $personId,
                    'is_primary' => (int) ($primaryInput[$groupId] ?? null) === (int) $personId,
                ]);
            }
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
     * @return array{project: Project, attributes: \Illuminate\Support\Collection, sort: ?string, direction: string, filters: array, previousProject: ?Project, nextProject: ?Project}
     */
    private function detailData(Request $request, Project $project): array
    {
        [$sort, $direction] = $this->sortFromRequest($request);
        $filters = $this->filtersFromRequest($request);

        return [
            'project' => $project->loadMissing(['markets', 'projectPeople.person', 'projectPeople.functionGroup', 'workflow', 'activities.user', 'projectWorkflowSteps.workflowStep.functionGroups', 'projectWorkflowSteps.people.functionGroup', 'projectWorkflowSteps.people.person', 'graphicOrders.status', 'graphicOrders.initiatedBy', 'graphicOrders.illustrator']),
            'attributes' => $project->relevantAttributes(),
            'allMarkets' => Market::query()->where('tenant_id', $project->tenant_id)->orderBy('sort')->get(),
            'marketSets' => MarketSet::query()->where('tenant_id', $project->tenant_id)->with('markets:id')->orderBy('sort')->get(),
            'allFunctionGroups' => FunctionGroup::query()->where('tenant_id', $project->tenant_id)->with('members')->orderBy('sort')->get(),
            // Der aktuell zugewiesene Workflow muss immer in der Liste auftauchen, auch wenn er
            // inzwischen inaktiv/ersetzt ist - sonst würde ein Speichern ohne bewusste Auswahl
            // den Workflow fälschlich entfernen, weil kein <option> mehr dazu passt.
            'availableWorkflows' => Workflow::query()
                ->where('tenant_id', $project->tenant_id)
                ->where(fn (Builder $query) => $query->where('active', true)->orWhere('id', $project->workflow_id))
                ->orderBy('sort')
                ->orderBy('name')
                ->get(),
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $filters,
            'previousProject' => $this->adjacentProject($sort, $direction, $filters, $project, 'previous'),
            'nextProject' => $this->adjacentProject($sort, $direction, $filters, $project, 'next'),
        ];
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

    /**
     * Nur bekannte Filterfelder durchlassen, leere Werte verwerfen.
     *
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        // Schnellsuche (Sidebar, Enter/Lupe) ersetzt jeden anderen Filter,
        // statt sich mit ihm zu kombinieren - wie in Vietto, und auf
        // Rückfrage von Ralf bewusst so entschieden (sonst nie klar, warum
        // ein erwarteter Treffer fehlt). Eigener Key statt Eintrag im
        // regulären, whitelisted filter[]-Katalog (ProjectFilterCatalog),
        // da sie kein normales Formularfeld ist.
        $quickSearch = trim((string) $request->input('filter.schnellsuche', ''));
        if ($quickSearch !== '') {
            return ['schnellsuche' => $quickSearch];
        }

        $available = array_column(ProjectFilterCatalog::available($request->user()->tenant_id), null, 'key');
        $raw = $request->query('filter', []);
        $filters = [];

        foreach ($raw as $key => $value) {
            if (! isset($available[$key])) {
                continue;
            }

            if (in_array($key, self::DATE_RANGE_FIELDS, true)) {
                $from = trim((string) ($value['from'] ?? ''));
                $to = trim((string) ($value['to'] ?? ''));
                if ($from !== '' || $to !== '') {
                    $filters[$key] = array_filter(['from' => $from ?: null, 'to' => $to ?: null]);
                }

                continue;
            }

            if ($key === 'project_type' || $key === 'project_year' || $key === 'markets' || $key === 'status') {
                $ids = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''));
                if (! empty($ids)) {
                    $filters[$key] = $ids;
                }

                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $value) {
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

            if ($key === 'localization') {
                $value === 'null' ? $query->whereNull('localization') : $query->where('localization', (bool) $value);

                continue;
            }

            if ($key === 'favorite') {
                $favoritesQuery = fn (Builder $query) => $query->where('user_id', Auth::id());
                (bool) $value ? $query->whereHas('favorites', $favoritesQuery) : $query->whereDoesntHave('favorites', $favoritesQuery);

                continue;
            }

            if ($key === 'project_type') {
                $query->whereIn('project_type_sub', $value);

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

            if ($key === 'version') {
                // Wert kommt aus einem Pulldown mit Operator+Zahl, z.B. ">= 3".
                [$operator, $number] = array_pad(explode(' ', $value, 2), 2, null);
                if (in_array($operator, ['<=', '<', '=', '>=', '>'], true) && is_numeric($number)) {
                    $query->where('version', $operator, (int) $number);
                }

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

            if ($key === 'graphic_orders') {
                match ($value) {
                    'ohne' => $query->whereDoesntHave('graphicOrders'),
                    'mit' => $query->whereHas('graphicOrders.status', fn (Builder $query) => $query->where('is_discarded', false)),
                    'offene' => $query->whereHas('graphicOrders.status', fn (Builder $query) => $query->where('is_open', true)),
                    default => null,
                };

                continue;
            }

            if ($key === 'status') {
                $query->whereIn('status', $value);

                continue;
            }

            $query->where($key, 'like', "%{$value}%");
        }
    }

    /**
     * Feldkatalog der Schnellsuche (Dropdown UND "alle Treffer"-Liste,
     * orientiert an Viettos ajax_direktsuche.php): PN als Präfix, alles
     * andere als Teilstring. Projekt-Art wird über die Namen der
     * zugehörigen ProjectTypeSub-Datensätze aufgelöst, da project_type_sub
     * auf Project nur die legacy_id trägt, nicht den Namen selbst.
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
            ->pluck('legacy_id')
            ->all();

        $query->where(function (Builder $query) use ($term, $typeIds, $includeRemarks) {
            $query->where('source_pn', 'like', "{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('codename', 'like', "%{$term}%")
                ->orWhere('initiator', 'like', "%{$term}%")
                ->orWhere('system_model', 'like', "%{$term}%")
                ->orWhere('attributes_material_number', 'like', "%{$term}%");

            if ($includeRemarks) {
                $query->orWhere('remarks', 'like', "%{$term}%");
            }

            if (! empty($typeIds)) {
                $query->orWhereIn('project_type_sub', $typeIds);
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

    private function orderedQuery(?string $sort, string $direction, array $filters = []): Builder
    {
        [$column, $dir] = $this->effectiveOrder($sort, $direction);

        $query = Project::query();
        [$sortColumn, $idColumn] = $this->resolveSortColumns($query, $column);
        $query->orderBy($sortColumn, $dir)->orderBy($idColumn, $dir);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Nächstes/vorheriges Projekt in genau der Reihenfolge, die auch die
     * (ggf. gefilterte) Übersicht für diese Sortierung anzeigt (Keyset-Vergleich, seitenübergreifend).
     */
    private function adjacentProject(?string $sort, string $direction, array $filters, Project $current, string $way): ?Project
    {
        [$column, $primaryDir] = $this->effectiveOrder($sort, $direction);
        $queryDirection = $way === 'next' ? $primaryDir : ($primaryDir === 'asc' ? 'desc' : 'asc');
        $operator = $queryDirection === 'asc' ? '>' : '<';

        $query = Project::query();
        [$sortColumn, $idColumn] = $this->resolveSortColumns($query, $column);
        $value = $column === 'workflow' ? $current->workflow?->sort : $current->{$column};
        $this->applyFilters($query, $filters);

        return $query
            ->where(function ($query) use ($sortColumn, $idColumn, $operator, $value, $current) {
                if ($value === null) {
                    $query->whereNotNull($sortColumn);

                    return;
                }

                $query->where($sortColumn, $operator, $value)
                    ->orWhere(function ($query) use ($sortColumn, $idColumn, $value, $operator, $current) {
                        $query->where($sortColumn, $value)->where($idColumn, $operator, $current->id);
                    });
            })
            ->orderBy($sortColumn, $queryDirection)
            ->orderBy($idColumn, $queryDirection)
            ->first();
    }

    /**
     * "workflow" sortiert nicht alphabetisch nach Name, sondern nach dem
     * internen Sortierschlüssel des zugewiesenen Workflows (workflows.sort)
     * - dafür nötig: Join + explizite Spaltenqualifizierung (sonst
     * mehrdeutig, da beide Tabellen u.a. "id" haben).
     *
     * @return array{0: string, 1: string} [Sortier-Spalte, id-Spalte]
     */
    private function resolveSortColumns(Builder $query, string $column): array
    {
        if ($column !== 'workflow') {
            return [$column, 'id'];
        }

        $query->leftJoin('workflows', 'workflows.id', '=', 'projects.workflow_id')->select('projects.*');

        return ['workflows.sort', 'projects.id'];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }
}
