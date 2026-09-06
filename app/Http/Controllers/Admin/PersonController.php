<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Personenverwaltung - angelehnt an Viettos personen.php: Liste mit Filtern
 * links, Stammdaten-Bearbeitung rechts. Rollen-Rechte selbst werden bewusst
 * NICHT hier verwaltet, sondern bleiben in der Rechte-Verwaltung (Rechte-Set
 * je Person) - keine doppelte Pflegestelle für dieselbe Sache. Firma/
 * Abteilung/Geschäftsbereich hier vorerst nur als Auswahl, deren eigene
 * Verwaltung (Neuanlage) kommt als Schritt 2.
 */
class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $query = Person::query()->where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $query->where('last_name', 'like', '%'.$request->string('search').'%');
        }
        if (! $request->boolean('show_inactive')) {
            $query->where('active', true);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', $request->integer('business_unit_id'));
        }
        if ($request->filled('permission_template_id')) {
            $query->where('permission_template_id', $request->integer('permission_template_id'));
        }

        $people = $query
            ->with(['company', 'department', 'businessUnit', 'permissionTemplate', 'user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.personen.index', [
            'people' => $people,
            'companies' => Company::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'permissionTemplates' => PermissionTemplate::query()->where('tenant_id', $tenantId)->orderBy('sort')->get(),
        ]);
    }

    /**
     * Neue Person sofort anlegen (leer) und direkt zum Bearbeiten öffnen -
     * genau wie Viettos create_neueperson().
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

    public function edit(Request $request, Person $person): View
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

        $tenantId = $request->user()->tenant_id;

        return view('admin.personen.edit', [
            'person' => $person->load(['company', 'department', 'businessUnit', 'permissionTemplate', 'legacyRole', 'user']),
            'companies' => Company::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get(),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $person->tenant_id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $person->tenant_id)],
            'business_unit_id' => ['nullable', 'integer', Rule::exists('business_units', 'id')->where('tenant_id', $person->tenant_id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
        $validated['active'] = $request->boolean('active');

        $person->update($validated);

        return redirect()->route('admin.personen.edit', $person)->with('status', 'person-updated');
    }

    /**
     * Login-Zugang für eine bisherige Kontaktperson (ohne User) anlegen -
     * entspricht dem Rollen-Aufstieg 4 -> 3 aus dem CLAUDE.md-Rollenmodell.
     * Rechte-Set/Rolle wird bewusst NICHT hier gesetzt, sondern separat über
     * die Rechte-Verwaltung.
     */
    public function createLogin(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);
        abort_if($person->user, 422);

        $validated = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:4'],
        ])->validate();

        User::query()->create([
            'tenant_id' => $person->tenant_id,
            'person_id' => $person->id,
            'name' => $person->fullName(),
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return redirect()->route('admin.personen.edit', $person)->with('status', 'login-created');
    }

    public function resetPassword(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($person->user, 404);

        $validated = $request->validate(['password' => ['required', 'string', 'min:4']]);

        $person->user->update(['password' => $validated['password']]);

        return redirect()->route('admin.personen.edit', $person)->with('status', 'password-reset');
    }
}
