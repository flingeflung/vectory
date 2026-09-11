<x-admin-layout>
    @if (session('status'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            {{ session('status') === 'person-deleted' ? __('Person gelöscht.') : __('Gespeichert.') }}
        </x-flash-message>
    @endif

    {{-- Kopf fix (Filter + Neue Person), nur die Tabelle scrollt - siehe
         CLAUDE.md-Konvention "Boxen mit Kopf-/Fußbereich + Liste". --}}
    <div id="personen-content" class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        <div class="shrink-0 flex flex-wrap items-end gap-3 border-b border-gray-100 p-3">
            <form method="GET" action="{{ route('admin.personen') }}" class="flex flex-1 flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Nachname') }}</label>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        oninput="window.liveFilterSearch(this, 'personen-list')"
                        class="mt-0.5 rounded-md border-gray-300 text-sm"
                    >
                </div>
                @if ($canSearchAllTenants)
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kunde') }}</label>
                        <select name="tenant_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                            <option value="all" @selected(request('tenant_id') === 'all')>{{ __('– Alle –') }}</option>
                            <option value="" @selected(! request()->filled('tenant_id'))>{{ __('Aktiver Kunde (+ Zugriff)') }}</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->short_name ?? $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-1">
                        <label class="block text-xs text-gray-500">{{ \App\Models\SystemSetting::companyLabel() }}</label>
                        <x-manage-lookup-button modal="company-manager" :title="\App\Models\SystemSetting::companyLabelPlural().' verwalten'" />
                    </div>
                    <select id="filter-company_id" name="company_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="none" @selected(request('company_id') === 'none')>{{ __('– nicht zugewiesen –') }}</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="block text-xs text-gray-500">{{ __('Abteilung') }}</label>
                        <x-manage-lookup-button modal="department-manager" :title="__('Abteilungen verwalten')" />
                    </div>
                    <select id="filter-department_id" name="department_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="none" @selected(request('department_id') === 'none')>{{ __('– nicht zugewiesen –') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="block text-xs text-gray-500">{{ __('Geschäftsbereich') }}</label>
                        <x-manage-lookup-button modal="business-unit-manager" :title="__('Geschäftsbereiche verwalten')" />
                    </div>
                    <select id="filter-business_unit_id" name="business_unit_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="none" @selected(request('business_unit_id') === 'none')>{{ __('– nicht zugewiesen –') }}</option>
                        @foreach ($businessUnits as $unit)
                            <option value="{{ $unit->id }}" @selected(request('business_unit_id') == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Rechte-Set') }}</label>
                    <select name="permission_template_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="none" @selected(request('permission_template_id') === 'none')>{{ __('– nicht zugewiesen –') }}</option>
                        @foreach ($permissionTemplates as $template)
                            <option value="{{ $template->id }}" @selected(request('permission_template_id') == $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="block text-xs text-gray-500">{{ __('Rolle') }}</label>
                        <x-manage-lookup-button modal="legacy-role-manager" :title="__('Rollen verwalten')" />
                    </div>
                    <select id="filter-legacy_role_id" name="legacy_role_id" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="none" @selected(request('legacy_role_id') === 'none')>{{ __('– nicht zugewiesen –') }}</option>
                        @foreach ($legacyRoles as $role)
                            <option value="{{ $role->id }}" @selected(request('legacy_role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Typ') }}</label>
                    <select name="typ" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="login" @selected(request('typ') === 'login')>{{ __('Login-User') }}</option>
                        <option value="kontakt" @selected(request('typ') === 'kontakt')>{{ __('Kontaktperson') }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-1.5 pb-1.5 text-xs text-gray-600">
                    <input type="checkbox" name="show_inactive" value="1" @checked(request()->boolean('show_inactive')) onchange="this.form.submit()" class="rounded border-gray-300">
                    {{ __('Inaktive zeigen') }}
                </label>
                @if (request()->anyFilled(['search', 'company_id', 'department_id', 'business_unit_id', 'permission_template_id', 'legacy_role_id', 'typ', 'tenant_id']) || request()->boolean('show_inactive'))
                    <a href="{{ route('admin.personen') }}" class="mb-1.5 inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Filter zurücksetzen') }}</a>
                @endif
            </form>

            <form
                method="POST"
                action="{{ route('admin.personen.store') }}"
                x-data="{ async createAndOpen(e) {
                    const response = await fetch(e.target.action, {
                        method: 'POST',
                        headers: { 'X-Overlay': '1', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                        body: new FormData(e.target),
                    });
                    const data = await response.json();
                    window.dispatchEvent(new CustomEvent('open-person', { detail: { id: data.id, filters: {{ \Illuminate\Support\Js::from($filters) }} } }));
                    window.refreshPersonenListInBackground?.();
                } }"
                @submit.prevent="createAndOpen($event)"
            >
                @csrf
                <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                    + {{ __('Neue Person') }}
                </button>
            </form>
        </div>

        <div id="personen-list" class="flex-1 min-h-0 overflow-y-auto">
            <div class="px-3 py-1.5 text-xs text-gray-400">
                @if ($canSearchAllTenants)
                    @php
                        $tenantFilterValue = $filters['tenant_id'] ?? '';
                        if ($tenantFilterValue === 'all') {
                            $searchCriteria = __('Alle Kunden');
                        } elseif ($tenantFilterValue !== '') {
                            $filterTenant = $tenants->firstWhere('id', (int) $tenantFilterValue);
                            $searchCriteria = __('Personen des Kunden :tenant', ['tenant' => $filterTenant?->short_name ?? $filterTenant?->name ?? '?']);
                        } else {
                            $activeTenant = \App\Support\CurrentTenant::current();
                            $searchCriteria = __('Personen des Kunden :tenant + solche mit Zugriff darauf', ['tenant' => $activeTenant?->short_name ?? $activeTenant?->name ?? '?']);
                        }
                    @endphp
                    {{ $searchCriteria }}:
                @endif
                {{ trans_choice(':count Person gefunden|:count Personen gefunden', $people->count(), ['count' => $people->count()]) }}
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="sticky top-0 bg-white text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Kürzel') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Typ') }}</th>
                        @if ($multiTenantEnabled)
                            <th class="px-3 py-2 text-left">{{ __('Kunde') }}</th>
                        @endif
                        <th class="px-3 py-2 text-left">{{ \App\Models\SystemSetting::companyLabel() }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Rolle') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Abteilung') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Geschäftsbereich') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Rechte-Set') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('E-Mail') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Letzter Login') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($people as $person)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 {{ $person->active ? '' : 'text-gray-400' }}">
                                <x-person-link :person="$person" :filters="$filters" />{{ ! $person->active ? ' [i]' : '' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600" title="{{ $person->fullName() }}">{{ $person->short_name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->user ? __('Login-User') : __('Kontaktperson') }}</td>
                            @if ($multiTenantEnabled)
                                <td class="px-3 py-2 text-gray-600" title="{{ $person->tenant?->name }}">{{ $person->tenant?->short_name ?? $person->tenant?->name ?? '–' }}</td>
                            @endif
                            <td class="px-3 py-2 text-gray-600">{{ $person->company?->name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->legacyRole?->name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->department?->name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->businessUnit?->name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->permissionTemplate?->name ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->email ?? '–' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $person->last_login_at?->format('d.m.Y H:i') ?? '–' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $multiTenantEnabled ? 11 : 10 }}" class="px-3 py-6 text-center text-gray-400">{{ __('Keine Personen gefunden.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
