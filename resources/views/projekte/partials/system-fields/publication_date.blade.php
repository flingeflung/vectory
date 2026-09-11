<div>
    <label class="block text-xs text-gray-500">{{ __('Publikationsdatum') }}</label>
    {{-- Nur mit eigenem Recht änderbar - die TR ist fürs Publizieren
         zuständig, das tatsächliche Datum wird hier protokollarisch
         eingetragen (Vietto-Vorbild: landet in den Vorgängen). --}}
    <input
        type="date"
        name="publication_date"
        value="{{ old('publication_date', $project->publication_date?->format('Y-m-d')) }}"
        @disabled(! auth()->user()->can('project.publication_date.edit'))
        class="mt-0.5 rounded border-gray-300 py-1 text-sm disabled:bg-gray-50 disabled:text-gray-400"
    >
</div>
