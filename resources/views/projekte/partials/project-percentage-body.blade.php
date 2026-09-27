{{--
    Reiter 2 "Prozentuale Aufteilung" (Ralf, 2026-09-27, siehe Roadmap-
    Backlog): nur am Hauptprojekt mit mindestens einem Unterprojekt
    sichtbar. Legt fest, wie Stunden, die künftig am Hauptprojekt gebucht
    werden, auf Hauptprojekt + Unterprojekte verteilt werden (siehe
    ProjectHourController::splitAndBook()) - Änderungen wirken erst auf
    künftige Buchungen, bereits gebuchte Stunden bleiben unangetastet.
--}}
<div class="flex gap-1 border-b border-gray-200 px-4 pt-2">
    <button
        type="button"
        onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'jobs')"
        class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
    >
        {{ __('Verknüpfte Jobs') }}
    </button>
    <span class="-mb-px border-b-2 border-indigo-500 px-3 py-1.5 text-xs font-medium text-gray-900">{{ __('Prozentuale Aufteilung') }}</span>
    <button
        type="button"
        onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'buchungen')"
        class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
    >
        {{ __('Buchungen') }}
    </button>
</div>

<form
    id="project-percentage-form"
    x-data="{ dirty: false, sum: {{ number_format((float) $shares->sum(), 2, '.', '') }} }"
    @input="dirty = window.formIsDirty($el); sum = Array.from($el.querySelectorAll('input[type=number]')).reduce((s, i) => s + (parseFloat(i.value) || 0), 0)"
    method="POST"
    action="{{ route('projekte.aufteilung.update', $project) }}"
    class="flex max-h-[75vh] flex-col"
>
    @csrf
    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
        <p class="mb-3 text-xs text-gray-500">
            {{ __('Wenn jemand am Hauptprojekt :pn Stunden bucht, werden sie sofort nach diesen Prozenten auf die Unterprojekte verteilt - dort entstehen dann die eigentlichen Buchungen. Eine spätere Änderung der Prozente wirkt nur auf künftige Buchungen.', ['pn' => $project->source_pn]) }}
            @unless ($configured)
                {{ __('Noch nichts gespeichert - die Werte unten sind gleichmäßig verteilt.') }}
            @endunless
        </p>

        <div class="space-y-1">
            @foreach ($participants as $p)
                <div class="flex items-center justify-between gap-3 rounded px-2 py-1.5 hover:bg-gray-50">
                    <span class="min-w-0 flex-1 truncate">
                        {{ $p->source_pn }}{{ $p->id === $project->id ? ' ('.__('Hauptprojekt').')' : '' }}
                        @if ($p->title)
                            – {{ $p->title }}
                        @endif
                    </span>
                    <div class="flex shrink-0 items-center gap-1">
                        <input
                            type="number"
                            name="shares[{{ $p->id }}]"
                            value="{{ number_format($shares[$p->id] ?? 0, 2, '.', '') }}"
                            step="0.01"
                            min="0"
                            max="100"
                            required
                            class="w-20 rounded border-gray-300 text-right text-sm tabular-nums"
                        >
                        <span class="text-gray-400">%</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2 text-xs font-medium" :class="Math.abs(sum - 100) < 0.005 ? 'text-gray-500' : 'text-red-600'">
            <span>{{ __('Summe') }}</span>
            <span x-text="sum.toFixed(2).replace('.', ',') + ' %'"></span>
        </div>
    </div>
    <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 px-4 py-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-time-tracking' }))"
            class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Abbrechen') }}
        </button>
        <button
            type="submit"
            x-show="dirty"
            x-cloak
            :disabled="Math.abs(sum - 100) > 0.005"
            :class="Math.abs(sum - 100) > 0.005 ? 'cursor-not-allowed opacity-40' : ''"
            class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover"
        >
            {{ __('Speichern') }}
        </button>
    </div>
</form>
