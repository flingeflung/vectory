{{--
    Reiter 2 "Prozentuale Aufteilung" (Ralf, 2026-09-27, siehe Roadmap-
    Backlog): nur am Hauptprojekt mit mindestens einem Unterprojekt
    sichtbar. Legt fest, wie Stunden, die künftig am Hauptprojekt gebucht
    werden, auf Hauptprojekt + Unterprojekte verteilt werden (siehe
    ProjectHourController::splitAndBook()) - Änderungen wirken erst auf
    künftige Buchungen, bereits gebuchte Stunden bleiben unangetastet.

    Equalizer-artige Zug-Regler (Ralf, 2026-09-27, Vorbild: Grafik-EQ einer
    Stereoanlage-App): Ziehen an einem Regler verteilt die Differenz
    PROPORTIONAL zum aktuellen Anteil auf alle anderen - die Summe bleibt
    dadurch mathematisch garantiert immer 100 (kein iteratives Clamping
    nötig: Ziel-Summe der anderen ist 100-neuerWert, alle anderen werden
    exakt auf diese Ziel-Summe hochskaliert). Zahlenfeld darunter bedient
    dieselbe Funktion - Ziehen und Tippen sind gleichwertig.
--}}
@include('projekte.partials.project-time-tracking-header')
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
    x-data="{
        values: {{ Illuminate\Support\Js::from($shares->mapWithKeys(fn ($v, $k) => [(string) $k => round((float) $v, 2)])) }},
        dragging: null,
        dragRect: null,
        dirty: false,

        sum() {
            return Object.values(this.values).reduce((a, b) => a + b, 0);
        },

        // Verteilt die Differenz proportional zum aktuellen Anteil auf alle
        // anderen Regler - Ziel-Summe der anderen ist immer 100 minus dem
        // neuen Wert, also landet die Gesamtsumme rechnerisch zwingend bei
        // 100 (keine Sonderfälle für 'am Anschlag', da proportionale
        // Skalierung auf eine Summe <= 100 keinen Einzelwert über 100 heben
        // kann). Rundungsrest (2 Nachkommastellen) schlägt sich der
        // gezogene Regler selbst zu, analog zur Rundungsregel beim Buchen.
        setValue(id, raw) {
            const newValue = Math.max(0, Math.min(100, isNaN(raw) ? 0 : raw));
            const key = String(id);
            const others = Object.keys(this.values).filter((k) => k !== key);
            const otherSum = others.reduce((s, k) => s + this.values[k], 0);
            const targetOtherSum = 100 - newValue;

            if (otherSum > 0.001) {
                const factor = targetOtherSum / otherSum;
                others.forEach((k) => { this.values[k] = Math.round(this.values[k] * factor * 100) / 100; });
            } else {
                const even = Math.round((targetOtherSum / (others.length || 1)) * 100) / 100;
                others.forEach((k) => { this.values[k] = even; });
            }

            const roundedOtherSum = others.reduce((s, k) => s + this.values[k], 0);
            this.values[key] = Math.round((100 - roundedOtherSum) * 100) / 100;
            this.dirty = true;
        },

        startDrag(id, event) {
            this.dragging = id;
            this.dragRect = event.currentTarget.getBoundingClientRect();
            this.dragFromY(id, event.clientY);
        },
        dragFromY(id, clientY) {
            const ratio = 1 - Math.max(0, Math.min(1, (clientY - this.dragRect.top) / this.dragRect.height));
            this.setValue(id, ratio * 100);
        },
        onWindowPointerMove(event) {
            if (this.dragging === null) { return; }
            this.dragFromY(this.dragging, event.clientY);
        },
        stopDrag() { this.dragging = null; },

        // Gleichmäßig auf Hundertstel-Prozent verteilen, Rest deterministisch den
        // ersten Teilnehmern zuschlagen - dieselbe Regel wie evenSplit() serverseitig
        // (ProjectPercentageSplitController), nur hier auf eine gewählte Teilmenge
        // angewendet statt immer auf alle.
        levelEvenly(keys, totalHundredths) {
            const n = keys.length;
            if (n === 0) { return; }
            const base = Math.floor(totalHundredths / n);
            const remainder = totalHundredths - base * n;
            keys.forEach((k, i) => { this.values[k] = (base + (i < remainder ? 1 : 0)) / 100; });
            this.dirty = true;
        },
        levelAll() {
            this.levelEvenly(Object.keys(this.values), 10000);
        },
        levelOthers(hauptprojektId) {
            const hpKey = String(hauptprojektId);
            const others = Object.keys(this.values).filter((k) => k !== hpKey);
            const remaining = 10000 - Math.round(this.values[hpKey] * 100);
            this.levelEvenly(others, remaining);
        },
    }"
    @pointermove.window="onWindowPointerMove($event)"
    @pointerup.window="stopDrag()"
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

        <div class="mb-3 flex gap-2">
            <button
                type="button"
                onclick="Alpine.$data(this.closest('form')).levelAll()"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                title="{{ __('Verteilt 100 % gleichmäßig auf Hauptprojekt und alle Unterprojekte.') }}"
            >
                {{ __('Alle nivellieren') }}
            </button>
            <button
                type="button"
                onclick="Alpine.$data(this.closest('form')).levelOthers({{ $project->id }})"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                title="{{ __('Lässt den Anteil des Hauptprojekts unverändert und verteilt den Rest gleichmäßig auf die Unterprojekte.') }}"
            >
                {{ __('Nur Unterprojekte nivellieren') }}
            </button>
        </div>

        {{-- flex-wrap statt einer einzelnen, horizontal scrollenden Zeile (Ralf, 2026-09-27:
             "was, wenn wir 25 Unterprojekte haben? Das geht nicht nebeneinander") - bricht
             bei Bedarf in mehrere Zeilen um, das Overlay scrollt ohnehin schon vertikal. --}}
        <div class="flex flex-wrap items-end gap-x-4 gap-y-5 pb-1 pt-6">
            @foreach ($participants as $p)
                @php($isHauptprojekt = $p->id === $project->id)
                <div class="flex w-14 shrink-0 flex-col items-center gap-1">
                    {{-- Hauptprojekt ist immer der erste Teilnehmer (unabhängig von seiner PN),
                         die Unterprojekte danach nach PN sortiert (Ralf-Nachfrage, 2026-09-27) -
                         eigenes Badge statt nur des kleinen "(H)"-Suffix, damit es auf einen
                         Blick auffällt, auch wenn die Spalte nur 56px breit ist. --}}
                    <span
                        class="h-4 rounded-full px-1.5 text-[10px] font-semibold leading-4 {{ $isHauptprojekt ? 'bg-indigo-100 text-indigo-700' : '' }}"
                        title="{{ $isHauptprojekt ? __('Hauptprojekt') : '' }}"
                    >
                        {{ $isHauptprojekt ? __('HP') : '' }}
                    </span>
                    <span class="font-mono text-xs font-semibold tabular-nums text-gray-700" x-text="values[{{ $p->id }}].toFixed(2).replace('.', ',') + ' %'"></span>

                    <div
                        class="relative h-36 w-7 shrink-0 touch-none select-none rounded-full {{ $isHauptprojekt ? 'bg-indigo-100' : 'bg-gray-200' }}"
                        @pointerdown="startDrag({{ $p->id }}, $event)"
                        style="cursor: grab;"
                    >
                        <div class="absolute inset-x-0 bottom-0 rounded-full {{ $isHauptprojekt ? 'bg-indigo-700' : 'bg-indigo-500' }}" :style="`height: ${values[{{ $p->id }}]}%`"></div>
                        <div
                            class="absolute left-1/2 h-4 w-4 -translate-x-1/2 rounded-full border-2 bg-white shadow {{ $isHauptprojekt ? 'border-indigo-700' : 'border-indigo-600' }}"
                            :style="`bottom: calc(${values[{{ $p->id }}]}% - 8px)`"
                        ></div>
                    </div>

                    <input
                        type="number"
                        name="shares[{{ $p->id }}]"
                        :value="values[{{ $p->id }}].toFixed(2)"
                        @change="setValue({{ $p->id }}, parseFloat($event.target.value))"
                        step="0.01"
                        min="0"
                        max="100"
                        required
                        class="w-14 rounded border-gray-300 px-1 py-0.5 text-center text-xs tabular-nums"
                    >

                    <span
                        class="max-w-14 truncate text-center text-[11px] {{ $isHauptprojekt ? 'font-semibold text-indigo-700' : 'text-gray-500' }}"
                        title="{{ $p->source_pn }}{{ $p->title ? ' – '.$p->title : '' }}"
                    >
                        {{ $p->source_pn }}
                    </span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-2 text-xs font-medium" :class="Math.abs(sum() - 100) < 0.005 ? 'text-gray-400' : 'text-red-600'">
            <span>{{ __('Summe') }}</span>
            <span x-text="sum().toFixed(2).replace('.', ',') + ' %'"></span>
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
            class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover"
        >
            {{ __('Speichern') }}
        </button>
    </div>
</form>
