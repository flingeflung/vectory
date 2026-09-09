<div id="projektkategorien-content" class="flex flex-1 min-h-0 gap-4">
    {{-- Links: Projektkategorien (Vietto: Projekttyp) - echte
         1:n-Baumstruktur, keine Mehrfachzuordnung wie bei Märkten. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.projektkategorien')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-72 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newCategory: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Projektkategorien') }}</span>
                <button type="button" @click="newCategory = !newCategory; if (newCategory) $nextTick(() => $refs.newCategoryName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newCategory" x-cloak method="POST" action="{{ route('admin.projektkategorien.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newCategoryName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Anlegen') }}
                    </button>
                </form>

                @if ($categories->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Projektkategorien angelegt.') }}</div>
                @else
                    <div
                        x-data="{
                            async saveOrder() {
                                const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                await fetch({{ \Illuminate\Support\Js::from(route('admin.projektkategorien.reorder')) }}, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                    body: JSON.stringify({ categories: ids }),
                                });
                            },
                        }"
                        x-sort="saveOrder()"
                    >
                        @foreach ($categories as $category)
                            <div x-sort:item="{{ $category->id }}" class="flex items-center gap-1 rounded {{ $selectedCategory?->id === $category->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                <a
                                    :href="navUrl({ kategorie: {{ $category->id }} })"
                                    onclick="return window.navigateOrConfirm(event)"
                                    @if ($selectedCategory?->id === $category->id) data-selected @endif
                                    class="flex flex-1 items-center justify-between py-1 pr-2 {{ $selectedCategory?->id === $category->id ? 'font-medium text-indigo-700' : ($category->active ? 'text-gray-700' : 'text-gray-400') }}"
                                >
                                    <span>{{ $category->name }}{{ ! $category->active ? ' [i]' : '' }}</span>
                                    <span class="text-xs text-gray-400">{{ $category->subs->count() }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: entweder die Arten der ausgewählten Kategorie (mit
         Umbenennen/Löschen der Kategorie selbst oben), oder - nichts
         ausgewählt - der komplette Katalog nur zur Ansicht (gleiches
         Muster wie Rechte-/Markt-Katalog). --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedCategory)
            <div
                x-data="{}"
                x-init="window.adminPageIsDirty = () => window.__projektkategorienDirtyForms.size > 0;"
                class="flex flex-1 min-h-0 flex-col"
            >
            <form
                method="POST"
                action="{{ route('admin.projektkategorien.update', $selectedCategory) }}"
                data-row-form
                class="shrink-0 border-b border-gray-100 p-3"
                x-data="{ dirty: false }"
                @input="dirty = true; window.__projektkategorienDirtyForms.add($el)"
                @submit="dirty = false; window.__projektkategorienDirtyForms.delete($el)"
            >
                @csrf
                <div class="flex items-center gap-3">
                    <input type="text" name="name" value="{{ $selectedCategory->name }}" required class="flex-1 rounded-md border-gray-300 py-1 text-sm font-medium text-gray-900">
                    <label class="flex shrink-0 items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" name="active" value="1" @checked($selectedCategory->active) class="rounded border-gray-300">
                        {{ __('Aktiv') }}
                    </label>
                    <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </div>
                <p class="mt-1 text-xs text-gray-400">{{ __('Wenn eine Kategorie inaktiv ist, bleibt sie bei bestehenden Projekten sichtbar, ist aber für neue nicht mehr wählbar.') }}</p>
            </form>

            <div
                class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2"
                x-data="{ newArt: false }"
            >
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold text-gray-500">{{ __('Projektarten') }}</div>
                    <button type="button" @click="newArt = !newArt; if (newArt) $nextTick(() => $refs.newArtName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        + {{ __('Neu') }}
                    </button>
                </div>

                <form x-show="newArt" x-cloak method="POST" action="{{ route('admin.projektkategorien.arten.store') }}" class="flex items-center gap-2 rounded-md border border-gray-200 p-2">
                    @csrf
                    <input type="hidden" name="project_type_main_id" value="{{ $selectedCategory->id }}">
                    <input type="text" name="name" x-ref="newArtName" placeholder="{{ __('Name') }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                    <button type="button" @click="newArt = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Anlegen') }}</button>
                </form>

                @if ($selectedCategory->subs->isNotEmpty())
                    <div
                        x-data="{
                            async saveOrder() {
                                const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                await fetch({{ \Illuminate\Support\Js::from(route('admin.projektkategorien.arten.reorder')) }}, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                    body: JSON.stringify({ subs: ids }),
                                });
                            },
                        }"
                        x-sort="saveOrder()"
                        class="space-y-2"
                    >
                @endif
                @forelse ($selectedCategory->subs as $sub)
                    @php
                        $subUsage = $usageBySub->get($sub->id, 0);
                        $subReassignOptions = $categories->map(fn ($optGroup) => [
                            'group' => $optGroup->name,
                            'options' => $optGroup->subs->where('id', '!=', $sub->id)->map(fn ($target) => [
                                'value' => (string) $target->id,
                                'label' => $target->name.(! $target->active ? ' [i]' : ''),
                            ])->values(),
                        ])->values();
                    @endphp
                    <div x-sort:item="{{ $sub->id }}" x-data="{ rowDirty: false }" class="rounded-md border border-gray-200 p-2">
                        <form method="POST" action="{{ route('admin.projektkategorien.arten.update', $sub) }}" data-row-form class="flex items-center gap-2" @input="rowDirty = true; window.__projektkategorienDirtyForms.add($el)" @submit="rowDirty = false; window.__projektkategorienDirtyForms.delete($el)">
                            @csrf
                            <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Sortierung ändern') }}">⠿</span>
                            <input type="text" name="name" value="{{ $sub->name }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                            <select name="project_type_main_id" title="{{ __('In andere Kategorie verschieben') }}" class="w-40 shrink-0 rounded-md border-gray-300 text-xs">
                                @foreach ($categories as $target)
                                    <option value="{{ $target->id }}" @selected($target->id === $selectedCategory->id)>{{ $target->name }}</option>
                                @endforeach
                            </select>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="active" value="1" @checked($sub->active) class="rounded border-gray-300">
                                {{ __('Aktiv') }}
                            </label>
                            <button type="submit" x-show="rowDirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                {{ __('Speichern') }}
                            </button>
                        </form>
                        <div class="mt-1 pl-6 text-xs text-gray-400">{{ trans_choice(':count Projekt|:count Projekte', $subUsage, ['count' => $subUsage]) }}</div>
                        {{-- Lösch-Bestätigung im separaten globalen Overlay
                             statt inline an derselben Stelle wie der
                             Löschen-Button - Ralf: sonst trifft ein
                             hastiger Doppelklick versehentlich den
                             endgültigen Löschen-Button. --}}
                        <form method="POST" action="{{ route('admin.projektkategorien.arten.destroy', $sub) }}" x-ref="deleteForm" class="hidden">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="reassign_to" value="">
                        </form>
                        <div class="mt-1.5 flex justify-end">
                            <button
                                type="button"
                                @click="window.deleteWithConfirm($refs.deleteForm, {
                                    message: {{ \Illuminate\Support\Js::from($subUsage > 0 ? trans_choice('Wird bereits in :count Projekt verwendet.|Wird bereits in :count Projekten verwendet.', $subUsage, ['count' => $subUsage]).' '.__('Ohne Umhängen wird die Art dort auf „– nicht zugewiesen –“ gesetzt.') : __('Diese Art wirklich endgültig löschen?')) }},
                                    reassignOptions: @js($subUsage > 0 ? $subReassignOptions : []),
                                })"
                                class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                            >
                                {{ __('Löschen') }}
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-gray-200 p-3 text-sm text-gray-400">{{ __('Noch keine Arten in dieser Kategorie.') }}</div>
                @endforelse
                @if ($selectedCategory->subs->isNotEmpty())
                    </div>
                @endif
            </div>
            </div>

            <div class="shrink-0 border-t border-gray-100 p-3">
                @php
                    $mainUsage = $usageByMain->get($selectedCategory->id, 0);
                    $mainReassignOptions = $categories->where('id', '!=', $selectedCategory->id)->map(fn ($target) => [
                        'value' => (string) $target->id,
                        'label' => $target->name.(! $target->active ? ' [i]' : ''),
                    ])->values();
                @endphp
                @if ($selectedCategory->subs->isNotEmpty())
                    {{-- Kein anklickbarer Löschen-Button, der erst hinterher
                         erklärt, dass es gar nicht geht (Ralf: "das ist ja
                         in die Irre führen") - der Grund steht direkt da,
                         der Button ist von Anfang an sichtbar deaktiviert.
                         "Verschieben" bezieht sich auf die Kategorie-Auswahl
                         direkt in jeder Art-Zeile oben (nicht das
                         Sortier-Griffsymbol, das ist nur die Reihenfolge). --}}
                    <div class="flex items-center justify-end gap-2">
                        <span class="text-xs text-gray-400">{{ __('Zum Löschen der Kategorie: Erst alle Arten dieser Kategorie löschen oder in eine andere Kategorie verschieben.') }}</span>
                        <button type="button" disabled title="{{ __('Zum Löschen der Kategorie: Erst alle Arten löschen oder verschieben.') }}" class="cursor-not-allowed rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-400">
                            {{ __('Kategorie löschen') }}
                        </button>
                    </div>
                @else
                    {{-- Lösch-Bestätigung im separaten globalen Overlay,
                         siehe gleiche Begründung bei den Arten oben. --}}
                    <div x-data class="flex justify-end">
                        <form method="POST" action="{{ route('admin.projektkategorien.destroy', $selectedCategory) }}" x-ref="deleteCategoryForm" class="hidden">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="reassign_to" value="">
                        </form>
                        <button
                            type="button"
                            @click="window.deleteWithConfirm($refs.deleteCategoryForm, {
                                message: {{ \Illuminate\Support\Js::from($mainUsage > 0 ? trans_choice('Wird bereits in :count Projekt verwendet.|Wird bereits in :count Projekten verwendet.', $mainUsage, ['count' => $mainUsage]).' '.__('Ohne Umhängen wird die Kategorie dort auf „– nicht zugewiesen –“ gesetzt.') : __('Diese Kategorie wirklich endgültig löschen?')) }},
                                reassignOptions: @js($mainUsage > 0 ? $mainReassignOptions : []),
                            })"
                            class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                        >
                            {{ __('Kategorie löschen') }}
                        </button>
                    </div>
                @endif
            </div>
        @else
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Kategorien-Katalog') }}</div>
                <p class="text-xs text-gray-400">{{ __('Wähle links eine Projektkategorie aus, um ihre Arten zu bearbeiten.') }}</p>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-3">
                @forelse ($categories as $category)
                    <div>
                        <div class="text-sm font-medium {{ $category->active ? 'text-gray-700' : 'text-gray-400' }}">{{ $category->name }}{{ ! $category->active ? ' [i]' : '' }}</div>
                        <ul class="mt-1 space-y-0.5 pl-3 text-sm">
                            @forelse ($category->subs as $sub)
                                <li class="{{ $sub->active ? 'text-gray-500' : 'text-gray-300' }}">
                                    {{ $sub->name }}{{ ! $sub->active ? ' [i]' : '' }}
                                    <span class="text-gray-300">– {{ trans_choice(':count Projekt|:count Projekte', $usageBySub->get($sub->id, 0), ['count' => $usageBySub->get($sub->id, 0)]) }}</span>
                                </li>
                            @empty
                                <li class="text-gray-300">{{ __('– keine Arten –') }}</li>
                            @endforelse
                        </ul>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">{{ __('Noch keine Projektkategorien angelegt.') }}</div>
                @endforelse
            </div>
        @endif
    </div>
</div>
