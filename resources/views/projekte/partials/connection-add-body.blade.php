@php
    $connectedIds = $connections->pluck('otherProject.id')->all();
@endphp
<div
    x-data="{
        term: '', results: [], debounceTimer: null,
        addingId: null, addLabel: '', addLabelReverse: '',
        connectedIds: {{ \Illuminate\Support\Js::from($connectedIds) }},
        onInput() {
            this.addingId = null;
            clearTimeout(this.debounceTimer);
            const value = this.term.trim();
            if (value.length < 3) { this.results = []; return; }
            this.debounceTimer = setTimeout(() => this.fetchResults(value), 300);
        },
        async fetchResults(value) {
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.schnellsuche')) }} + '?q=' + encodeURIComponent(value));
            const data = response.ok ? await response.json() : [];
            this.results = data.filter((p) => p.id !== {{ $project->id }} && ! this.connectedIds.includes(p.id));
        },
        startAdd(item) {
            this.addingId = item.id;
            this.addLabel = '';
            this.addLabelReverse = '';
        },
        confirmAdd(item) {
            fetch({{ \Illuminate\Support\Js::from(route('projekte.verknuepfungen.store', $project)) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ related_project_id: item.id, label: this.addLabel, label_reverse: this.addLabelReverse }),
            }).then((response) => {
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Verknüpfen fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.')) }});
                    return;
                }
                this.term = '';
                this.results = [];
                window.reloadProjectConnectionModal();
            });
        },
        removeConnection(connectionId) {
            window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verknüpfung entfernen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Diese Verknüpfung wirklich entfernen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            }).then((ok) => {
                if (! ok) return;
                fetch(`/projekte/{{ $project->id }}/verknuepfungen/${connectionId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then(() => window.reloadProjectConnectionModal());
            });
        },
    }"
>
    {{-- Ralf, 2026-09-11 (S2, Vietto-Vorbild "Verbundene Projekte"): EINE
         Übersicht statt Suche-dann-Formular - Suchfeld oben, darunter immer
         die verknüpften Projekte (angehakt), darunter die dazu passenden
         Suchtreffer (nicht angehakt, anhaken klappt Bezeichnungsfelder auf). --}}
    <div>
        <label class="block text-xs text-gray-500">{{ __('Projekt suchen (PN oder Bezeichnung)') }}</label>
        <input
            type="text"
            x-model="term"
            @input="onInput()"
            required
            autocomplete="off"
            class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
        >
    </div>

    <div class="mt-3">
        <div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Verknüpfte Projekte') }}</div>
        @forelse ($connections as $entry)
            <div class="border-b border-gray-100 py-1 last:border-0">
                <label class="flex items-start gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" checked @click.prevent="removeConnection({{ $entry->connection->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
                    <span>{{ $entry->otherProject->source_pn }} &ndash; {{ $entry->otherProject->title }}</span>
                </label>
                <div class="ml-5 text-xs text-gray-400">{{ $entry->label }}</div>
            </div>
        @empty
            <div class="text-xs text-gray-400">{{ __('Noch keine Verknüpfungen.') }}</div>
        @endforelse
    </div>

    <div class="mt-3">
        <div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Andere Projekte') }}</div>
        <div x-show="term.trim() === ''" class="text-xs text-gray-400">{{ __('Zum Suchen tippen.') }}</div>
        <div x-show="term.trim() !== '' && results.length === 0" x-cloak class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
        <template x-for="item in results" :key="item.id">
            <div class="border-b border-gray-100 py-1 last:border-0">
                <label class="flex items-start gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" @click.prevent="addingId === item.id ? (addingId = null) : startAdd(item)" class="mt-0.5 shrink-0 rounded border-gray-300">
                    <span x-text="item.pn + ' – ' + item.title"></span>
                </label>
                <div x-show="addingId === item.id" x-cloak class="ml-5 mt-1 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
                    <input type="text" x-model="addLabel" list="connection-label-suggestions" placeholder="{{ __('Bezeichnung dieser Richtung (von diesem Projekt aus), z. B. Vorlage für') }}" class="w-full rounded-md border-gray-300 text-xs">
                    <input type="text" x-model="addLabelReverse" list="connection-label-suggestions" placeholder="{{ __('Bezeichnung der Rückrichtung, z. B. Kopie von') }}" class="w-full rounded-md border-gray-300 text-xs">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addingId = null" class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="button" :disabled="!addLabel.trim() || !addLabelReverse.trim()" @click="confirmAdd(item)" class="rounded bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Verknüpfen') }}</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <datalist id="connection-label-suggestions">
        @foreach ($labelSuggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
</div>
