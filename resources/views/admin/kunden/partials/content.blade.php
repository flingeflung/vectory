<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Kunden, analog zur Workflow-/Mail-Vorlagen-Liste. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.kunden')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newTenant: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Kunden') }}</span>
                <button type="button" @click="newTenant = !newTenant; if (newTenant) $nextTick(() => $refs.newTenantName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newTenant" x-cloak method="POST" action="{{ route('admin.kunden.store') }}" class="mb-2 space-y-2 rounded border border-gray-200 p-2" @input="window.__tenantsDirtyForms.add($el)" @submit="window.__tenantsDirtyForms.delete($el)">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                        <input type="text" name="name" x-ref="newTenantName" required class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                        <input type="text" name="short_name" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Projektpfad') }}</label>
                        <input type="text" name="project_path" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }}</label>
                        <input type="email" name="notification_email" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    @if ($tenants->isNotEmpty())
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Als Kopie von') }}</label>
                            <select name="source_tenant_id" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                <option value="">{{ __('– Keine Vorlage –') }}</option>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Übernimmt Funktionsgruppen, Abteilungen, Geschäftsbereiche, Rollen, Workflows, Projektarten und Märkte des gewählten Kunden - keine Personen, Projekte oder Subunternehmer.') }}</p>
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="newTenant = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                            {{ __('Abbrechen') }}
                        </button>
                        <button type="submit" class="rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Anlegen') }}
                        </button>
                    </div>
                </form>

                @if ($tenants->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Kunden angelegt.') }}</div>
                @else
                    @foreach ($tenants as $tenant)
                        <a
                            :href="navUrl({ tenant: {{ $tenant->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedTenant?->id === $tenant->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selectedTenant?->id === $tenant->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $tenant->name }}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: der gewählte Kunde zum Bearbeiten, oder ein Hinweis, wenn keiner ausgewählt ist. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedTenant)
            <div class="flex min-h-0 flex-1 flex-col" x-data="{ dirty: false }">
                {{-- Speichern- und Lösch-Formular als Geschwister, nicht
                     verschachtelt (siehe Mail-Vorlagen: ein <form> im
                     <form> zieht das versteckte "_method=DELETE"-Feld ins
                     äußere Formular, Speichern löscht dann tatsächlich). --}}
                <form
                    id="tenant-form-{{ $selectedTenant->id }}"
                    method="POST"
                    action="{{ route('admin.kunden.update', $selectedTenant) }}"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3"
                    @input="dirty = window.formIsDirty($el, window.__tenantsDirtyForms)"
                    @submit="dirty = false; window.__tenantsDirtyForms.delete($el)"
                >
                    @csrf
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                            <input type="text" name="name" value="{{ $selectedTenant->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                            <input type="text" name="short_name" value="{{ $selectedTenant->short_name }}" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Projektpfad') }}</label>
                        <input type="text" name="project_path" value="{{ $selectedTenant->project_path }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }}</label>
                        <input type="email" name="notification_email" value="{{ $selectedTenant->notification_email }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                </form>

                @unless ($selectedTenant->hasData())
                    <form x-ref="deleteForm" method="POST" action="{{ route('admin.kunden.destroy', $selectedTenant) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endunless
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    @unless ($selectedTenant->hasData())
                        <button
                            type="button"
                            @click="window.deleteWithConfirm($refs.deleteForm, {
                                message: {{ \Illuminate\Support\Js::from(__('Dieser Kunde hat noch keine Daten und kann gefahrlos gelöscht werden.')) }},
                            })"
                            class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                        >
                            {{ __('Löschen') }}
                        </button>
                    @else
                        <span></span>
                    @endunless
                    <button type="submit" form="tenant-form-{{ $selectedTenant->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links einen Kunden aus, um ihn zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
