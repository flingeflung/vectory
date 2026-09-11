<form
    method="POST"
    action="{{ route('projekte.verknuepfungen.store', $project) }}"
    class="space-y-3"
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
    }"
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

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-connection-add' }))"
            class="rounded border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" :disabled="!selected" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
            {{ __('Verknüpfen') }}
        </button>
    </div>
</form>
