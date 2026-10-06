{{--
    Zeitraum-Diagramm (Ralf, 2026-10-06): die Arbeitsschritte "In Bearbeitung" als aneinanderhängende Balken über dem Kalender von
    Projektstart bis -ende. Die Zeitachse zeigt alle Kalendertage; im Hintergrund liegt ein feines Raster (Sa/So, Feiertage, Monatswechsel,
    Jahreswechsel, Heute). Ein Balken reicht bis zum Beginn des ersten Arbeitstags des nächsten Schritts (Wochenenden zählen zum vorigen
    Schritt). Die Balkenenden lassen sich ziehen; darunter stehen je Schritt Enddatum und Dauer als Felder. Alle drei Eingabewege (Ziehen,
    Datum, AT) wirken aufeinander (Wechselwirkung):
    - Ziehen: verschiebt die Grenze zum nächsten Schritt -> Datum und AT beider Schritte ändern sich.
    - Datum eines Schritts ändern: dieselbe Grenzverschiebung (der letzte Schritt endet immer am Projektende).
    - AT eines Schritts ändern: der Nachbar gleicht aus (beim letzten Schritt der Vorgänger).
    Die Summe der Dauern bleibt der Projektzeitraum in AT, jeder Schritt hat mindestens 1 AT. Vorerst nur Ansicht/Entwurf: nichts wird gespeichert.
--}}
<div
    x-data="{
        workdays: @js($chart['workdays']),
        calendar: @js($chart['calendar']),
        holidays: @js($chart['holidays']),
        steps: @js($chart['steps']),
        groups: @js($chart['groups']),
        original: null,
        wdCal: [],
        grid: { weekends: [], holidays: [], months: [], years: [], today: null },
        palette: ['#93c5fd', '#6ee7b7', '#fcd34d', '#f9a8d4', '#c4b5fd', '#fdba74', '#5eead4', '#fca5a5'],
        init() {
            this.normalize();
            this.original = this.steps.map((step) => step.days);
            const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
            const now = new Date();
            const todayIso = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const index = {};
            this.calendar.forEach((iso, k) => {
                index[iso] = k;
                const [y, m, d] = iso.split('-').map(Number);
                const dow = new Date(Date.UTC(y, m - 1, d)).getUTCDay();
                if (dow === 0 || dow === 6) this.grid.weekends.push(k);
                if (this.holidays[iso]) this.grid.holidays.push({ k: k, name: this.holidays[iso] });
                if (d === 1 && k > 0) {
                    if (m === 1) this.grid.years.push({ k: k, label: String(y) });
                    else this.grid.months.push({ k: k, label: monthNames[m - 1] });
                }
            });
            this.wdCal = this.workdays.map((iso) => index[iso]);
            if (index[todayIso] !== undefined) this.grid.today = index[todayIso];
        },
        get total() { return this.workdays.length; },
        get span() { return this.calendar.length; },
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
        // Kalenderposition (0..span) des rechten Rands von Schritt i
        edge(i) { return i >= this.steps.length - 1 ? this.span : this.wdCal[this.cum(i)]; },
        startPct(i) { return (i === 0 ? 0 : this.edge(i - 1)) / this.span * 100; },
        widthPct(i) { return (this.edge(i) - (i === 0 ? 0 : this.edge(i - 1))) / this.span * 100; },
        pct(k) { return k / this.span * 100; },
        // Einsatzplan: Balken einer Funktionsgruppe von Schritt from bis Schritt to; die Stunden kommen aus Planstunden (Variable planned der Planungsseite)
        groupLeft(g) { return this.startPct(Math.min(g.from, this.steps.length - 1)); },
        groupWidth(g) { const to = Math.min(g.to, this.steps.length - 1); return this.pct(this.edge(to)) - this.groupLeft(g); },
        groupHours(g) { return Number(this.planned[String(g.id)] || 0); },
        hoursLabel(h) { return h > 0 ? h.toLocaleString('de-DE', { maximumFractionDigits: 2 }) + ' h' : ''; },
        groupTip(g) {
            const from = Math.min(g.from, this.steps.length - 1);
            const to = Math.min(g.to, this.steps.length - 1);
            const start = this.workdays[this.cum(from) - this.steps[from].days];
            const hours = this.groupHours(g);
            return g.name + ' · ' + (hours > 0 ? this.hoursLabel(hours) + ' · ' : '') + this.dateDe(start) + ' – ' + this.dateDe(this.endIso(to));
        },
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
                const c = Math.round((e.clientX - rect.left) / rect.width * this.span);
                this.moveBoundary(i, this.wdCal.filter((k) => k < c).length);
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
        <div x-ref="track" style="height: 60px" class="relative select-none overflow-visible rounded" :class="groups.length ? 'ml-[9.5rem]' : ''">
            <span x-show="groups.length" class="absolute right-full top-0 mr-2 text-[10px] font-medium text-gray-400">{{ __('Workflow') }}</span>
            {{-- Raster im Hintergrund --}}
            <template x-for="k in grid.weekends" :key="'we-' + k">
                <div class="pointer-events-none absolute top-0 h-full" :style="{ left: pct(k) + '%', width: pct(1) + '%', backgroundColor: '#fdefc6' }"></div>
            </template>
            <template x-for="h in grid.holidays" :key="'ho-' + h.k">
                <div class="absolute top-0 h-full bg-rose-300" :style="{ left: pct(h.k) + '%', width: 'max(1px, ' + pct(1) + '%)', opacity: 0.6 }" :title="h.name + ' (' + dateDe(calendar[h.k]) + ')'"></div>
            </template>
            <template x-for="m in grid.months" :key="'mo-' + m.k">
                <div class="pointer-events-none absolute top-0 h-full w-px bg-gray-300" :style="{ left: pct(m.k) + '%' }"></div>
            </template>
            <template x-for="y in grid.years" :key="'ye-' + y.k">
                <div class="pointer-events-none absolute top-0 h-full w-0.5 -translate-x-px bg-gray-500" :style="{ left: pct(y.k) + '%' }"></div>
            </template>

            <template x-for="(step, i) in steps" :key="step.id">
                <div
                    class="absolute border-r border-white"
                    :class="{ 'rounded-l': i === 0, 'rounded-r': i === steps.length - 1 }"
                    :style="{ top: '16px', bottom: '16px', left: startPct(i) + '%', width: widthPct(i) + '%', backgroundColor: palette[i % palette.length], opacity: 0.85 }"
                    :title="tip(i)"
                ></div>
            </template>

            <div
                x-show="grid.today !== null"
                class="pointer-events-none absolute top-0 z-[5] h-full w-0.5 -translate-x-px bg-blue-600"
                :style="{ left: pct((grid.today ?? 0) + 0.5) + '%' }"
                title="{{ __('Heute') }}"
            ></div>

            <template x-for="(step, i) in steps.slice(0, -1)" :key="'handle-' + step.id">
                <div
                    class="absolute top-0 z-10 flex h-full w-3 -translate-x-1/2 cursor-col-resize items-center justify-center"
                    :style="{ left: pct(edge(i)) + '%' }"
                    @pointerdown.prevent="startDrag(i, $event)"
                    :title="dateDe(endIso(i))"
                >
                    <div class="h-5 w-1 rounded bg-gray-700/70"></div>
                </div>
            </template>
        </div>
        <div class="relative mt-1 h-4 text-gray-500" :class="groups.length ? 'ml-[9.5rem]' : ''">
            <span class="absolute left-0" x-text="dateDe(calendar[0])"></span>
            <template x-for="m in grid.months.concat(grid.years)" :key="'lab-' + m.k">
                <span
                    x-show="pct(m.k) > 12 && pct(m.k) < 88"
                    class="absolute -translate-x-1/2 text-[10px] text-gray-400"
                    :class="m.label.length === 4 ? 'font-semibold text-gray-600' : ''"
                    :style="{ left: pct(m.k) + '%' }"
                    x-text="m.label"
                ></span>
            </template>
            <span class="absolute right-0" x-text="dateDe(calendar[calendar.length - 1])"></span>
        </div>

        <div x-show="groups.length" class="mt-2 space-y-1">
            <div class="text-[10px] font-medium text-gray-400">{{ __('Einsatzplan') }}</div>
            <template x-for="g in groups" :key="'group-' + g.id">
                <div class="flex items-center gap-2">
                    <span class="w-36 shrink-0 truncate text-gray-600" x-text="g.name" :title="g.name"></span>
                    <div class="relative h-5 flex-1 rounded bg-gray-100">
                        <div
                            class="absolute top-0 flex h-full items-center justify-center overflow-hidden whitespace-nowrap rounded bg-sky-500 text-[10px] font-medium text-white"
                            :style="{ left: groupLeft(g) + '%', width: groupWidth(g) + '%' }"
                            :title="groupTip(g)"
                            x-text="hoursLabel(groupHours(g))"
                        ></div>
                    </div>
                </div>
            </template>
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
                    class="w-[5.5rem] rounded border-gray-300 px-1 py-0.5 text-xs [&::-webkit-calendar-picker-indicator]:m-0 [&::-webkit-calendar-picker-indicator]:p-0 disabled:bg-gray-50 disabled:text-gray-500"
                    title="{{ __('Berechnetes Ende des Schritts') }}"
                >
                <input
                    type="number"
                    min="1"
                    :value="step.days"
                    @change="setDays(i, $event.target.value, $event.target)"
                    class="w-10 rounded border-gray-300 px-1 py-0.5 text-right text-xs"
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
