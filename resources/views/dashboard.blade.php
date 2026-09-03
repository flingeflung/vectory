<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Startseite Test') }}
            </h2>
            <button
                type="button"
                x-data
                @click="$dispatch('open-modal', 'kacheln-verwalten')"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                {{ __('Kacheln verwalten') }}
            </button>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        {{-- Echtes Grid statt Flex-Wrap: da alle Kacheln jetzt dieselbe feste
             Höhe haben, packt ein Grid sie lückenlos (kein Freiraum unter einer
             "leichten" Kachel wie Meldungen). x-sort sitzt auf einem
             "display: contents"-Wrapper, damit dessen Kinder trotz eigenem
             DOM-Element direkt als Grid-Zellen neben Meldungen einsortiert
             werden - Meldungen selbst liegt AUSSERHALB dieses Wrappers und
             ist damit für SortableJS unsichtbar (bleibt so garantiert fix an
             Position 1, kann nicht durch Drag anderer Kacheln verschoben werden). --}}
        <div class="mx-auto grid max-w-7xl grid-cols-[repeat(auto-fill,20rem)] gap-4">
            {{-- Meldungen: immer fix zuerst, nicht verschiebbar, nicht abwählbar
                 (kommt später mit echtem Inhalt + Rechtefilterung). --}}
            <x-dashboard-tile :title="__('Meldungen')">
                <div class="text-gray-400">&ndash; {{ __('derzeit keine Meldungen') }} &ndash;</div>
            </x-dashboard-tile>

            <div
                x-data="{
                    saving: false,
                    async saveOrder() {
                        this.saving = true;
                        const keys = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                        await fetch({{ \Illuminate\Support\Js::from(route('dashboard.layout')) }}, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                            body: JSON.stringify({ tiles: keys }),
                        });
                        this.saving = false;
                    },
                }"
                x-sort="saveOrder()"
                id="dashboard-tiles"
                class="contents"
            >
                @foreach ($activeTiles as $tileKey)
                    <div x-sort:item="{{ \Illuminate\Support\Js::from($tileKey) }}">
                        @include('projekte.partials.dashboard-tiles.'.$tileKey)
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        // Öffnen eines Projekts im globalen Overlay ändert Daten, die hier
        // angezeigt werden (z.B. "Zuletzt geöffnete Projekte") - ohne das hier
        // stünde bis zum nächsten manuellen Reload der alte Stand (siehe
        // dasselbe Prinzip bei refreshProjekteListInBackground).
        window.refreshDashboardTiles = async function refreshDashboardTiles() {
            const current = document.getElementById('dashboard-tiles');
            if (!current) {
                return;
            }

            const html = await fetch(window.location.href).then((r) => r.text());
            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('dashboard-tiles');
            if (fresh) {
                current.innerHTML = fresh.innerHTML;
            }
        };
    </script>

    <x-modal name="kacheln-verwalten" max-width="md">
        <div class="p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900">{{ __('Kacheln verwalten') }}</h2>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'kacheln-verwalten' }))"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Schließen') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="mb-3 text-xs text-gray-500">{{ __('Die Reihenfolge kannst du direkt auf der Startseite per Ziehen ändern.') }}</p>

            <form method="POST" action="{{ route('dashboard.layout') }}" class="space-y-2 text-sm">
                @csrf
                @php
                    // Aktive Kacheln zuerst, in ihrer aktuellen (per Drag&Drop
                    // sortierten) Reihenfolge - sonst würde ein simples Ab-/Anhaken
                    // hier die auf der Startseite gezogene Reihenfolge zurücksetzen.
                    $orderedTiles = collect($activeTiles)
                        ->map(fn ($key) => collect($allTiles)->firstWhere('key', $key))
                        ->filter()
                        ->concat(collect($allTiles)->whereNotIn('key', $activeTiles));
                @endphp
                @foreach ($orderedTiles as $tile)
                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2">
                        <input type="checkbox" name="tiles[]" value="{{ $tile['key'] }}" class="rounded border-gray-300" @checked(in_array($tile['key'], $activeTiles, true))>
                        {{ $tile['label'] }}
                    </label>
                @endforeach

                <div class="flex justify-end pt-2">
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-xs font-medium text-white hover:bg-gray-700">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</x-app-layout>
