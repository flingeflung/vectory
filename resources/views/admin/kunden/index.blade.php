<x-admin-layout>
    @if (session('status') === 'tenant-updated')
        <x-flash-message class="mb-3 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    {{-- Gleiches Dirty-Tracking-Muster wie die Konfig-Seite (mehrere
         unabhängige Formulare, je eine Zeile pro Kunde) - siehe dortiger
         Kommentar für die x-init-vs-Script-Stolperfalle. --}}
    <script>
        window.__configDirtyForms = new Set();
    </script>
    <div class="max-w-2xl" x-data x-init="window.adminPageIsDirty = () => window.__configDirtyForms.size > 0">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">{{ __('Kunden verwalten') }}</div>
            <p class="mb-3 text-xs text-gray-400">{{ __('Jeder Kunde ist ein eigener, vollständig getrennter Mandant, mit eigenem Projektpfad - die Projektverzeichnisse können sehr groß werden, ein eigener Server pro Kunde ist möglich.') }}</p>

            <div x-data="{ creating: false }">
                <div x-show="!creating">
                    <button type="button" @click="creating = true; $nextTick(() => $refs.newTenantName.focus())" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        + {{ __('Neuer Kunde') }}
                    </button>
                </div>
                <form
                    x-show="creating"
                    x-cloak
                    method="POST"
                    action="{{ route('admin.kunden.store') }}"
                    @input="window.__configDirtyForms.add($el)"
                    class="space-y-2 rounded-md border border-gray-200 p-2"
                >
                    @csrf
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                            <input type="text" name="name" x-ref="newTenantName" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                            <input type="text" name="short_name" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Projektpfad') }}</label>
                        <input type="text" name="project_path" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }}</label>
                        <input type="email" name="notification_email" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    @if ($tenants->isNotEmpty())
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Als Kopie von') }}</label>
                            <select name="source_tenant_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('– Keine Vorlage –') }}</option>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Übernimmt Funktionsgruppen, Abteilungen, Geschäftsbereiche, Rollen, Workflows, Projektarten und Märkte des gewählten Kunden - keine Personen, Projekte oder Subunternehmer.') }}</p>
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="creating = false" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ __('Abbrechen') }}
                        </button>
                        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                            {{ __('Anlegen') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($tenants as $tenant)
                    <div class="rounded-md border border-gray-200 p-2" x-data="{}">
                        <form
                            method="POST"
                            action="{{ route('admin.kunden.update', $tenant) }}"
                            x-data="{ dirty: false }"
                            @input="dirty = true; window.__configDirtyForms.add($el)"
                            class="space-y-2"
                        >
                            @csrf
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                                    <input type="text" name="name" value="{{ $tenant->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div class="w-24">
                                    <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                                    <input type="text" name="short_name" value="{{ $tenant->short_name }}" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('Projektpfad') }}</label>
                                <input type="text" name="project_path" value="{{ $tenant->project_path }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }}</label>
                                <input type="email" name="notification_email" value="{{ $tenant->notification_email }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" x-show="dirty" x-cloak class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    {{ __('Speichern') }}
                                </button>
                            </div>
                        </form>

                        @unless ($tenant->hasData())
                            <form method="POST" action="{{ route('admin.kunden.destroy', $tenant) }}" x-ref="deleteForm" class="hidden">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="reassign_to" value="">
                            </form>
                            <div class="mt-2 flex justify-end">
                                <button
                                    type="button"
                                    @click="window.deleteWithConfirm($refs.deleteForm, {
                                        message: {{ \Illuminate\Support\Js::from(__('Dieser Kunde hat noch keine Daten und kann gefahrlos gelöscht werden.')) }},
                                    })"
                                    class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    {{ __('Löschen') }}
                                </button>
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="text-sm text-gray-400">{{ __('Noch keine Kunden angelegt.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
