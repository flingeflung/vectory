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
                    <button type="button" @click="newSet = !newSet; if (newSet) $nextTick(() => $refs.newSetName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        + {{ __('Neu') }}
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                    <form x-show="newSet" x-cloak method="POST" action="{{ route('admin.maerkte.gruppen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                        <input type="text" name="name" x-ref="newSetName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                        @csrf
                        <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
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
                        {{ __('Hier definieren, welche Zielmärkte relevant sind und wo Übersetzung/Lokalisierung dafür erstellt wird.') }}
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
                            class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
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
                @input="dirty = window.formIsDirty($el)"
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
                                        @php
                                            $pairKey = "{$country->id}-{$language->id}";
                                            $existingMarket = $existingMarkets->get($pairKey);
                                            // "keine Übersetzung" ist eine Eigenschaft des Markt-Datensatzes
                                            // selbst, unabhängig von der aktuell betrachteten Ländergruppe -
                                            // nur zeigen, wenn die Sprache hier auch tatsächlich angehakt ist,
                                            // sonst wirkt "Sprache aus, aber keine Übersetzung an" widersprüchlich.
                                            $showNoTranslation = $existingMarket && (! $selectedSet || $checkedPairs->contains($pairKey));
                                        @endphp
                                        <span
                                            class="mr-3 inline-flex items-center gap-1 py-0.5"
                                            x-data="{
                                                @if ($selectedSet) active: {{ $checkedPairs->contains($pairKey) ? 'true' : 'false' }}, @endif
                                                @if ($existingMarket) translated: {{ $existingMarket->no_translation ? 'false' : 'true' }}, @endif
                                            }"
                                        >
                                            <label class="inline-flex items-center gap-1">
                                                @if ($selectedSet)
                                                    <input type="checkbox" name="pairs[]" value="{{ $pairKey }}" class="rounded border-gray-300" @checked($checkedPairs->contains($pairKey)) @change="active = $event.target.checked">
                                                @endif
                                                <span class="text-gray-700">{{ $language->name }} <span class="text-gray-400">{{ $language->code }}</span></span>
                                            </label>
                                            @if ($showNoTranslation)
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center"
                                                    :class="translated ? 'text-gray-700' : 'text-gray-300'"
                                                    :title="translated ? {{ \Illuminate\Support\Js::from(__('Wird übersetzt')) }} : {{ \Illuminate\Support\Js::from(__('Wird nicht übersetzt')) }}"
                                                    @if ($selectedSet) x-show="active" @endif
                                                    @click="
                                                        translated = !translated;
                                                        fetch({{ \Illuminate\Support\Js::from(route('admin.maerkte.keine-uebersetzung.toggle', $existingMarket->id)) }}, {
                                                            method: 'POST',
                                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                                                        });
                                                    "
                                                >
                                                    <svg class="h-4 w-auto" viewBox="0 0 135.71 110.4" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill="currentColor" d="M23.05,81.53l-7.49,22.68H5.93L30.43,32.1h11.23l24.61,72.11h-9.95l-7.7-22.68h-25.57ZM46.7,74.25l-7.06-20.76c-1.6-4.71-2.68-8.99-3.75-13.16h-.21c-1.07,4.28-2.25,8.67-3.64,13.05l-7.06,20.86h21.72Z"/>
                                                        <path fill="currentColor" d="M122.24,23.52h7.54v-8h-29.04V6.19h-8v9.33h-29.04v8h50.48c-1.61,9.95-8.97,20.24-18.26,28.25-.16-.18-.33-.36-.49-.53-9.81-11.07-12.4-22.11-12.42-22.22l-3.9.87-3.91.86c.12.53,2.98,13.08,14.25,25.79.04.05.09.09.13.14-8.61,5.96-18.05,9.8-25.88,9.8v8c9.91,0,21.52-4.73,31.79-12.13,7.32,6.21,15.77,11.29,16.23,11.57l4.1-6.87c-.1-.06-7.41-4.46-13.96-9.8,10.67-9.42,18.85-21.61,20.37-33.73Z"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </span>
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
