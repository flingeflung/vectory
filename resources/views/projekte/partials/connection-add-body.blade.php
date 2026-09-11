<div
    x-data="{
        term: '', results: [], selected: null, open: false, debounceTimer: null,
        onInput() {
            this.selected = null;
            clearTimeout(this.debounceTimer);
            const value = this.term.trim();
            if (value.length < 3) { this.results = []; this.open = false; return; }
            this.open = true;
            this.debounceTimer = setTimeout(() => this.fetchResults(value), 300);
        },
        async fetchResults(value) {
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.schnellsuche')) }} + '?q=' + encodeURIComponent(value));
            this.results = response.ok ? (await response.json()).filter((p) => p.id !== {{ $project->id }}) : [];
        },
        pick(item) {
            this.selected = item;
            this.term = item.pn + ' – ' + item.title;
            this.results = [];
            this.open = false;
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
    {{-- Ralf, 2026-09-11 (S2, Vietto-Vorbild "Verbundene Projekte"): bestehende
         Verknüpfungen und das Hinzufügen neuer in einem Modal, bleibt beim
         Hinzufügen/Entfernen offen - mehrere Verknüpfungen lassen sich so
         nacheinander anlegen, ohne das Modal jedes Mal neu zu öffnen. --}}
    <div class="mb-3 space-y-1">
        @forelse ($connections as $entry)
            <div class="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs">
                <div class="min-w-0">
                    <span class="text-gray-500">{{ $entry->label }}:</span>
                    <span class="font-medium text-gray-900">{{ $entry->otherProject->source_pn }} &ndash; {{ $entry->otherProject->title }}</span>
                </div>
                <button type="button" @click="removeConnection({{ $entry->connection->id }})" class="shrink-0 text-gray-400 hover:text-red-600" title="{{ __('Verknüpfung entfernen') }}">
                    &times;
                </button>
            </div>
        @empty
            <div class="text-xs text-gray-400">{{ __('Noch keine Verknüpfungen.') }}</div>
        @endforelse
    </div>

    <form
        method="POST"
        action="{{ route('projekte.verknuepfungen.store', $project) }}"
        class="space-y-3 border-t border-gray-200 pt-3"
        @submit.prevent="
            fetch($el.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: new FormData($el),
            }).then((response) => {
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Verknüpfen fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.')) }});
                    return;
                }
                window.reloadProjectConnectionModal();
            });
        "
    >
        @csrf
        <div class="relative">
            <label class="block text-xs text-gray-500">{{ __('Projekt suchen (PN oder Bezeichnung)') }}</label>
            <input
                type="text"
                x-model="term"
                @input="onInput()"
                @focus="if (results.length) open = true"
                @click.outside="open = false"
                required
                autocomplete="off"
                class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
            >
            <input type="hidden" name="related_project_id" :value="selected?.id ?? ''">
            <div x-show="open && results.length" x-cloak class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-gray-200 bg-white text-sm shadow-lg">
                <template x-for="item in results" :key="item.id">
                    <button type="button" @click="pick(item)" class="block w-full px-2 py-1.5 text-left hover:bg-gray-50">
                        <span class="font-medium" x-text="item.pn"></span> &ndash; <span x-text="item.title"></span>
                    </button>
                </template>
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500">{{ __('Bezeichnung dieser Richtung (von diesem Projekt aus)') }}</label>
            <input type="text" name="label" required list="connection-label-suggestions" placeholder="{{ __('z. B. Vorlage für') }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500">{{ __('Bezeichnung der Rückrichtung') }}</label>
            <input type="text" name="label_reverse" required list="connection-label-suggestions" placeholder="{{ __('z. B. Kopie von') }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <datalist id="connection-label-suggestions">
            @foreach ($labelSuggestions as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>

        <div class="flex justify-end gap-2">
            <button type="submit" :disabled="!selected" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('Hinzufügen') }}
            </button>
        </div>
    </form>
</div>
