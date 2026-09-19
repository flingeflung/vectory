{{--
    Print-Format (Vietto: "Format" in ajax_buildformate.php/ajax_formate_get.php,
    Schritt 4 des Print-Formate-Rebuilds, siehe Backlog-Memory). Zwei
    Varianten je nach ProjectTypeSub::format_type der aktuellen Projektart:
    - Standard-Formate: Anzeige der gewählten Kombination, Auswahl über ein
      eigenes Overlay (S2) mit den freigegebenen, per Heftung gefilterten
      Kombinationen - Speichern dort läuft sofort über eine eigene Route
      (ProjectFormatController), nicht über den großen Formular-Speichern-
      Button (gleiches Nachlade-Muster wie system_model/Produkt-Verknüpfung).
    - Variable Formate: zwei normale Freitext-Felder, laufen ganz normal im
      großen Speichern-Button mit (siehe ProjectController::update()).
    Formattyp 3 "Online-GA" kommt hier nicht vor - bewusst nicht Teil dieses
    Rebuilds, siehe Backlog.
--}}
@php
    $formatType = $project->projectTypeSub?->format_type;
@endphp
<div id="project-format-{{ $project->id }}">
    @if ($formatType === \App\Models\ProjectTypeSub::FORMAT_TYPE_STANDARD)
        @php
            $combination = $project->paperFormatCombination;
            $combinationInvalid = $combination && ! $combination->active;
        @endphp
        <div class="flex items-center gap-1.5">
            <label class="text-xs text-gray-500">{{ $field->label }}</label>
            @can('project.edit')
                <x-edit-icon-button modal="format-picker-{{ $project->id }}" :title="__('Format wählen')" />
            @endcan
        </div>
        <div class="mt-0.5 text-gray-700">
            @if ($combination)
                {{ $combination->inputFormat->name }} <span class="text-gray-400">&rArr;</span> {{ $combination->outputFormat->name }}
                @if ($combinationInvalid)
                    <span class="text-red-600" title="{{ __('Diese Kombination ist inzwischen nicht mehr freigegeben.') }}">!!!</span>
                @endif
            @else
                <span class="text-gray-400">{{ __('– nicht zugewiesen –') }}</span>
            @endif
        </div>
    @elseif ($formatType === \App\Models\ProjectTypeSub::FORMAT_TYPE_VARIABLE)
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-gray-500">{{ __('Ausgangsformat') }}</label>
                <input type="text" name="input_format_free_text" value="{{ old('input_format_free_text', $project->input_format_free_text) }}" placeholder="{{ __('z. B. 70 x 100 mm') }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Endformat') }}</label>
                <input type="text" name="output_format_free_text" value="{{ old('output_format_free_text', $project->output_format_free_text) }}" placeholder="{{ __('z. B. DIN A4 hoch') }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
            </div>
        </div>
    @endif
</div>

@if ($formatType === \App\Models\ProjectTypeSub::FORMAT_TYPE_STANDARD)
    @php
        $heftung = $project->attributes['heftung'] ?? null;
        $combinations = \App\Models\PaperFormatCombination::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('active', true)
            ->whereHas('inputFormat', fn ($q) => $q->where('active', true))
            ->whereHas('outputFormat', fn ($q) => $q->where('active', true))
            ->matchingHeftung($heftung)
            ->with(['inputFormat', 'outputFormat'])
            ->get()
            ->sortBy([fn ($c) => $c->inputFormat->sort, fn ($c) => $c->outputFormat->sort])
            ->values();
    @endphp
    <x-modal name="format-picker-{{ $project->id }}" max-width="lg">
        <div
            class="flex max-h-[75vh] flex-col"
            x-data="{
                search: '',
                selectedId: {{ $combination?->id ?? 'null' }},
                async save() {
                    if (! this.selectedId) return;
                    const fd = new FormData();
                    fd.append('paper_format_combination_id', this.selectedId);
                    const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.format.kombination', $project)) }}, {
                        method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    });
                    const html = await response.text();
                    const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('project-format-{{ $project->id }}');
                    const current = document.getElementById('project-format-{{ $project->id }}');
                    if (fresh && current) { current.replaceWith(fresh); }
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'format-picker-{{ $project->id }}' }));
                },
            }"
        >
            <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Standard-Papierformate') }}</h3>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'format-picker-{{ $project->id }}' }))"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Schließen') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="shrink-0 border-b border-gray-100 px-4 py-2">
                <input type="search" x-model="search" placeholder="{{ __('Format suchen') }}" class="w-full rounded-md border-gray-300 text-sm">
                @if ($heftung)
                    <p class="mt-1 text-xs text-gray-400">{{ __('Die Auswahl ist durch die gewählte Heftungsart eingeschränkt.') }}</p>
                @endif
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-2 text-sm">
                @if ($combinations->isEmpty())
                    <p class="px-2 py-3 text-gray-400">{{ __('Keine freigegebene Format-Kombination verfügbar.') }}</p>
                @else
                    @foreach ($combinations as $c)
                        @php
                            $inputArea = number_format($c->inputFormat->width_mm * $c->inputFormat->height_mm / 1000000, 3);
                            $outputArea = number_format($c->outputFormat->width_mm * $c->outputFormat->height_mm / 1000000, 3);
                            $searchText = mb_strtolower($c->inputFormat->name.' '.$c->outputFormat->name);
                        @endphp
                        <label
                            x-show="!search || {{ \Illuminate\Support\Js::from($searchText) }}.includes(search.toLowerCase())"
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-transparent p-2 hover:bg-gray-50"
                            :class="selectedId === {{ $c->id }} ? 'bg-indigo-50 border-indigo-100' : ''"
                        >
                            <input type="radio" name="format_combination_choice" value="{{ $c->id }}" x-model.number="selectedId" class="shrink-0">
                            <span class="min-w-0 flex-1">
                                <span class="text-gray-700">{{ $c->inputFormat->name }}</span>
                                <span class="text-xs text-gray-400">{{ $c->inputFormat->width_mm }}×{{ $c->inputFormat->height_mm }} mm · {{ $inputArea }} m²</span>
                                <span class="text-gray-400">&rArr;</span>
                                <span class="text-gray-700">{{ $c->outputFormat->name }}</span>
                                <span class="text-xs text-gray-400">{{ $c->outputFormat->width_mm }}×{{ $c->outputFormat->height_mm }} mm · {{ $outputArea }} m²</span>
                            </span>
                            @if ($c->fold_count !== null)
                                <span class="shrink-0 text-xs text-gray-400" title="{{ __('Falzungen') }}">{{ $c->fold_count }}×</span>
                            @endif
                            @if ($c->remark)
                                <span class="shrink-0 text-gray-400" title="{{ $c->remark }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </span>
                            @endif
                        </label>
                    @endforeach
                @endif
            </div>

            <div class="shrink-0 border-t border-gray-100 p-3 flex justify-end gap-2">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'format-picker-{{ $project->id }}' }))"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Abbrechen') }}
                </button>
                <button type="button" @click="save()" :disabled="!selectedId" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Speichern') }}
                </button>
            </div>
        </div>
    </x-modal>
@endif
