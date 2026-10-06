{{--
    Zeitraum-Diagramm (Ralf, 2026-10-06): die Arbeitsschritte "In Bearbeitung" als aneinanderhängende Balken über den Arbeitstagen von
    Projektstart bis -ende. Die Breite eines Balkens entspricht seiner Dauer in AT. Die Balkenenden lassen sich ziehen; darunter stehen je
    Schritt Enddatum und Dauer als Felder. Alle drei Eingabewege (Ziehen, Datum, AT) wirken aufeinander (Wechselwirkung):
    - Ziehen: verschiebt die Grenze zum nächsten Schritt -> Datum und AT beider Schritte ändern sich.
    - Datum eines Schritts ändern: dieselbe Grenzverschiebung (der letzte Schritt endet immer am Projektende).
    - AT eines Schritts ändern: der Nachbar gleicht aus (beim letzten Schritt der Vorgänger).
    Die Gesamtbreite bleibt der Projektzeitraum, jeder Schritt hat mindestens 1 AT. Vorerst nur Ansicht/Entwurf: nichts wird gespeichert.
--}}
<div
    x-data="{
        workdays: @js($chart['workdays']),
        steps: @js($chart['steps']),
        original: null,
        palette: ['#93c5fd', '#6ee7b7', '#fcd34d', '#f9a8d4', '#c4b5fd', '#fdba74', '#5eead4', '#fca5a5'],
        init() {
            this.normalize();
            this.original = this.steps.map((step) => step.days);
        },
        get total() { return this.workdays.length; },
        get changed() { return this.original !== null && this.steps.some((step, i) => step.days !== this.original[i]); },
        // Die Summe der Dauern muss genau der Zeitraum sein (der Server liefert das schon so; hier nur als Absicherung)
        normalize() {
            const sum = this.steps.reduce((a, step) => a + step.days, 0);
            if (sum === this.total || this.steps.length === 0) return;
            this.steps.forEach((step) => { step.days = Math.max(1, Math.round(step.days * this.total / sum)); });
            let diff = this.total - this.steps.reduce((a, step) => a + step.days, 0);
            const order = [...this.steps.keys()].sort((a, b) => this.steps[b].days - this.steps[a].days);
            while (diff !== 0 && order.length) {
                const step = this.steps[order[0]];
                if (diff < 0 && step.days <= 1) break;
                step.days += diff > 0 ? 1 : -1;
                diff += diff > 0 ? -1 : 1;
            }
        },
        cum(i) { let c = 0; for (let k = 0; k <= i; k++) c += this.steps[k].days; return c; },
        endIso(i) { return this.workdays[Math.min(this.total, this.cum(i)) - 1]; },
        dateDe(iso) { return iso ? iso.split('-').reverse().join('.') : ''; },
        startPct(i) { return (this.cum(i) - this.steps[i].days) / this.total * 100; },
        widthPct(i) { return this.steps[i].days / this.total * 100; },
        tip(i) {
            const start = this.workdays[this.cum(i) - this.steps[i].days];
            return this.steps[i].title + ' · ' + this.steps[i].days + ' AT · ' + this.dateDe(start) + ' – ' + this.dateDe(this.endIso(i));
        },
        // erstes Wort; ist es kurz (bis 3 Zeichen), das zweite dazu; bei weiteren Wörtern ein Auslassungszeichen
        shortLabel(title) {
            const words = String(title).trim().split(/\s+/);
            let label = words[0];
            let used = 1;
            if (label.length <= 3 && words.length > 1) { label += ' ' + words[1]; used = 2; }
            return words.length > used ? label + ' …' : label;
        },
        // Grenze hinter Schritt i auf Arbeitstag-Position unit schieben (jeder der beiden Schritte behält mindestens 1 AT)
        moveBoundary(i, unit) {
            if (i < 0 || i >= this.steps.length - 1) return;
            const left = this.cum(i) - this.steps[i].days;
            const right = this.cum(i + 1);
            const clamped = Math.min(Math.max(unit, left + 1), right - 1);
            this.steps[i].days = clamped - left;
            this.steps[i + 1].days = right - clamped;
        },
        startDrag(i, event) {
            const move = (e) => {
                const rect = this.$refs.track.getBoundingClientRect();
                this.moveBoundary(i, Math.round((e.clientX - rect.left) / rect.width * this.total));
            };
            const up = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        },
        setEndDate(i, iso, el) {
            if (i < this.steps.length - 1 && iso) {
                let idx = this.workdays.findIndex((day) => day >= iso);
                if (idx < 0) idx = this.total - 1;
                this.moveBoundary(i, idx + 1);
            }
            el.value = this.endIso(i);
        },
        setDays(i, value, el) {
            const n = Math.max(1, parseInt(value, 10) || 1);
            const j = i < this.steps.length - 1 ? i + 1 : i - 1;
            if (j >= 0) {
                const delta = Math.min(n - this.steps[i].days, this.steps[j].days - 1);
                this.steps[i].days += delta;
                this.steps[j].days -= delta;
            }
            el.value = this.steps[i].days;
        },
        reset() { this.steps.forEach((step, i) => { step.days = this.original[i]; }); },
    }"
    class="rounded-lg border border-gray-200 bg-white p-3 text-xs"
>
    @if ($chart['compressed'])
        <p class="mb-2 text-gray-500">{{ __('Der Zeitbedarf laut Workflow (:sum AT) passt nicht zum Projektzeitraum (:available AT). Die Balken sind auf den Zeitraum umgerechnet; die Dauer laut Workflow steht im Tooltip der Felder.', ['sum' => $chart['sum'], 'available' => $chart['available']]) }}</p>
    @endif

    <div class="px-1">
        <div x-ref="track" class="relative h-9 w-full select-none overflow-visible rounded">
            <template x-for="(step, i) in steps" :key="step.id">
                <div
                    class="absolute top-0 h-full border-r border-white"
                    :class="{ 'rounded-l': i === 0, 'rounded-r': i === steps.length - 1 }"
                    :style="{ left: startPct(i) + '%', width: widthPct(i) + '%', backgroundColor: palette[i % palette.length] }"
                    :title="tip(i)"
                ></div>
            </template>
            <template x-for="(step, i) in steps.slice(0, -1)" :key="'handle-' + step.id">
                <div
                    class="absolute top-0 z-10 flex h-full w-3 -translate-x-1/2 cursor-col-resize items-center justify-center"
                    :style="{ left: (cum(i) / total * 100) + '%' }"
                    @pointerdown.prevent="startDrag(i, $event)"
                    title="{{ __('Ziehen, um die Grenze zum nächsten Schritt zu verschieben') }}"
                >
                    <div class="h-5 w-1 rounded bg-gray-700/70"></div>
                </div>
            </template>
        </div>
        <div class="mt-1 flex justify-between text-gray-500">
            <span x-text="dateDe(workdays[0])"></span>
            <span x-text="dateDe(workdays[workdays.length - 1])"></span>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap gap-2">
        <template x-for="(step, i) in steps" :key="'field-' + step.id">
            <div class="flex items-center gap-1 rounded-md border border-gray-200 px-1.5 py-1">
                <span class="inline-block h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: palette[i % palette.length] }"></span>
                <span class="max-w-[10rem] truncate font-medium text-gray-700" x-text="shortLabel(step.title)" :title="step.title"></span>
                <input
                    type="date"
                    :value="endIso(i)"
                    :disabled="i === steps.length - 1"
                    @change="setEndDate(i, $event.target.value, $event.target)"
                    class="w-[8.5rem] rounded border-gray-300 py-0.5 text-xs disabled:bg-gray-50 disabled:text-gray-500"
                    title="{{ __('Berechnetes Ende des Schritts') }}"
                >
                <input
                    type="number"
                    min="1"
                    :value="step.days"
                    @change="setDays(i, $event.target.value, $event.target)"
                    class="w-14 rounded border-gray-300 py-0.5 text-right text-xs"
                    :title="'{{ __('Dauer in Arbeitstagen (AT)') }}' + (step.fixed ? ' – {{ __('keine Dauer im Workflow eingetragen, zählt 1 Tag') }}' : ' – {{ __('laut Workflow') }}: ' + step.workflow_days)"
                >
                <span class="text-gray-500">{{ __('AT') }}</span>
            </div>
        </template>
    </div>

    <div class="mt-2 flex items-center gap-3 text-gray-400">
        <span>{{ __('Vorerst nur Ansicht: Verschiebungen werden nicht gespeichert.') }}</span>
        <button type="button" x-show="changed" x-cloak @click="reset()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Zurücksetzen') }}</button>
    </div>
</div>
