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
        period: @js($chart['period']),
        milestones: @js($chart['milestones']),
        hasOverrides: @js($hasOverrides ?? false),
        canEditPeriod: @js($canEditPeriod ?? false),
        applying: false,
        hover: null,
        preview: null,
        lead: 0,
        previewOff: 0,
        backupDays: null,
        original: null,
        wdCal: [],
        grid: { weekends: [], holidays: [], months: [], years: [], today: null },
        palette: ['#93c5fd', '#6ee7b7', '#fcd34d', '#f9a8d4', '#c4b5fd', '#fdba74', '#5eead4', '#fca5a5'],
        init() {
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
        // Summe der Dauern; kleiner als total = Puffer bis zum Projektende, total ist bei zu knappem Zeitraum über das Projektende hinaus verlängert
        get used() { return this.steps.reduce((a, step) => a + step.days, 0); },
        get endIdx() { return this.calendar.indexOf(this.period.project_end); },
        get todayIso() { const now = new Date(); return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'); },
        get startInPast() { return this.period.new_start !== null && this.period.new_start < this.todayIso; },
        // Vorlauf: Arbeitstage am Anfang, an denen noch kein Schritt läuft (linken Rand nach rechts gezogen); dazu die Vorschau-Verschiebung
        get off() { return this.lead + this.previewOff; },
        get liveAvail() { return this.period.available - this.lead; },
        // Differenz Zeitraum - Bedarf laut Diagramm: positiv = Puffer, negativ = es fehlen Tage
        get liveDiff() { return this.liveAvail - this.used; },
        get liveMode() { return this.liveDiff === 0 ? 'match' : (this.liveDiff > 0 ? 'buffer' : 'overflow'); },
        get changed() { return this.original !== null && (this.lead !== 0 || this.steps.some((step, i) => step.days !== this.original[i])); },
        cum(i) { let c = 0; for (let k = 0; k <= i; k++) c += this.steps[k].days; return c; },
        endIso(i) { return this.workdays[Math.min(this.total, this.off + this.cum(i)) - 1]; },
        dateDe(iso) { return iso ? iso.split('-').reverse().join('.') : ''; },
        // Datum mit Wochentag in Kurzform für Tooltips, z. B. Mo, 05.10.2026
        dateWd(iso) {
            if (! iso) return '';
            const [y, m, d] = iso.split('-').map(Number);
            return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][new Date(Date.UTC(y, m - 1, d)).getUTCDay()] + ', ' + this.dateDe(iso);
        },
        // Kalenderposition (0..span) des rechten Rands von Schritt i
        // Kalenderposition des k-ten Arbeitstags; off verschiebt alles beim Vorschau-Start (Puffer vorn statt hinten)
        wd(k) { return this.wdCal[k + this.off]; },
        edge(i) { return i >= this.steps.length - 1 ? (this.used + this.off >= this.total ? this.span : this.wd(this.used - 1) + 1) : this.wd(this.cum(i)); },
        first() { return this.off ? this.wd(0) : 0; },
        startPct(i) { return (i === 0 ? this.first() : this.edge(i - 1)) / this.span * 100; },
        widthPct(i) { return (this.edge(i) - (i === 0 ? this.first() : this.edge(i - 1))) / this.span * 100; },
        get newEndIdx() { return this.period.new_end ? this.calendar.indexOf(this.period.new_end) : -1; },
        // Vorschau beim Überfahren der Knöpfe: zeigt, wie das Diagramm nach dem Klick aussähe
        showPreview(kind) {
            if (this.period.mode === 'match' || this.preview !== null) return;
            this.preview = kind;
            if (kind === 'durations') {
                this.backupDays = this.steps.map((step) => step.days);
                this.steps.forEach((step) => { step.days = step.scaled_days; });
            }
            if (kind === 'start' && this.period.mode === 'buffer') this.previewOff = this.total - this.used;
        },
        hidePreview() {
            if (this.backupDays) { this.steps.forEach((step, i) => { step.days = this.backupDays[i]; }); this.backupDays = null; }
            this.previewOff = 0;
            this.preview = null;
        },
        pct(k) { return k / this.span * 100; },
        // Meilenstein = Schritt mit eingetragenem Termin; außerhalb des Diagramms am Rand
        msPct(m) {
            let idx = this.calendar.indexOf(m.date);
            if (idx < 0) idx = m.date < this.calendar[0] ? 0 : this.span - 1;
            return (idx + 0.5) / this.span * 100;
        },
        // Zu spät: der berechnete Schritt endet erst nach seinem Meilenstein
        msLate(m) {
            const i = this.steps.findIndex((step) => step.id === m.step_id);
            return i >= 0 && this.endIso(i) > m.date;
        },
        msTip(m) {
            const i = this.steps.findIndex((step) => step.id === m.step_id);
            const base = m.title + ' · ' + this.dateWd(m.date);
            return this.msLate(m) ? base + ' – ' + @js(__('Der Schritt endet laut Plan erst am :date.')).replace(':date', this.dateWd(this.endIso(i))) : base;
        },
        // Einsatzplan: Balken einer Funktionsgruppe von Schritt from bis Schritt to; die Stunden kommen aus Planstunden (Variable planned der Planungsseite)
        groupLeft(g) { return this.startPct(Math.min(g.from, this.steps.length - 1)); },
        groupWidth(g) { const to = Math.min(g.to, this.steps.length - 1); return this.pct(this.edge(to)) - this.groupLeft(g); },
        groupHours(g) { return Number(this.planned[String(g.id)] || 0); },
        hoursLabel(h) { return h > 0 ? h.toLocaleString('de-DE', { maximumFractionDigits: 2 }) + ' h' : ''; },
        groupTip(g) {
            const from = Math.min(g.from, this.steps.length - 1);
            const to = Math.min(g.to, this.steps.length - 1);
            const start = this.workdays[this.off + this.cum(from) - this.steps[from].days];
            const hours = this.groupHours(g);
            return g.name + ' · ' + (hours > 0 ? this.hoursLabel(hours) + ' · ' : '') + this.dateWd(start) + ' – ' + this.dateWd(this.endIso(to));
        },
        tip(i) {
            const start = this.workdays[this.off + this.cum(i) - this.steps[i].days];
            return this.steps[i].title + ' · ' + this.steps[i].days + ' AT · ' + this.dateWd(start) + ' – ' + this.dateWd(this.endIso(i));
        },
        // erstes Wort; ist es kurz (bis 3 Zeichen), das zweite dazu; bei weiteren Wörtern ein Auslassungszeichen
        shortLabel(title) {
            const words = String(title).trim().split(/\s+/);
            let label = words[0];
            let used = 1;
            if (label.length <= 3 && words.length > 1) { label += ' ' + words[1]; used = 2; }
            return words.length > used ? label + ' …' : label;
        },
        // Grenze hinter Schritt i auf Arbeitstag-Position unit schieben (relativ zum Start des ersten Schritts; jeder der beiden Schritte behält mindestens 1 AT)
        moveBoundary(i, unit) {
            if (i < 0 || i >= this.steps.length - 1) return;
            const left = this.cum(i) - this.steps[i].days;
            const right = this.cum(i + 1);
            const clamped = Math.min(Math.max(unit, left + 1), right - 1);
            this.steps[i].days = clamped - left;
            this.steps[i + 1].days = right - clamped;
        },
        // Ende des letzten Schritts verschieben: ändert nur dessen Dauer, der Rest bis zum Ende der Achse ist Puffer
        moveEnd(i, unit) {
            const left = this.cum(i) - this.steps[i].days;
            const room = this.total - this.off - left;
            this.steps[i].days = Math.min(Math.max(unit - left, 1), room);
        },
        moveEdge(i, unit) {
            if (i >= this.steps.length - 1) this.moveEnd(i, unit);
            else this.moveBoundary(i, unit);
        },
        calendarUnit(e) {
            const rect = this.$refs.track.getBoundingClientRect();
            const c = Math.round((e.clientX - rect.left) / rect.width * this.span);
            return this.wdCal.filter((k) => k < c).length;
        },
        drag(onMove) {
            const up = () => {
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', up);
            };
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', up);
        },
        startDrag(i, event) {
            this.drag((e) => this.moveEdge(i, this.calendarUnit(e) - this.off));
        },
        // Linker Rand: der erste Schritt beginnt später (wird kürzer), alle späteren Schritte behalten ihre Lage; mindestens 1 AT bleibt
        startDragLeft(event) {
            this.drag((e) => {
                const target = Math.min(Math.max(this.calendarUnit(e), 0), this.lead + this.steps[0].days - 1);
                this.steps[0].days -= target - this.lead;
                this.lead = target;
            });
        },
        setEndDate(i, iso, el) {
            if (iso) {
                let idx = this.workdays.findIndex((day) => day >= iso);
                if (idx < 0) idx = this.total - 1;
                this.moveEdge(i, idx + 1 - this.off);
            }
            el.value = this.endIso(i);
        },
        setDays(i, value, el) {
            const n = Math.max(1, parseInt(value, 10) || 1);
            if (i < this.steps.length - 1) {
                const delta = Math.min(n - this.steps[i].days, this.steps[i + 1].days - 1);
                this.steps[i].days += delta;
                this.steps[i + 1].days -= delta;
            } else {
                this.steps[i].days = Math.min(n, this.total - this.off - (this.used - this.steps[i].days));
            }
            el.value = this.steps[i].days;
        },
        async saveDurations() {
            const durations = {};
            this.steps.forEach((step, i) => { if (step.days !== this.original[i]) durations[step.id] = step.days; });
            this.applying = true;
            const response = await fetch(@js(route('projekte.termine.save-durations', $project)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ durations: durations }),
            });
            let failed = ! response.ok ? response : null;
            if (! failed && this.lead > 0) {
                failed = await fetch(@js(route('projekte.termine.set-period', $project)), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ side: 'start', date: this.workdays[this.lead] }),
                }).then((r) => r.ok ? null : r);
            }
            this.applying = false;
            if (failed) {
                const data = await failed.json().catch(() => ({}));
                await window.notifyDialog(data.message || @js(__('Die Dauern konnten nicht gespeichert werden.')));
                return;
            }
            window.showToast(@js(__('Gespeichert.')));
            @if ($isOverlay)
                await window.refreshUnderlyingProject({{ $project->id }});
            @else
                window.location.reload();
            @endif
        },
        async applyPeriod(side, iso) {
            const message = side === 'end'
                ? @js(__('Das Projektende wird auf :date gesetzt. Die Termine der Schritte ändern sich dabei nicht.'))
                : @js(__('Der Projektstart wird auf :date gesetzt. Die Termine der Schritte ändern sich dabei nicht.'));
            if (! await window.confirmDialog({
                title: @js(__('Zeitraum anpassen?')),
                message: message.replace(':date', this.dateDe(iso)),
                confirmLabel: @js(__('Anpassen')),
                cancelLabel: @js(__('Abbrechen')),
            })) return;
            this.applying = true;
            const response = await fetch(@js(route('projekte.termine.set-period', $project)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ side: side, date: iso }),
            });
            this.applying = false;
            if (! response.ok) {
                const data = await response.json().catch(() => ({}));
                await window.notifyDialog(data.message || @js(__('Der Zeitraum konnte nicht angepasst werden.')));
                return;
            }
            window.showToast(@js(__('Gespeichert.')));
            @if ($isOverlay)
                await window.refreshUnderlyingProject({{ $project->id }});
            @else
                window.location.reload();
            @endif
        },
        reset() { this.steps.forEach((step, i) => { step.days = this.original[i]; }); this.lead = 0; },
    }"
    class="rounded-lg border border-gray-200 bg-white p-3 text-xs"
>
    <div
        x-show="liveMode !== 'match'"
        class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-md border px-2 py-1"
        :class="liveMode === 'overflow' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-600'"
    >
        <span title="{{ __('Summe der Dauern der Workflow-Schritte „In Bearbeitung“ (in Arbeitstagen) gegenüber den Arbeitstagen zwischen Projektstart und -ende.') }}">
            {{ __('Zeitbedarf laut Workflow') }}: <span class="font-semibold"><span x-text="used"></span> {{ __('AT') }}</span>
            &middot; {{ __('Projektzeitraum') }}: <span class="font-semibold"><span x-text="liveAvail"></span> {{ __('AT') }}</span>
            &middot;
            <span class="font-semibold" x-text="(liveMode === 'overflow' ? @js(__('Es fehlen :days AT')) : @js(__('Puffer: :days AT'))).replace(':days', Math.abs(liveDiff))"></span>
        </span>
        <template x-if="canEditPeriod && ! changed && period.new_end">
            <span class="inline-flex flex-wrap items-center gap-1.5">
                <button type="button" :disabled="applying" @mouseenter="showPreview('end')" @mouseleave="hidePreview()" @focus="showPreview('end')" @blur="hidePreview()" @click="hidePreview(); applyPeriod('end', period.new_end)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-wait disabled:opacity-50" :title="@js(__('Setzt das Projektende so, dass der Zeitraum zu den Dauern passt. Der Projektstart bleibt.'))">
                    <span x-text="@js(__('Ende auf :date setzen')).replace(':date', dateDe(period.new_end))"></span>
                </button>
                <button type="button" :disabled="applying || startInPast" @mouseenter="showPreview('start')" @mouseleave="hidePreview()" @focus="showPreview('start')" @blur="hidePreview()" @click="hidePreview(); applyPeriod('start', period.new_start)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-50" :title="startInPast ? @js(__('Der neue Start läge in der Vergangenheit.')) : @js(__('Setzt den Projektstart so, dass der Zeitraum zu den Dauern passt. Das Projektende bleibt.'))">
                    <span x-text="@js(__('Start auf :date setzen')).replace(':date', dateDe(period.new_start))"></span>
                </button>
                <button type="button" :disabled="applying" @mouseenter="showPreview('durations')" @mouseleave="hidePreview()" @focus="showPreview('durations')" @blur="hidePreview()" @click="hidePreview(); adjustDurations(hasOverrides)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-wait disabled:opacity-50" title="{{ __('Rechnet die Dauern der Schritte mit dem Faktor auf den Projektzeitraum um und speichert sie am Projekt. Der Workflow selbst bleibt unverändert.') }}">{{ __('Dauern an Projektzeitraum anpassen') }}</button>
            </span>
        </template>
    </div>

    <div class="px-1">
        <div x-ref="track" style="height: 60px" class="relative select-none overflow-visible rounded" :class="groups.length ? 'ml-[9.5rem]' : ''">
            <span x-show="groups.length" style="left: -9.5rem; top: 24px" class="absolute text-[10px] font-medium text-gray-400">{{ __('Workflow') }}</span>
            {{-- Raster im Hintergrund --}}
            <template x-for="k in grid.weekends" :key="'we-' + k">
                <div class="pointer-events-none absolute top-0 h-full" :style="{ left: pct(k) + '%', width: pct(1) + '%', backgroundColor: '#fdefc6' }"></div>
            </template>
            <template x-for="h in grid.holidays" :key="'ho-' + h.k">
                <div class="absolute top-0 h-full bg-rose-300" :style="{ left: pct(h.k) + '%', width: 'max(1px, ' + pct(1) + '%)', opacity: 0.6 }" :title="h.name + ' (' + dateWd(calendar[h.k]) + ')'"></div>
            </template>
            <template x-for="m in grid.months" :key="'mo-' + m.k">
                <div class="pointer-events-none absolute top-0 h-full w-px bg-gray-300" :style="{ left: pct(m.k) + '%' }"></div>
            </template>
            <template x-for="y in grid.years" :key="'ye-' + y.k">
                <div class="pointer-events-none absolute top-0 h-full w-0.5 -translate-x-px bg-gray-500" :style="{ left: pct(y.k) + '%' }"></div>
            </template>

            <div
                x-show="liveDiff > 0 && endIdx >= 0"
                class="pointer-events-none absolute flex items-center justify-center overflow-hidden whitespace-nowrap text-[10px] text-gray-500"
                :style="{ top: '16px', bottom: '16px', left: pct(edge(steps.length - 1)) + '%', width: Math.max(0, pct(endIdx + 1) - pct(edge(steps.length - 1))) + '%', backgroundImage: 'repeating-linear-gradient(135deg, #e5e7eb 0, #e5e7eb 2px, #f9fafb 2px, #f9fafb 6px)' }"
                :title="@js(__('Puffer: Zeit bis zum Projektende, die der Workflow nicht braucht'))"
            ><span x-show="preview !== 'end'" x-text="@js(__('Puffer: :days AT')).replace(':days', liveDiff)"></span></div>
            <div
                x-show="lead > 0"
                class="pointer-events-none absolute flex items-center justify-center overflow-hidden whitespace-nowrap text-[10px] text-gray-500"
                :style="{ top: '16px', bottom: '16px', left: '0%', width: pct(first()) + '%', backgroundImage: 'repeating-linear-gradient(135deg, #e5e7eb 0, #e5e7eb 2px, #f9fafb 2px, #f9fafb 6px)' }"
            ><span x-text="@js(__('Puffer: :days AT')).replace(':days', lead)"></span></div>
            <div
                x-show="period.mode === 'overflow' && endIdx >= 0 && preview !== 'durations'"
                class="absolute top-0 h-full"
                :style="{ left: pct(endIdx + 1) + '%', width: (100 - pct(endIdx + 1)) + '%', backgroundColor: 'rgba(239, 68, 68, 0.12)' }"
            ></div>
            <div
                x-show="period.mode === 'overflow' && endIdx >= 0 && preview !== 'durations'"
                class="pointer-events-none absolute top-0 z-[5] h-full w-0.5 -translate-x-px bg-red-500"
                :style="{ left: pct(endIdx + 1) + '%' }"
                :title="@js(__('Projektende')) + ' ' + dateWd(period.project_end) + ' – ' + @js(__('Der Workflow reicht :days AT darüber hinaus.')).replace(':days', period.diff)"
            ></div>

            <template x-if="preview === 'end' && newEndIdx >= 0">
                <div class="pointer-events-none">
                    <div
                        class="absolute top-0 flex h-full items-center justify-center overflow-hidden whitespace-nowrap text-[10px] font-medium"
                        :class="period.mode === 'buffer' ? 'text-red-700' : 'text-green-700'"
                        :style="period.mode === 'buffer'
                            ? { left: pct(newEndIdx + 1) + '%', width: (pct(endIdx + 1) - pct(newEndIdx + 1)) + '%', backgroundColor: 'rgba(239, 68, 68, 0.15)' }
                            : { left: pct(endIdx + 1) + '%', width: (100 - pct(endIdx + 1)) + '%', backgroundColor: 'rgba(34, 197, 94, 0.18)' }"
                        x-text="period.mode === 'buffer' ? @js(__('entfällt')) : @js(__('kommt dazu'))"
                    ></div>
                    <div class="absolute top-0 z-[5] h-full border-l-2 border-dashed border-blue-600" :style="{ left: pct(newEndIdx + 1) + '%' }"></div>
                </div>
            </template>
            <template x-if="preview === 'start' && period.mode === 'buffer' && off > 0">
                <div class="pointer-events-none">
                    <div
                        class="absolute top-0 flex h-full items-center justify-center overflow-hidden whitespace-nowrap text-[10px] font-medium text-red-700"
                        :style="{ left: '0%', width: pct(first()) + '%', backgroundColor: 'rgba(239, 68, 68, 0.15)' }"
                        x-text="@js(__('entfällt'))"
                    ></div>
                    <div class="absolute top-0 z-[5] h-full border-l-2 border-dashed border-blue-600" :style="{ left: pct(first()) + '%' }"></div>
                </div>
            </template>
            <div
                x-show="preview === 'start' && period.mode === 'overflow'"
                class="pointer-events-none absolute left-1 top-0 z-[5] rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-800"
                x-text="@js(__('Start früher: :date')).replace(':date', dateDe(period.new_start))"
            ></div>

            <template x-for="(step, i) in steps" :key="step.id">
                <div
                    class="absolute border-r border-white"
                    :class="{ 'rounded-l': i === 0, 'rounded-r': i === steps.length - 1 }"
                    :style="{ top: '16px', bottom: '16px', left: startPct(i) + '%', width: widthPct(i) + '%', backgroundColor: palette[i % palette.length], opacity: hover === null ? 0.85 : (hover === i ? 1 : 0.35), outline: hover === i ? '2px solid #1f2937' : 'none', zIndex: hover === i ? 6 : 0 }"
                    :title="tip(i)"
                ></div>
            </template>

            <div
                x-show="grid.today !== null"
                class="absolute top-0 z-[5] flex h-full w-2 -translate-x-1/2 justify-center"
                :style="{ left: pct((grid.today ?? 0) + 0.5) + '%' }"
                :title="@js(__('Heute')) + ': ' + dateWd(todayIso)"
            ><div class="h-full w-0.5 bg-blue-600"></div></div>

            <template x-for="m in milestones" :key="'ms-' + m.step_id">
                <div
                    class="absolute z-[11] h-2.5 w-2.5 -translate-x-1/2 rotate-45 border border-white"
                    :class="msLate(m) ? 'bg-red-600' : 'bg-gray-600'"
                    :style="{ left: msPct(m) + '%', bottom: '3px' }"
                    :title="msTip(m)"
                ></div>
            </template>

            <div
                class="absolute top-0 z-10 flex h-full w-3 -translate-x-1/2 cursor-col-resize items-center justify-center"
                :style="{ left: pct(first()) + '%' }"
                @pointerdown.prevent="startDragLeft($event)"
                :title="dateWd(workdays[lead])"
            >
                <div class="h-5 w-1 rounded bg-gray-700/70"></div>
            </div>
            <template x-for="(step, i) in steps" :key="'handle-' + step.id">
                <div
                    class="absolute top-0 z-10 flex h-full w-3 -translate-x-1/2 cursor-col-resize items-center justify-center"
                    :style="{ left: pct(edge(i)) + '%' }"
                    @pointerdown.prevent="startDrag(i, $event)"
                    :title="dateWd(endIso(i))"
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
                            class="absolute flex items-center justify-center overflow-hidden whitespace-nowrap rounded bg-sky-500 text-[10px] font-medium text-white"
                            :style="{ top: '2px', bottom: '2px', left: groupLeft(g) + '%', width: groupWidth(g) + '%' }"
                            :title="groupTip(g)"
                            x-text="hoursLabel(groupHours(g))"
                        ></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap gap-1">
        <template x-for="(step, i) in steps" :key="'field-' + step.id">
            <div class="flex items-center gap-1 rounded-md border px-1.5 py-0.5" :class="hover === i ? 'border-gray-500 bg-gray-50' : 'border-gray-200'" @mouseenter="hover = i" @mouseleave="hover = null">
                <span class="inline-block h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: palette[i % palette.length] }"></span>
                <span class="max-w-[10rem] truncate font-medium text-gray-700" x-text="shortLabel(step.title)" :title="step.title"></span>
                <input
                    type="date"
                    :value="endIso(i)"
                    @change="setEndDate(i, $event.target.value, $event.target)"
                    class="w-[5.5rem] rounded border-gray-300 px-1 py-px text-xs [&::-webkit-calendar-picker-indicator]:m-0 [&::-webkit-calendar-picker-indicator]:p-0 disabled:bg-gray-50 disabled:text-gray-500"
                    title="{{ __('Berechnetes Ende des Schritts') }}"
                >
                <input
                    type="number"
                    min="1"
                    :value="step.days"
                    @focus="$event.target.select()"
                    @mouseup.prevent
                    @change="setDays(i, $event.target.value, $event.target)"
                    class="w-10 rounded border-gray-300 px-1 py-px text-right text-xs"
                    :title="'{{ __('Dauer in Arbeitstagen (AT)') }}' + (step.fixed ? ' – {{ __('keine Dauer im Workflow eingetragen, zählt 1 Tag') }}' : ' – {{ __('laut Workflow') }}: ' + step.workflow_days)"
                >
                <span class="text-gray-500">{{ __('AT') }}</span>
            </div>
        </template>
    </div>

    <p x-show="milestones.length" class="mt-2 text-gray-500">
        <span class="mr-1 inline-block h-2 w-2 rotate-45 bg-gray-600 align-middle"></span>{{ __('Meilenstein (Termin am Schritt)') }}
        <span class="ml-3 mr-1 inline-block h-2 w-2 rotate-45 bg-red-600 align-middle"></span>{{ __('Schritt endet laut Plan nach dem Termin') }}
    </p>

    <div class="mt-2 flex flex-wrap items-center gap-2 text-gray-500">
        <template x-if="canEditPeriod">
            <button type="button" onclick="window.openProjectSchedule({{ $project->id }})" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Berechnet die Termine der Schritte aus einem Fixpunkt und den Dauern und trägt sie auf Wunsch ein.') }}">{{ __('Termine berechnen …') }}</button>
        </template>
        <span x-show="changed" x-cloak class="text-amber-700">{{ __('Nicht gespeicherte Änderungen: Dauern (und ein späterer Projektstart) werden erst mit „Speichern“ am Projekt übernommen, die Termine der Schritte bleiben unverändert.') }}</span>
        <span x-show="! canEditPeriod && changed" x-cloak class="text-gray-400">{{ __('Ihnen fehlt die Berechtigung, Termine und Dauern zu ändern.') }}</span>
        <span class="flex-1"></span>
        <button type="button" x-show="changed" x-cloak @click="reset()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Zurücksetzen') }}</button>
        <button type="button" x-show="changed && canEditPeriod" x-cloak :disabled="applying" @click="saveDurations()" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50" title="{{ __('Speichert die geänderten Dauern am Projekt. Die Termine der Schritte ändern sich dabei nicht.') }}">{{ __('Speichern') }}</button>
    </div>
</div>
