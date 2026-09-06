<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\LegacyRole;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Personenverwaltung - angelehnt an Viettos personen.php: Liste mit Filtern,
 * Bearbeiten als globales Overlay (gleiches Muster wie das Projekt-Overlay -
 * per X-Overlay-Header erkannt, siehe isOverlayRequest()). Rechte selbst
 * werden bewusst NICHT hier verwaltet, sondern bleiben in der Rechte-
 * Verwaltung (Rechte-Set je Person) - keine doppelte Pflegestelle für
 * dieselbe Sache. "Rolle" hier ist die importierte Vietto-Rolle (LegacyRole,
 * fachliche Funktion wie TR/PM-PT), eine andere Achse als das Rechte-Set.
 * Firma/Abteilung/Geschäftsbereich hier vorerst nur als Auswahl, deren
 * eigene Verwaltung (Neuanlage) kommt als Schritt 2.
 */
class PersonController extends Controller
{
    /**
     * @var list<string>
     */
    private const FILTER_KEYS = ['search', 'company_id', 'department_id', 'business_unit_id', 'permission_template_id', 'legacy_role_id', 'typ', 'show_inactive'];

    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $this->filtersFromRequest($request);

        $people = $this->filteredPeopleQuery($filters, $tenantId)
            ->with(['company', 'department', 'businessUnit', 'permissionTemplate', 'legacyRole', 'user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.personen.index', [
            'people' => $people,
            'companies' => Company::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'permissionTemplates' => PermissionTemplate::query()->where('tenant_id', $tenantId)->orderBy('sort')->get(),
            'legacyRoles' => LegacyRole::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Neue Person sofort anlegen (leer) und direkt zum Bearbeiten öffnen -
     * genau wie Viettos create_neueperson(). Läuft bewusst immer über eine
     * echte Navigation (nicht per Overlay-Event), damit "Neue Person" auch
     * ohne JS als normaler Link funktioniert.
     */
    public function store(Request $request): RedirectResponse
    {
        $person = Person::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'first_name' => '',
            'last_name' => __('Neue Person'),
        ]);

        return redirect()->route('admin.personen.edit', $person);
    }

    public function edit(Request $request, Person $person): View|Response
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

        $data = $this->editData($request, $person);

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.personen.partials.edit-body', [...$data, 'overlay' => true]);
        }

        return view('admin.personen.edit', $data);
    }

    public function update(Request $request, Person $person): RedirectResponse|Response
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'active' => ['boolean'],
        ], [], ['short_name' => __('Kürzel')]);

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
        $person->update($validated);

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
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);
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
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);
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
     * @return array{person: Person, companies: \Illuminate\Support\Collection, departments: \Illuminate\Support\Collection, businessUnits: \Illuminate\Support\Collection, legacyRoles: \Illuminate\Support\Collection, filters: array, previousPerson: ?Person, nextPerson: ?Person}
     */
    private function editData(Request $request, Person $person): array
    {
        $tenantId = $request->user()->tenant_id;
        // Nur aus der Query-String gelesen (nicht $request->all()) - beim
        // Speichern (POST) trägt die Action-URL bewusst keine Filter mehr
        // mit (genau wie beim Projekt-Overlay), Vor/Zurück wirkt danach
        // wieder auf die ungefilterte Liste, bis erneut über die Liste
        // geöffnet wird.
        $filters = $this->filtersFromRequest($request);

        return [
            'person' => $person->fresh(['company', 'department', 'businessUnit', 'permissionTemplate', 'legacyRole', 'user']),
            'companies' => Company::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'legacyRoles' => LegacyRole::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'filters' => $filters,
            'previousPerson' => $this->adjacentPerson($filters, $person, 'previous', $tenantId),
            'nextPerson' => $this->adjacentPerson($filters, $person, 'next', $tenantId),
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

    private function filteredPeopleQuery(array $filters, int $tenantId): Builder
    {
        $query = Person::query()->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $query->where('last_name', 'like', '%'.$filters['search'].'%');
        }
        if (empty($filters['show_inactive'])) {
            $query->where('active', true);
        }
        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['business_unit_id'])) {
            $query->where('business_unit_id', (int) $filters['business_unit_id']);
        }
        if (! empty($filters['permission_template_id'])) {
            $query->where('permission_template_id', (int) $filters['permission_template_id']);
        }
        if (! empty($filters['legacy_role_id'])) {
            $query->where('legacy_role_id', (int) $filters['legacy_role_id']);
        }
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
     */
    private function adjacentPerson(array $filters, Person $current, string $way, int $tenantId): ?Person
    {
        $direction = $way === 'next' ? 'asc' : 'desc';
        $operator = $direction === 'asc' ? '>' : '<';

        return $this->filteredPeopleQuery($filters, $tenantId)
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

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }
}
