<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\FunctionGroup;
use App\Models\LegacyRole;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Personenverwaltung - angelehnt an Viettos personen.php: Liste mit Filtern,
 * Bearbeiten als globales Overlay (gleiches Muster wie das Projekt-Overlay -
 * per X-Overlay-Header erkannt, siehe isOverlayRequest()). "Rolle" hier ist
 * die importierte Vietto-Rolle (LegacyRole, fachliche Funktion wie
 * TR/PM-PT), eine andere Achse als das Rechte-Set. Firma/Abteilung/
 * Geschäftsbereich hier vorerst nur als Auswahl, deren eigene Verwaltung
 * (Neuanlage) kommt als Schritt 2.
 *
 * Rechte-Set und Funktionsgruppen sind hier zusätzlich als schnelle
 * Zuweisung mit dabei (Ralf, 2026-09-12: "ich hab das nämlich schon wieder
 * vergessen und mich gewundert, warum ich die Person bei den WFS nicht
 * sehe" - ohne Funktionsgruppen-Zuordnung taucht eine Person nirgends in
 * den Workflow-Schritten auf, das darf kein separat zu merkender
 * Extra-Schritt sein). Die eigentliche Detailverwaltung (Rechte-Sets
 * anlegen, "gilt für X Personen"-Übersicht bzw. Funktionsgruppen anlegen)
 * bleibt weiterhin in PermissionController/FunctionGroupController - hier
 * nur die Zuweisung selbst, per "verwalten"-Link dorthin verlinkt (gleiches
 * Muster wie Firma/Abteilung/Geschäftsbereich/Rolle).
 */
class PersonController extends Controller
{
    /**
     * @var list<string>
     */
    private const FILTER_KEYS = ['search', 'company_id', 'department_id', 'business_unit_id', 'permission_template_id', 'legacy_role_id', 'typ', 'show_inactive', 'tenant_id'];

    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $filters = $this->filtersFromRequest($request);

        // Kunden-Filter (Wert "all" = alle Kunden, eine konkrete ID = genau
        // dieser Kunde) nur für Admin/Super-Admin bei aktiver
        // Mandantenfähigkeit (Ralf: Kollege ruft an, "Herr XY hat
        // angerufen" - ohne das müsste man jeden Kunden einzeln
        // durchklicken, um eine Person wiederzufinden). Hier zusätzlich
        // gegen Missbrauch über die Query-String abgesichert, nicht nur in
        // der Oberfläche versteckt.
        $canSearchAllTenants = SystemSetting::multiTenantEnabled() && in_array($request->user()->role, ['admin', 'super_admin'], true);
        if (! $canSearchAllTenants) {
            unset($filters['tenant_id']);
        }

        $people = $this->filteredPeopleQuery($filters, $tenantId, $request->user()->role)
            ->with([
                // withoutGlobalScope('tenant'): eine über Kundenzugriff oder
                // "Alle Kunden durchsuchen" eingeblendete Person (siehe
                // filteredPeopleQuery) gehört meist einem ANDEREN Mandanten -
                // ohne das hier würde deren eigene Firma/Abteilung/... durch
                // deren eigenen Tenant-Scope rausgefiltert und leer angezeigt.
                'company' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'department' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'businessUnit' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'permissionTemplate' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'legacyRole' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'tenant',
                'user',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Nicht nur der Katalog DIESES Kunden - eine per Kundenzugriff
        // freigegebene Person hat ihre Firma/Abteilung/GB/ihr Rechte-Set
        // beim eigenen Heimat-Mandanten, der stünde sonst nicht zur
        // Auswahl (Ralfs Bug-Report bei Funktionsgruppen, gleiche Ursache
        // hier). Bewusst aus den GRUNDSÄTZLICH sichtbaren Mandanten
        // abgeleitet (nicht aus der schon gefilterten Personenliste) -
        // sonst würden die Dropdowns beim Filtern unerwartet schrumpfen.
        $visibleTenantIds = Person::visibleTenantIds($tenantId);

        return view('admin.personen.index', [
            'people' => $people,
            'companies' => Company::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $visibleTenantIds)->orderBy('name')->get(),
            'departments' => Department::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $visibleTenantIds)->where('active', true)->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $visibleTenantIds)->where('active', true)->orderBy('name')->get(),
            'permissionTemplates' => PermissionTemplate::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $visibleTenantIds)->orderBy('sort')->get(),
            'legacyRoles' => LegacyRole::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $visibleTenantIds)->orderBy('name')->get(),
            'multiTenantEnabled' => SystemSetting::multiTenantEnabled(),
            'canSearchAllTenants' => $canSearchAllTenants,
            'tenants' => $canSearchAllTenants ? Tenant::query()->orderBy('name')->get() : collect(),
            'filters' => $filters,
        ]);
    }

    /**
     * Neue Person sofort anlegen (leer) und direkt zum Bearbeiten öffnen -
     * genau wie Viettos create_neueperson(). Bei Aufruf aus dem Overlay
     * heraus (X-Overlay-Header) nur die neue ID als JSON zurückgeben, das
     * Öffnen als Overlay übernimmt dann das Frontend (window.open-person) -
     * ohne JS/Overlay-Kontext bleibt der klassische Redirect als Fallback.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $person = Person::query()->create([
            'tenant_id' => CurrentTenant::id(),
            'first_name' => '',
            'last_name' => __('Neue Person'),
        ]);

        if ($this->isOverlayRequest($request)) {
            return response()->json(['id' => $person->id]);
        }

        return redirect()->route('admin.personen.edit', $person);
    }

    public function edit(Request $request, Person $person): View|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);

        $data = $this->editData($request, $person);

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.personen.partials.edit-body', [...$data, 'overlay' => true]);
        }

        return view('admin.personen.edit', $data);
    }

    public function update(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);

        $isOverlay = $this->isOverlayRequest($request);

        $validator = Validator::make($request->all(), [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'short_name' => [
                'nullable', 'string', 'min:2', 'max:4',
                Rule::unique('people', 'short_name')->where('tenant_id', $person->tenant_id)->ignore($person->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $person->tenant_id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $person->tenant_id)],
            'business_unit_id' => ['nullable', 'integer', Rule::exists('business_units', 'id')->where('tenant_id', $person->tenant_id)],
            'legacy_role_id' => ['nullable', 'integer', Rule::exists('legacy_roles', 'id')->where('tenant_id', $person->tenant_id)],
            'permission_template_id' => ['nullable', 'integer', Rule::exists('permission_templates', 'id')->where('tenant_id', $person->tenant_id)],
            'function_group_ids' => ['array'],
            'function_group_ids.*' => ['integer', Rule::exists('function_groups', 'id')->where('tenant_id', $person->tenant_id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'active' => ['boolean'],
            'is_absent' => ['boolean'],
            'absent_until' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            // Gleiche eigene Meldung wie SettingsController::updateAbsence()
            // (Laravels Standardtext übersetzt "today" nicht).
            'absent_until.after_or_equal' => __('Das Datum darf nicht in der Vergangenheit liegen.'),
        ], ['short_name' => __('Kürzel'), 'absent_until' => __('Abwesend bis')]);

        if ($validator->fails()) {
            if ($isOverlay) {
                return response()
                    ->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true, 'errors' => $validator->errors()])
                    ->setStatusCode(422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $validated['active'] = $request->boolean('active');
        $validated['is_absent'] = $request->boolean('is_absent');
        $functionGroupIds = collect($validated['function_group_ids'] ?? []);
        unset($validated['function_group_ids']);
        $person->update($validated);

        // Rolle (users.role) folgt dem Rechte-Set, wenn die Person einen
        // Login hat - gleiche Logik wie PermissionController::
        // assignTemplate(), hier dupliziert statt extrahiert, weil beide
        // Stellen bewusst unabhängige, kleine Aufrufer bleiben sollen.
        if ($person->user && $person->permission_template_id) {
            $person->user->update(['role' => PermissionTemplate::query()->find($person->permission_template_id)?->role ?? 'user']);
        }

        // function_group_member.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben (gleiches Muster wie FunctionGroupController::
        // updatePersonGroups()).
        $person->functionGroups()->sync($functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $person->tenant_id]]));

        if ($isOverlay) {
            $request->session()->flash('status', 'person-updated');

            return response()->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true]);
        }

        return redirect()->route('admin.personen.edit', $person)->with('status', 'person-updated');
    }

    /**
     * Login-Zugang für eine bisherige Kontaktperson (ohne User) anlegen -
     * entspricht dem Rollen-Aufstieg 4 -> 3 aus dem CLAUDE.md-Rollenmodell.
     * Rechte-Set/Rolle wird bewusst NICHT hier gesetzt, sondern separat über
     * die Rechte-Verwaltung.
     */
    public function createLogin(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);
        abort_if($person->user, 422);

        $isOverlay = $this->isOverlayRequest($request);

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:4'],
        ]);

        if ($validator->fails()) {
            if ($isOverlay) {
                return response()
                    ->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true, 'errors' => $validator->errors()])
                    ->setStatusCode(422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        User::query()->create([
            'tenant_id' => $person->tenant_id,
            'person_id' => $person->id,
            'name' => $person->fullName(),
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($isOverlay) {
            $request->session()->flash('status', 'login-created');

            return response()->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true]);
        }

        return redirect()->route('admin.personen.edit', $person)->with('status', 'login-created');
    }

    public function resetPassword(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);
        abort_unless($person->user, 404);

        $isOverlay = $this->isOverlayRequest($request);

        $validator = Validator::make($request->all(), ['password' => ['required', 'string', 'min:4']]);

        if ($validator->fails()) {
            if ($isOverlay) {
                return response()
                    ->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true, 'errors' => $validator->errors()])
                    ->setStatusCode(422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $person->user->update(['password' => $validator->validated()['password']]);

        if ($isOverlay) {
            $request->session()->flash('status', 'password-reset');

            return response()->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true]);
        }

        return redirect()->route('admin.personen.edit', $person)->with('status', 'password-reset');
    }

    /**
     * Zugriff auf zusätzliche Kunden-Mandanten (Umschalter-Vorarbeit,
     * Mandantenfähigkeit) - separates Formular/eigene Box im Overlay,
     * analog zu createLogin()/resetPassword() oben.
     */
    public function updateTenantAccess(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $tenantIds = collect($request->array('tenant_ids'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== $person->tenant_id)
            ->unique()
            ->values();

        $person->accessibleTenants()->sync($tenantIds);

        $isOverlay = $this->isOverlayRequest($request);

        if ($isOverlay) {
            $request->session()->flash('status', 'tenant-access-updated');

            return response()->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true]);
        }

        return redirect()->route('admin.personen.edit', $person)->with('status', 'tenant-access-updated');
    }

    /**
     * Löschen nur, solange die Person noch keine "echten" Daten hat (siehe
     * Person::hasData()) - sonst bleibt nur "inaktiv setzen". Ralf: aus
     * Versehen unter dem falschen Kunden angelegt, sofort bemerkt - "ich
     * kann sie dann nur inaktiv setzen und sie bleibt auf ewig als Leiche
     * rumliegen".
     */
    public function destroy(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        $this->abortIfProtectedFromEditing($request, $person);
        abort_if($person->hasData(), 422, __('Diese Person hat bereits Daten und kann nicht gelöscht werden.'));

        $person->delete();

        if ($this->isOverlayRequest($request)) {
            return response()->noContent();
        }

        return redirect()->route('admin.personen')->with('status', 'person-deleted');
    }

    /**
     * Nur ein Super-Admin darf einer anderen Person Super-Admin-Rechte
     * geben oder wieder entziehen (Ralf: "als Superadmin sollte ich
     * weitere Personen in diesen erhabenen Stand erheben können"). Beim
     * Zurückstufen fällt die Rolle auf das zugewiesene Rechte-Set zurück
     * (oder "user", falls keins zugewiesen ist) - nie blind auf "admin",
     * das wäre eine unbeabsichtigte Rechteausweitung ohne passendes Set
     * (siehe Ralfs eigenes alte Konto, das genau in dieser Falle war).
     * Der letzte verbliebene Super-Admin kann nicht zurückgestuft werden.
     */
    public function updateRole(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($this->personVisibleInCurrentTenant($request, $person), 404);
        abort_unless($request->user()->role === 'super_admin', 403);
        abort_unless($person->user, 404);

        $makeSuperAdmin = $request->boolean('super_admin');

        if (! $makeSuperAdmin && $person->user->role === 'super_admin') {
            $remainingSuperAdmins = User::query()->where('role', 'super_admin')->where('id', '!=', $person->user->id)->exists();
            abort_unless($remainingSuperAdmins, 422, __('Der letzte verbliebene Super-Admin kann nicht zurückgestuft werden.'));
        }

        $person->user->update(['role' => $makeSuperAdmin ? 'super_admin' : ($person->permissionTemplate?->role ?? 'user')]);

        $isOverlay = $this->isOverlayRequest($request);

        if ($isOverlay) {
            $request->session()->flash('status', 'role-updated');

            return response()->view('admin.personen.partials.edit-body', [...$this->editData($request, $person), 'overlay' => true]);
        }

        return redirect()->route('admin.personen.edit', $person)->with('status', 'role-updated');
    }

    /**
     * Ein Super-Admin-Konto darf nur von einem anderen Super-Admin
     * eingesehen und bearbeitet werden - ein normaler Admin sieht es noch
     * in der Personenliste (Name/Zeile), aber das Detail-Overlay bleibt
     * gesperrt (Ralf zunächst: "sollte ich vielleicht sehen, aber nicht
     * ändern dürfen", dann korrigiert: "Sperre doch einfach den Zugriff
     * auf die Personendetails" - einfacher als einzelne Felder zu
     * deaktivieren). Schützt vor versehentlicher oder böswilliger
     * Einmischung eines untergeordneten Admins.
     */
    private function abortIfProtectedFromEditing(Request $request, Person $person): void
    {
        abort_if(
            $person->user?->role === 'super_admin' && $request->user()->role !== 'super_admin',
            403
        );
    }

    /**
     * @return array{person: Person, companies: \Illuminate\Support\Collection, departments: \Illuminate\Support\Collection, businessUnits: \Illuminate\Support\Collection, legacyRoles: \Illuminate\Support\Collection, permissionTemplates: \Illuminate\Support\Collection, functionGroups: \Illuminate\Support\Collection, filters: array, previousPerson: ?Person, nextPerson: ?Person}
     */
    private function editData(Request $request, Person $person): array
    {
        // Für die Liste/das Blättern zählt der AKTIVE Kunde (Kontext, in dem
        // gerade geblättert wird) - für die eigenen Stammdaten der Person
        // (Firma/Abteilung/Geschäftsbereich/Rolle, Kundenzugriff) zählt ihr
        // eigener Heimat-Mandant, der bei einer über Kundenzugriff
        // eingeblendeten Person vom aktiven Kunden abweichen kann (siehe
        // filteredPeopleQuery/personVisibleInCurrentTenant).
        $tenantId = CurrentTenant::id();
        $personTenantId = $person->tenant_id;
        // Nur aus der Query-String gelesen (nicht $request->all()) - beim
        // Speichern (POST) trägt die Action-URL bewusst keine Filter mehr
        // mit (genau wie beim Projekt-Overlay), Vor/Zurück wirkt danach
        // wieder auf die ungefilterte Liste, bis erneut über die Liste
        // geöffnet wird.
        $filters = $this->filtersFromRequest($request);

        $multiTenantEnabled = SystemSetting::multiTenantEnabled();
        $withoutTenantScope = fn ($query) => $query->withoutGlobalScope('tenant');

        return [
            'person' => $person->fresh([
                'company' => $withoutTenantScope,
                'department' => $withoutTenantScope,
                'businessUnit' => $withoutTenantScope,
                'permissionTemplate' => $withoutTenantScope,
                'legacyRole' => $withoutTenantScope,
                'functionGroups',
                'user', 'accessibleTenants', 'tenant',
            ]),
            'companies' => Company::query()->where('tenant_id', $personTenantId)->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $personTenantId)->where('active', true)->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('tenant_id', $personTenantId)->where('active', true)->orderBy('name')->get(),
            'legacyRoles' => LegacyRole::query()->where('tenant_id', $personTenantId)->orderBy('name')->get(),
            'permissionTemplates' => PermissionTemplate::query()->where('tenant_id', $personTenantId)->orderBy('sort')->get(),
            // Inaktive Gruppen bleiben in der Liste, wenn die Person schon
            // Mitglied ist (gleiches Prinzip wie Abteilung/Geschäftsbereich
            // bei den vier "klitzekleinen" Verwalten-Overlays) - sonst würde
            // ein Speichern eine bestehende Mitgliedschaft in einer
            // inzwischen deaktivierten Gruppe stillschweigend entfernen.
            'functionGroups' => FunctionGroup::query()->where('tenant_id', $personTenantId)
                ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $person->functionGroups->pluck('id')))
                ->orderBy('name')->get(),
            'multiTenantEnabled' => $multiTenantEnabled,
            'otherTenants' => $multiTenantEnabled ? Tenant::query()->where('id', '!=', $personTenantId)->orderBy('name')->get() : collect(),
            'actingUserIsSuperAdmin' => $request->user()->role === 'super_admin',
            'filters' => $filters,
            'previousPerson' => $this->adjacentPerson($request, $filters, $person, 'previous', $tenantId),
            'nextPerson' => $this->adjacentPerson($request, $filters, $person, 'next', $tenantId),
        ];
    }

    /**
     * Nur bekannte Filterfelder durchlassen, leere Werte verwerfen - liest
     * bewusst nur aus der Query-String (siehe editData()).
     *
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return collect($request->query())
            ->only(self::FILTER_KEYS)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }

    /**
     * Drei unterscheidbare Bedeutungen des Kunden-Filters (Ralf: "gehört zu
     * Firma XY" ist etwas GRUNDSÄTZLICH anderes als "kann zum
     * Kundenbereich wechseln + dort Projektmitglied werden" - beides
     * gemischt in einem Filter zu haben, wäre irreführend):
     * - kein Filter (Standard): Heimat-Personen DES AKTIVEN Kunden PLUS
     *   Personen mit Kundenzugriff-Freigabe dafür (siehe person_tenant) -
     *   "wer steht mir hier zur Verfügung" (Kunden-PM + freigeschaltete
     *   eigene TR).
     * - eine konkrete Kunden-ID: NUR dessen Heimat-Personen, reine
     *   Mitgliedschaft ("gehört zu Kunde X"), keine Freigabe-Personen -
     *   exakt wie Firma/Abteilung/GB.
     * - "all": keine Einschränkung, alle Kunden (Namenssuche über alle
     *   hinweg, z.B. "wer war das nochmal am Telefon").
     * Braucht withoutGlobalScope('tenant'), weil BelongsToTenant sonst
     * automatisch UND tenant_id = aktiver Kunde an jede Abfrage hängt und
     * Personen anderer Mandanten wieder rausfiltern würde. Super-Admin-
     * Konten sind für alle Logins unterhalb der Superadmin-Rolle komplett
     * ausgeblendet (siehe Person::scopeVisibleToRole()), nicht nur gesperrt.
     */
    private function filteredPeopleQuery(array $filters, int $tenantId, string $viewerRole): Builder
    {
        $query = Person::query()->withoutGlobalScope('tenant')->visibleToRole($viewerRole);

        $tenantFilter = $filters['tenant_id'] ?? null;

        if ($tenantFilter === 'all') {
            // keine Einschränkung
        } elseif ($tenantFilter !== null) {
            $query->where('tenant_id', (int) $tenantFilter);
        } else {
            $query->visibleInTenant($tenantId);
        }

        if (! empty($filters['search'])) {
            $query->where('last_name', 'like', '%'.$filters['search'].'%');
        }
        if (empty($filters['show_inactive'])) {
            $query->where('active', true);
        }
        // "none" als Filterwert = gezielt nach nicht zugewiesenen Personen
        // suchen (Pulldown-Option "– nicht zugewiesen –"), sonst normaler
        // ID-Abgleich.
        $applyLookupFilter = function (Builder $query, string $column, ?string $value): void {
            if (empty($value)) {
                return;
            }
            $value === 'none' ? $query->whereNull($column) : $query->where($column, (int) $value);
        };
        $applyLookupFilter($query, 'company_id', $filters['company_id'] ?? null);
        $applyLookupFilter($query, 'department_id', $filters['department_id'] ?? null);
        $applyLookupFilter($query, 'business_unit_id', $filters['business_unit_id'] ?? null);
        $applyLookupFilter($query, 'permission_template_id', $filters['permission_template_id'] ?? null);
        $applyLookupFilter($query, 'legacy_role_id', $filters['legacy_role_id'] ?? null);
        if (! empty($filters['typ'])) {
            // "Typ" ist rein abgeleitet aus dem Vorhandensein eines
            // User-Accounts (Login-User vs. Kontaktperson) - kein eigenes
            // Feld, damit es nie mit der Realität auseinanderlaufen kann.
            $filters['typ'] === 'login' ? $query->has('user') : $query->doesntHave('user');
        }

        return $query;
    }

    /**
     * Blättern (< >) innerhalb der aktuell gefilterten Treffermenge -
     * gleiches Vorgehen wie ProjectController::adjacentProject(), aber
     * ohne dessen Komplexität für wählbare Sortier-Spalten: die
     * Personenliste sortiert immer fix nach Nachname/Vorname, id als
     * dritte Ebene bricht Gleichstände (gleicher Name) eindeutig auf.
     * filteredPeopleQuery() blendet Super-Admin-Konten für alle Logins
     * unterhalb der Superadmin-Rolle bereits aus - landet man beim
     * Blättern also nie auf einem.
     */
    private function adjacentPerson(Request $request, array $filters, Person $current, string $way, int $tenantId): ?Person
    {
        $direction = $way === 'next' ? 'asc' : 'desc';
        $operator = $direction === 'asc' ? '>' : '<';

        return $this->filteredPeopleQuery($filters, $tenantId, $request->user()->role)
            ->where(function (Builder $query) use ($operator, $current) {
                $query->where('last_name', $operator, $current->last_name)
                    ->orWhere(function (Builder $query) use ($operator, $current) {
                        $query->where('last_name', $current->last_name)
                            ->where(function (Builder $query) use ($operator, $current) {
                                $query->where('first_name', $operator, $current->first_name)
                                    ->orWhere(function (Builder $query) use ($operator, $current) {
                                        $query->where('first_name', $current->first_name)->where('id', $operator, $current->id);
                                    });
                            });
                    });
            })
            ->orderBy('last_name', $direction)
            ->orderBy('first_name', $direction)
            ->orderBy('id', $direction)
            ->first();
    }

    /**
     * Eine Person ist erreichbar, wenn sie im aktiven Kunden zuhause ist
     * ODER dafür per Kundenzugriff freigegeben wurde (siehe
     * filteredPeopleQuery/updateTenantAccess) - Ralf: "die Mitarbeiter
     * meiner Firma, für die der Kunde freigegeben ist" sollen beim Kunden
     * auftauchen und dort auch geöffnet werden können. Admin/Super-Admin
     * dürfen zusätzlich JEDE Person öffnen, unabhängig vom aktiven Kunden -
     * sie könnten ohnehin zu jedem Kunden umschalten (siehe CurrentTenant),
     * das erspart den Umweg über "Alle Kunden durchsuchen" + Kunde erst
     * wechseln, nur um eine gefundene Person zu öffnen.
     */
    private function personVisibleInCurrentTenant(Request $request, Person $person): bool
    {
        if (in_array($request->user()->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        return $person->isVisibleInTenant(CurrentTenant::id());
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }
}
