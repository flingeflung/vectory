<x-admin-layout>
    @if (session('status') === 'maerkte-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div class="flex flex-1 min-h-0 gap-4">
        {{-- Links: nur die Ländergruppen - Märkte selbst werden nicht mehr
             separat verwaltet, sondern entstehen implizit beim Anhaken
             rechts (siehe Rechts-Kommentar). --}}
        <div
            x-data="{
                navUrl(params) {
                    const url = new URL({{ \Illuminate\Support\Js::from(route('admin.maerkte')) }}, window.location.origin);
                    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                    return url.pathname + url.search;
                },
            }"
            class="flex w-72 shrink-0 flex-col"
        >
            <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newSet: false }">
                <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                    <span class="text-xs font-semibold text-gray-500">{{ __('Ländergruppen') }}</span>
                    <button type="button" @click="newSet = !newSet; if (newSet) $nextTick(() => $refs.newSetName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                        + {{ __('Neu') }}
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                    <form x-show="newSet" x-cloak method="POST" action="{{ route('admin.maerkte.gruppen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                        <input type="text" name="name" x-ref="newSetName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                        @csrf
                        <button type="submit" class="shrink-0 rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">
                            {{ __('Anlegen') }}
                        </button>
                    </form>

                    @forelse ($sets as $set)
                        <a
                            :href="navUrl({ gruppe: {{ $set->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedSet?->id === $set->id) data-selected @endif
                            class="block rounded px-2 py-1 {{ $selectedSet?->id === $set->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $set->name }}
                        </a>
                    @empty
                        <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Ländergruppen angelegt.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Rechts: der komplette globale Land+Sprache-Katalog (alle
             Länder mit ihren jeweils relevanten Sprachen, siehe
             Country::languages()). Ohne ausgewählte Ländergruppe reine
             Ansicht ohne Häkchen (wie der Rechte-Katalog); mit
             ausgewählter Gruppe bekommt jede Sprache einen Haken -
             anhaken+speichern legt den Markt für diesen Kunden an (falls
             noch nicht vorhanden) und ordnet ihn der Gruppe zu. --}}
        <div
            x-data="{
                search: '',
                dirty: false,
                filterRow(text) {
                    return !this.search || text.toLowerCase().includes(this.search.toLowerCase());
                },
            }"
            x-init="window.adminPageIsDirty = () => dirty;"
            class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white"
        >
            <div class="shrink-0 flex items-center justify-between gap-3 border-b border-gray-100 p-3">
                <div>
                    <div class="text-sm font-medium text-gray-900">
                        {{ $selectedSet ? $selectedSet->name : __('Markt-Katalog') }}
                    </div>
                    <p class="text-xs text-gray-400">
                        @if ($selectedSet)
                            {{ __('Hake die Sprachen an, die für diese Ländergruppe gelten, und speichere.') }}
                        @else
                            {{ __('Wähle links eine Ländergruppe aus, um Sprachen zuzuordnen.') }}
                        @endif
                        {{ __('Ländercodes nach ISO 3166-1, Sprachcodes nach ISO 639-1.') }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <input
                        type="search"
                        x-model="search"
                        placeholder="{{ __('Filter (Land/Sprache)') }}"
                        class="w-56 rounded-md border-gray-300 py-1 text-sm"
                    >
                    @if ($selectedSet)
                        <button
                            type="submit"
                            form="country-language-form"
                            x-show="dirty"
                            x-cloak
                            class="shrink-0 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                        >
                            {{ __('Speichern') }}
                        </button>
                        <form method="POST" action="{{ route('admin.maerkte.gruppen.destroy', $selectedSet) }}" x-ref="deleteSetForm" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                        <button
                            type="button"
                            @click="window.deleteWithConfirm($refs.deleteSetForm, { message: {{ \Illuminate\Support\Js::from(__('Diese Ländergruppe wirklich endgültig löschen? Die zugeordneten Märkte selbst bleiben erhalten.')) }} })"
                            class="shrink-0 rounded-md border border-red-300 px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                        >
                            {{ __('Löschen') }}
                        </button>
                    @endif
                </div>
            </div>

            <form
                id="country-language-form"
                method="POST"
                action="{{ $selectedSet ? route('admin.maerkte.gruppen.mitglieder.update', $selectedSet) : '#' }}"
                @input="dirty = true"
                @submit="dirty = false"
                class="flex-1 min-h-0 overflow-y-auto"
            >
                @csrf
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($countries as $country)
                            <tr x-show="filterRow({{ \Illuminate\Support\Js::from(mb_strtolower($country->name.' '.$country->iso.' '.$country->languages->pluck('name')->implode(' ').' '.$country->languages->pluck('code')->implode(' '))) }})">
                                <td class="w-56 px-3 py-2 align-top text-gray-700">
                                    @if ($country->iconUrl())
                                        <img src="{{ $country->iconUrl() }}" alt="{{ $country->iso }}" class="mr-1.5 inline-block h-3 w-auto align-middle">
                                    @endif
                                    {{ $country->name }} <span class="text-gray-400">{{ $country->iso }}</span>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    @forelse ($country->languages as $language)
                                        @php $pairKey = "{$country->id}-{$language->id}"; @endphp
                                        <label class="mr-3 inline-flex items-center gap-1 py-0.5">
                                            @if ($selectedSet)
                                                <input type="checkbox" name="pairs[]" value="{{ $pairKey }}" class="rounded border-gray-300" @checked($checkedPairs->contains($pairKey))>
                                            @endif
                                            <span class="text-gray-700">{{ $language->name }} <span class="text-gray-400">{{ $language->code }}</span></span>
                                        </label>
                                    @empty
                                        <span class="text-gray-300">–</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-6 text-center text-gray-400">{{ __('Kein Länder-Katalog vorhanden.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</x-admin-layout>
