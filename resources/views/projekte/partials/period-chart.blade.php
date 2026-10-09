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
@php $isNewModel = (int) $project->schedule_model === 2; @endphp
<div
    x-data="{
        workdays: @js($chart['workdays']),
        calendar: @js($chart['calendar']),
        holidays: @js($chart['holidays']),
        steps: @js($chart['steps']),
        groups: @js($chart['groups']),
        period: @js($chart['period']),
        pre: @js($chart['pre']),
        milestones: @js($chart['milestones']),
        hasOverrides: @js($hasOverrides ?? false),
        canEditPeriod: @js($canEditPeriod ?? false),
        applying: false,
        hover: null,
        groupsOpen: (() => { try { return localStorage.getItem('vectory-period-groups-open') !== '0'; } catch (e) { return true; } })(),
        stepsOpen: (() => { try { return localStorage.getItem('vectory-period-steps-open') !== '0'; } catch (e) { return true; } })(),
        preview: null,
        lead: 0,
        previewOff: 0,
        backupDays: null,
        original: null,
        wdCal: [],
        grid: { weekends: [], holidays: [], months: [], years: [], today: null },
        palette: ['#93c5fd', '#6ee7b7', '#fcd34d', '#f9a8d4', '#c4b5fd', '#fdba74', '#5eead4', '#fca5a5'],
        dirtyHook: null,
        init() {
            this.original = this.steps.map((step) => step.days);
            this.dirtyHook = () => this.changed;
            window.periodChartIsDirty = this.dirtyHook;
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
        destroy() { if (window.periodChartIsDirty === this.dirtyHook) window.periodChartIsDirty = null; },
        ms: null,
        msSaving: false,
        msError: '',
        msUrl: @js(route('projekte.meilensteine.store', $project)),
        msCsrf: @js(csrf_token()),
        projectId: @js($project->id),
        focusMs() { setTimeout(() => { if (this.$refs.msName) this.$refs.msName.focus(); }, 30); },
        newMs() {
            this.ms = { id: null, name: '', anchor_type: 'workflow_end', anchor_workflow_step_id: this.steps.length ? this.steps[this.steps.length - 1].id : null, offset_days: 0, fixed_date: '', check_direction: '' };
            this.msError = '';
            this.focusMs();
        },
        editMs(m) {
            this.ms = { id: m.id, name: m.title, anchor_type: m.anchor_type, anchor_workflow_step_id: m.anchor_step_id || (this.steps.length ? this.steps[0].id : null), offset_days: m.offset_days, fixed_date: m.fixed_date || '', check_direction: m.check_direction || '' };
            this.msError = '';
            this.focusMs();
        },
        // Neu laden verwirft einen nicht gespeicherten Diagramm-Entwurf (Dauern): vorher nachfragen
        async msConfirmDiscard() {
            if (! this.changed) return true;
            return await window.confirmDialog({
                signal: 'achtung',
                title: @js(__('Entwurf verwerfen?')),
                message: @js(__('Im Diagramm gibt es nicht gespeicherte Änderungen an Dauern.')),
                consequence: @js(__('Wenn Sie den Meilenstein jetzt speichern, gehen diese Änderungen verloren.')),
                confirmLabel: @js(__('Verwerfen und fortfahren')),
                cancelLabel: @js(__('Abbrechen')),
            });
        },
        async msSend(method, url, body) {
            const response = await fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.msCsrf }, body: body ? JSON.stringify(body) : undefined });
            if (response.status === 422) {
                const data = await response.json().catch(() => ({}));
                this.msError = Object.values(data.errors || {}).flat()[0] || data.message || @js(__('Das Speichern ist fehlgeschlagen.'));
                return false;
            }
            if (! response.ok) {
                this.msError = @js(__('Das Speichern ist fehlgeschlagen.'));
                return false;
            }
            this.ms = null;
            window.dispatchEvent(new CustomEvent('project-schedule-changed', { detail: { projectId: this.projectId } }));
            return true;
        },
        async saveMs() {
            if (this.msSaving || ! this.ms) return;
            if (! (await this.msConfirmDiscard())) return;
            const isStep = ['step_start', 'step_end'].includes(this.ms.anchor_type);
            const body = {
                name: this.ms.name,
                anchor_type: this.ms.anchor_type,
                anchor_workflow_step_id: isStep ? this.ms.anchor_workflow_step_id : null,
                offset_days: this.ms.anchor_type === 'fixed' ? 0 : (this.ms.offset_days === '' ? 0 : this.ms.offset_days),
                fixed_date: this.ms.anchor_type === 'fixed' ? this.ms.fixed_date : null,
                check_direction: this.ms.check_direction || null,
            };
            this.msSaving = true;
            this.msError = '';
            await this.msSend(this.ms.id ? 'PATCH' : 'POST', this.ms.id ? this.msUrl + '/' + this.ms.id : this.msUrl, body);
            this.msSaving = false;
        },
        async deleteMs() {
            if (this.msSaving || ! this.ms || ! this.ms.id) return;
            const ok = await window.confirmDialog({
                signal: 'achtung',
                title: @js(__('Meilenstein löschen?')),
                message: @js(__('Der Meilenstein wird aus diesem Projekt entfernt.')),
                consequence: @js(__('Das lässt sich nicht rückgängig machen.')),
                confirmLabel: @js(__('Löschen')),
                cancelLabel: @js(__('Abbrechen')),
            });
            if (! ok || ! (await this.msConfirmDiscard())) return;
            this.msSaving = true;
            await this.msSend('DELETE', this.msUrl + '/' + this.ms.id, null);
            this.msSaving = false;
        },
        msDrag: null,
        // Meilensteine lassen sich nur ziehen, solange das Diagramm keinen ungespeicherten Entwurf hat (die Lage passt sonst nicht zum Server)
        msDraggable(m) { return m.kind === 'milestone' && this.canEditPeriod && ! this.changed; },
        // Arbeitstag-Index des Bezugspunkts im Diagramm
        msAnchorIdx(m) {
            if (m.anchor_type === 'workflow_start') return this.off;
            if (m.anchor_type === 'workflow_end') return this.off + this.used - 1;
            const i = this.steps.findIndex((step) => step.id === m.anchor_step_id);
            if (i < 0) return null;
            return m.anchor_type === 'step_start' ? this.off + this.cum(i) - this.steps[i].days : this.off + this.cum(i) - 1;
        },
        // Arbeitstag unter dem Mauszeiger (nächstliegender)
        nearestWorkday(e) {
            const rect = this.$refs.track.getBoundingClientRect();
            const c = (e.clientX - rect.left) / rect.width * this.span - 0.5;
            let best = 0;
            let bestDist = Infinity;
            this.wdCal.forEach((k, idx) => {
                const dist = Math.abs(k - c);
                if (dist < bestDist) { bestDist = dist; best = idx; }
            });
            return best;
        },
        startMsDrag(m) {
            if (! this.msDraggable(m)) return;
            const anchor = m.anchor_type === 'fixed' ? 0 : this.msAnchorIdx(m);
            if (anchor === null) return;
            this.msDrag = { id: m.id, date: m.date, idx: null, moved: false };
            const onMove = (e) => {
                const idx = this.nearestWorkday(e);
                this.msDrag.idx = idx;
                this.msDrag.date = this.workdays[idx];
                this.msDrag.moved = true;
            };
            const up = async () => {
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', up);
                const drag = this.msDrag;
                this.msDrag = null;
                if (! drag || ! drag.moved || drag.date === m.date) return;
                const fixed = m.anchor_type === 'fixed';
                const saved = await this.msSend('PATCH', this.msUrl + '/' + m.id, {
                    name: m.title,
                    anchor_type: m.anchor_type,
                    anchor_workflow_step_id: m.anchor_step_id,
                    offset_days: fixed ? 0 : drag.idx - anchor,
                    fixed_date: fixed ? drag.date : null,
                    check_direction: m.check_direction || null,
                });
                if (! saved && window.notifyDialog) window.notifyDialog(this.msError);
            };
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', up);
        },
        // Meilensteine außerhalb des Zeitstrahls stehen in den Randbereichen links/rechts (nächster zuerst), der Maßstab bleibt unverändert
        msInside(m) { return m.date && m.date >= this.calendar[0] && m.date <= this.calendar[this.calendar.length - 1]; },
        get outsideLeft() { return this.milestones.filter((m) => m.date && m.date < this.calendar[0]).sort((a, b) => (a.date < b.date ? 1 : -1)); },
        get outsideRight() { return this.milestones.filter((m) => m.date && m.date > this.calendar[this.calendar.length - 1]).sort((a, b) => (a.date < b.date ? -1 : 1)); },
        daysBetween(a, b) {
            const [y1, m1, d1] = a.split('-').map(Number);
            const [y2, m2, d2] = b.split('-').map(Number);
            return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000);
        },
        // Abstand in Worten, z. B. 12 Monate vor Projektstart
        msOutsideTip(m, before) {
            const days = Math.abs(before ? this.daysBetween(m.date, this.period.project_start) : this.daysBetween(this.period.project_end, m.date));
            const amount = days >= 60 ? Math.round(days / 30.4) + ' ' + @js(__('Monate')) : days + ' ' + (days === 1 ? @js(__('Tag')) : @js(__('Tage')));
            return m.title + ' · ' + this.dateWd(m.date) + ' – ' + amount + ' ' + (before ? @js(__('vor Projektstart')) : @js(__('nach Projektende')));
        },
        fix: null,
        fixUrl: @js(route('projekte.termine.fixpunkt', $project)),
        openFix(point, label, current) {
            this.fix = { point: point, label: label, date: current || '', current: current || '', preview: null, busy: false, error: '' };
            this.$nextTick(() => { const el = this.$refs.fixDate; if (el) { el.focus(); if (el.showPicker) { try { el.showPicker(); } catch (e) {} } } });
        },
        async fixSend(preview) {
            const response = await fetch(this.fixUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.msCsrf }, body: JSON.stringify({ point: this.fix.point, date: this.fix.date, preview: preview }) });
            if (response.status === 422) {
                const data = await response.json().catch(() => ({}));
                this.fix.error = Object.values(data.errors || {}).flat()[0] || data.message || @js(__('Das Datum lässt sich nicht verwenden.'));
                this.fix.preview = null;
                return null;
            }
            if (! response.ok) { this.fix.error = @js(__('Das Speichern ist fehlgeschlagen.')); return null; }
            this.fix.error = '';
            return await response.json();
        },
        async fixPreview() {
            if (! this.fix || ! this.fix.date) return;
            this.fix.preview = await this.fixSend(true);
        },
        async applyFix() {
            if (! this.fix || this.fix.busy || ! this.fix.date) return;
            if (! (await this.msConfirmDiscard())) return;
            this.fix.busy = true;
            const result = await this.fixSend(false);
            this.fix.busy = false;
            if (! result) return;
            this.fix = null;
            window.dispatchEvent(new CustomEvent('project-schedule-changed', { detail: { projectId: this.projectId } }));
            if (window.refreshUnderlyingProject) window.refreshUnderlyingProject(this.projectId);
        },
        weekNo(iso) {
            if (! iso) return '';
            const [y, m, d] = iso.split('-').map(Number);
            const date = new Date(Date.UTC(y, m - 1, d));
            const day = date.getUTCDay() || 7;
            date.setUTCDate(date.getUTCDate() + 4 - day);
            const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
            return Math.ceil(((date - yearStart) / 86400000 + 1) / 7);
        },
        startIso(i) { return this.workdays[Math.max(0, this.off + this.cum(i) - this.steps[i].days)]; },
        // Tabelle: Phasen in Reihenfolge, Meilensteine (neues Terminmodell) an ihrer zeitlichen Stelle
        get tableRows() {
            const rows = this.steps.map((step, i) => ({ type: 'phase', i: i, date: this.endIso(i), key: 'p-' + step.id }));
            this.milestones.filter((m) => m.kind === 'milestone').forEach((m) => rows.push({ type: 'milestone', m: m, date: m.date, key: m.key }));
            const key = (r) => r.date || '9999-12-31';
            return rows.sort((a, b) => (key(a) === key(b) ? (a.type === 'phase' ? -1 : 1) : (key(a) < key(b) ? -1 : 1)));
        },
        get total() { return this.workdays.length; },
        get span() { return this.calendar.length; },
        // Summe der Dauern; kleiner als total = Puffer bis zum Projektende, total ist bei zu knappem Zeitraum über das Projektende hinaus verlängert
        get used() { return this.steps.reduce((a, step) => a + step.days, 0); },
        get endIdx() { return this.calendar.indexOf(this.period.project_end); },
        get todayIso() { const now = new Date(); return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'); },
        get startInPast() { return this.period.new_start !== null && this.period.new_start < this.todayIso; },
        // Vorlauf: Arbeitstage am Anfang, an denen noch kein Schritt läuft (linken Rand nach rechts gezogen); dazu die Vorschau-Verschiebung
        get off() { return this.pre + this.lead + this.previewOff; },
        get startIdx() { return this.calendar.indexOf(this.period.project_start); },
        get liveAvail() { return this.period.available - this.lead; },
        // Differenz Zeitraum - Bedarf laut Diagramm: positiv = Puffer, negativ = es fehlen Tage
        // echte Dauern ohne die vorübergehenden Werte der Vorschau, damit Zeile und Knöpfe unter der Maus nicht verschwinden
        get realDays() { return this.backupDays ?? this.steps.map((step) => step.days); },
        get liveDiff() { return this.liveAvail - this.realDays.reduce((a, days) => a + days, 0); },
        get liveMode() { return this.liveDiff === 0 ? 'match' : (this.liveDiff > 0 ? 'buffer' : 'overflow'); },
        get changed() { return this.original !== null && (this.lead !== 0 || this.realDays.some((days, i) => days !== this.original[i])); },
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
            if (kind === 'start' && this.period.mode === 'overflow' && this.pre > 0) this.previewOff = -this.pre;
        },
        hidePreview() {
            if (this.backupDays) { this.steps.forEach((step, i) => { step.days = this.backupDays[i]; }); this.backupDays = null; }
            this.previewOff = 0;
            this.preview = null;
        },
        pct(k) { return k / this.span * 100; },
        // Meilenstein = Schritt mit eingetragenem Termin; außerhalb des Diagramms am Rand
        msPct(m) {
            const date = this.msDrag && this.msDrag.id === m.id ? this.msDrag.date : m.date;
            let idx = this.calendar.indexOf(date);
            if (idx < 0) idx = date < this.calendar[0] ? 0 : this.span - 1;
            return (idx + 0.5) / this.span * 100;
        },
        // Zu spät: der berechnete Schritt endet erst nach seinem Meilenstein
        msLate(m) {
            const i = this.steps.findIndex((step) => step.id === m.step_id);
            return i >= 0 && this.endIso(i) > m.date;
        },
        msTip(m) {
            const i = this.steps.findIndex((step) => step.id === m.step_id);
            const base = m.title + ' · ' + this.dateWd(m.date) + (this.msDraggable(m) ? ' – ' + @js(__('Zum Verschieben ziehen')) : '');
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
        // Gesperrte Schritte behalten ihre Dauer und wandern beim Verschieben mit; ausgleichen tut der nächste nicht gesperrte Schritt davor bzw. dahinter
        unlockedBefore(i) { for (let k = i; k >= 0; k--) { if (! this.steps[k].locked) return k; } return -1; },
        unlockedAfter(i) { for (let k = i; k < this.steps.length; k++) { if (! this.steps[k].locked) return k; } return -1; },
        toggleSteps() {
            this.stepsOpen = ! this.stepsOpen;
            try { localStorage.setItem('vectory-period-steps-open', this.stepsOpen ? '1' : '0'); } catch (e) {}
        },
        toggleGroups() {
            this.groupsOpen = ! this.groupsOpen;
            try { localStorage.setItem('vectory-period-groups-open', this.groupsOpen ? '1' : '0'); } catch (e) {}
        },
        // Schloss umschalten und sofort am Projekt speichern (Voreinstellung steht am Workflow-Schritt); ohne Recht nur vorübergehend
        async toggleLock(i) {
            const step = this.steps[i];
            step.locked = ! step.locked;
            if (! this.canEditPeriod) return;
            const response = await fetch(@js(route('projekte.termine.set-duration-lock', $project)), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ step_id: step.id, locked: step.locked }),
            });
            if (! response.ok) {
                step.locked = ! step.locked;
                const data = await response.json().catch(() => ({}));
                await window.notifyDialog(data.message || @js(__('Die Sperre konnte nicht gespeichert werden.')));
            }
        },
        // Grenze hinter Schritt i auf Arbeitstag-Position unit schieben (relativ zum Start des ersten Schritts); jeder ausgleichende Schritt behält mindestens 1 AT
        moveBoundary(i, unit) {
            if (i < 0 || i >= this.steps.length - 1) return;
            const before = this.unlockedBefore(i);
            const after = this.unlockedAfter(i + 1);
            if (before < 0 || after < 0) return;
            const delta = Math.max(1 - this.steps[before].days, Math.min(unit - this.cum(i), this.steps[after].days - 1));
            this.steps[before].days += delta;
            this.steps[after].days -= delta;
        },
        // Ende des letzten Schritts verschieben: der Rest bis zum Ende der Achse ist Puffer
        moveEnd(i, unit) {
            const before = this.unlockedBefore(i);
            if (before < 0) return;
            const room = this.total - this.off - this.used;
            this.steps[before].days += Math.max(1 - this.steps[before].days, Math.min(unit - this.cum(i), room));
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
                const first = this.unlockedAfter(0);
                if (first < 0) return;
                const target = Math.min(Math.max(this.calendarUnit(e) - this.pre, 0), this.lead + this.steps[first].days - 1);
                this.steps[first].days -= target - this.lead;
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
                const after = this.unlockedAfter(i + 1);
                if (after >= 0) {
                    const delta = Math.min(n - this.steps[i].days, this.steps[after].days - 1);
                    this.steps[i].days += delta;
                    this.steps[after].days -= delta;
                }
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
                    body: JSON.stringify({ side: 'start', date: this.workdays[this.pre + this.lead] }),
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
                ? @js($isNewModel ? __('Das Projektende wird auf :date gesetzt. Alle Termine werden daraus neu berechnet.') : __('Das Projektende wird auf :date gesetzt. Die Termine der Schritte ändern sich dabei nicht.'))
                : @js($isNewModel ? __('Der Projektstart wird auf :date gesetzt. Alle Termine werden daraus neu berechnet.') : __('Der Projektstart wird auf :date gesetzt. Die Termine der Schritte ändern sich dabei nicht.'));
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
            {{ __('Zeitbedarf laut Workflow') }}: <span class="font-semibold"><span x-text="realDays.reduce((a, days) => a + days, 0)"></span> {{ __('AT') }}</span>
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

    <div class="px-1" :class="[outsideLeft.length && ! groups.length ? 'pl-6' : '', outsideRight.length ? 'pr-4' : '']">
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
                x-show="pre > 0 && startIdx > 0 && preview !== 'start'"
                class="absolute top-0 h-full"
                :style="{ left: '0%', width: pct(startIdx) + '%', backgroundColor: 'rgba(239, 68, 68, 0.12)' }"
            ></div>
            <div
                x-show="pre > 0 && startIdx > 0 && preview !== 'start'"
                class="pointer-events-none absolute top-0 z-[5] h-full w-0.5 -translate-x-px bg-red-500"
                :style="{ left: pct(startIdx) + '%' }"
                :title="@js(__('Projektstart')) + ' ' + dateWd(period.project_start)"
            ></div>
            <div
                x-show="lead > 0"
                class="pointer-events-none absolute flex items-center justify-center overflow-hidden whitespace-nowrap text-[10px] text-gray-500"
                :style="{ top: '16px', bottom: '16px', left: pct(startIdx) + '%', width: (pct(first()) - pct(startIdx)) + '%', backgroundImage: 'repeating-linear-gradient(135deg, #e5e7eb 0, #e5e7eb 2px, #f9fafb 2px, #f9fafb 6px)' }"
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
                x-show="preview === 'start' && period.mode === 'overflow' && pre === 0"
                class="pointer-events-none absolute left-1 top-0 z-[5] rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-800"
                x-text="@js(__('Start früher: :date')).replace(':date', dateDe(period.new_start))"
            ></div>

            <template x-for="(step, i) in steps" :key="step.id">
                <div
                    class="absolute border-r border-white"
                    :class="{ 'rounded-l': i === 0, 'rounded-r': i === steps.length - 1 }"
                    :style="{ top: '16px', bottom: '16px', left: startPct(i) + '%', width: widthPct(i) + '%', backgroundColor: palette[i % palette.length], opacity: hover === null ? 0.85 : (hover === i ? 1 : 0.35), outline: hover === i ? '2px solid #1f2937' : 'none', zIndex: hover === i ? 6 : 0 }"
                    :title="tip(i)"
                    @mouseenter="hover = i"
                    @mouseleave="hover = null"
                ></div>
            </template>

            <div
                x-show="grid.today !== null"
                class="absolute top-0 z-[5] flex h-full w-2 -translate-x-1/2 justify-center"
                :style="{ left: pct((grid.today ?? 0) + 0.5) + '%' }"
                :title="@js(__('Heute')) + ': ' + dateWd(todayIso)"
            ><div class="h-full w-0.5 bg-blue-600"></div></div>

            <template x-for="m in milestones.filter((x) => msInside(x))" :key="'ms-' + m.key">
                <div
                    class="absolute z-[11] -translate-x-1/2 rotate-45"
                    :class="[m.kind === 'milestone' ? 'h-3 w-3 border border-white bg-indigo-600 ring-1 ring-indigo-300' : (msLate(m) ? 'h-2.5 w-2.5 border border-white bg-red-600' : 'h-2.5 w-2.5 border-2 border-gray-600 bg-white'), msDraggable(m) ? 'cursor-grab touch-none' : '', msDrag && msDrag.id === m.id ? 'scale-125 cursor-grabbing' : '']"
                    @pointerdown.prevent="startMsDrag(m)"
                    :style="{ left: msPct(m) + '%', bottom: '3px' }"
                    :title="msTip(m)"
                ></div>
            </template>

            {{-- Meilensteine außerhalb des Zeitraums: Randbereiche mit Pfeil nach außen und Abstand im Tooltip --}}
            <template x-for="(m, j) in outsideLeft.slice(0, 4)" :key="'out-l-' + m.key">
                <div class="absolute z-[11] flex cursor-help items-center gap-0.5" :style="{ right: 'calc(100% + 2px)', bottom: (3 + j * 14) + 'px' }" :title="msOutsideTip(m, true)">
                    <span class="text-[10px] leading-none text-indigo-700">&#9664;</span>
                    <span class="inline-block h-3 w-3 rotate-45 border border-white bg-indigo-600 ring-1 ring-indigo-300"></span>
                </div>
            </template>
            <template x-for="(m, j) in outsideRight.slice(0, 4)" :key="'out-r-' + m.key">
                <div class="absolute z-[11] flex cursor-help items-center gap-0.5" :style="{ left: 'calc(100% + 2px)', bottom: (3 + j * 14) + 'px' }" :title="msOutsideTip(m, false)">
                    <span class="inline-block h-3 w-3 rotate-45 border border-white bg-indigo-600 ring-1 ring-indigo-300"></span>
                    <span class="text-[10px] leading-none text-indigo-700">&#9654;</span>
                </div>
            </template>
            <span x-show="outsideLeft.length > 4" class="absolute text-[10px] text-gray-500" style="right: calc(100% + 2px); top: 0" :title="@js(__('Weitere Meilensteine vor dem Zeitraum: siehe Tabelle'))" x-text="'+' + (outsideLeft.length - 4)"></span>
            <span x-show="outsideRight.length > 4" class="absolute text-[10px] text-gray-500" style="left: calc(100% + 2px); top: 0" :title="@js(__('Weitere Meilensteine nach dem Zeitraum: siehe Tabelle'))" x-text="'+' + (outsideRight.length - 4)"></span>

            <div
                class="absolute top-0 z-10 flex h-full w-3 -translate-x-1/2 cursor-col-resize items-center justify-center"
                :style="{ left: pct(first()) + '%' }"
                @pointerdown.prevent="startDragLeft($event)"
                :title="dateWd(workdays[pre + lead])"
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
            <button type="button" @click="toggleGroups()" :aria-expanded="groupsOpen" class="flex items-center gap-1 text-[10px] font-medium text-gray-400 hover:text-gray-600">
                <svg class="h-3 w-3 transition-transform" :class="groupsOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                {{ __('Einsatzplan') }}
            </button>
            <template x-for="g in (groupsOpen ? groups : [])" :key="'group-' + g.id">
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

    <div class="mt-2">
        <button type="button" @click="toggleSteps()" :aria-expanded="stepsOpen" class="flex items-center gap-1 text-[10px] font-medium text-gray-400 hover:text-gray-600">
            <svg class="h-3 w-3 transition-transform" :class="stepsOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            {{ __('Workflowschritte') }}
        </button>
    </div>
    <div x-show="stepsOpen" class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-xs">
            <thead>
                <tr class="text-left text-[10px] font-medium uppercase tracking-wide text-gray-400">
                    <th class="w-14 py-1 pr-1"></th>
                    <th class="py-1 pr-2">{{ __('Phase') }}</th>
                    <th class="py-1 pr-2">{{ __('Start') }}</th>
                    <th class="py-1 pr-2">{{ __('Ende') }}</th>
                    <th class="py-1 pr-2">{{ __('Phasenende') }}</th>
                    <th class="py-1 pr-2">{{ __('KW') }}</th>
                    <th class="py-1 pr-2">{{ __('Dauer (AT)') }}</th>
                    @if ((int) $project->schedule_model === 2)
                        <th class="w-6 py-1"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <template x-for="row in tableRows" :key="row.key">
                    <tr class="h-8 border-t border-gray-100" :class="row.type === 'phase' && hover === row.i ? 'bg-gray-50' : ''" @mouseenter="hover = row.type === 'phase' ? row.i : null" @mouseleave="hover = null">
                        <td class="whitespace-nowrap py-0 align-middle pr-1">
                            <div class="flex h-6 items-center gap-1">
                            <template x-if="row.type === 'phase'">
                                <div class="flex items-center gap-1">
                                    <span class="inline-block h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: palette[row.i % palette.length] }"></span>
                                    <button
                                        type="button"
                                        @click="toggleLock(row.i)"
                                        class="shrink-0 rounded p-px"
                                        :class="steps[row.i].locked ? 'text-gray-700' : 'text-gray-300 hover:text-gray-500'"
                                        :title="steps[row.i].locked ? @js(__('Gesperrt: Die Dauer bleibt unverändert, der Schritt wandert beim Verschieben mit. Zum Entsperren klicken.')) : @js(__('Sperren: Die Dauer bleibt beim Verschieben anderer Schritte unverändert, der Schritt wandert mit.'))"
                                        :aria-pressed="steps[row.i].locked ? 'true' : 'false'"
                                    >
                    <svg x-show="steps[row.i].locked" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v3H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 7V5a2 2 0 10-4 0v3h4z" clip-rule="evenodd" /></svg>
                    <svg x-show="! steps[row.i].locked" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.5 1A4.5 4.5 0 0010 5.5V9H3a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-1.5V5.5a3 3 0 116 0v2.75a.75.75 0 001.5 0V5.5A4.5 4.5 0 0014.5 1z" clip-rule="evenodd" /></svg>
                                    </button>
                                </div>
                            </template>
                            <template x-if="row.type === 'milestone'">
                                <span class="ml-0.5 inline-block h-2.5 w-2.5 rotate-45 bg-indigo-600 align-middle" :title="@js(__('Meilenstein'))"></span>
                            </template>
                        </div>
                        </td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2">
                            <div class="flex h-6 items-center gap-1">
                            <template x-if="row.type === 'phase'">
                                <span class="block max-w-[16rem] truncate font-medium text-gray-700" x-text="steps[row.i].title" :title="steps[row.i].title"></span>
                            </template>
                            <template x-if="row.type === 'milestone'">
                                <span class="block max-w-[16rem] truncate text-gray-700" x-text="row.m.title" :title="row.m.title"></span>
                            </template>
                        </div>
                        </td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2 text-gray-500">
                            <div class="flex h-6 items-center gap-1">
                            <span class="inline-flex items-center gap-1">
                                <template x-if="row.type === 'phase'"><span x-text="dateDe(startIso(row.i))" :title="@js(__('Beginn der Phase (berechnet)'))"></span></template>
                                @if ((int) $project->schedule_model === 2)
                                    <template x-if="row.type === 'phase' && canEditPeriod">
                                        <button type="button" @click="openFix('step_start:' + steps[row.i].id, @js(__('Start von')) + ' ' + steps[row.i].title, startIso(row.i))" class="inline-flex h-6 w-6 items-center justify-center rounded text-gray-300 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Fixpunkt hier setzen, um die Termine neu zu berechnen') }}"><x-icon name="target" class="h-3.5 w-3.5" /></button>
                                    </template>
                                @endif
                            </span>
                        </div>
                        </td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2">
                            <div class="flex h-6 items-center gap-1">
                            <template x-if="row.type === 'phase'">
                                <input
                                    type="date"
                                    :value="endIso(row.i)"
                                    :disabled="steps[row.i].locked"
                                    @change="setEndDate(row.i, $event.target.value, $event.target)"
                                    class="w-[6.5rem] h-6 rounded border-gray-300 px-1 py-0 text-xs [&::-webkit-calendar-picker-indicator]:m-0 [&::-webkit-calendar-picker-indicator]:p-0 disabled:bg-gray-50 disabled:text-gray-500"
                                    title="{{ __('Berechnetes Ende der Phase') }}"
                                >
                            </template>
                            <template x-if="row.type === 'milestone'"><span class="text-gray-700" x-text="dateDe(row.m.date)"></span></template>
                            @if ((int) $project->schedule_model === 2)
                                <template x-if="row.type === 'phase' && canEditPeriod">
                                    <button type="button" @click="openFix('step_end:' + steps[row.i].id, @js(__('Ende von')) + ' ' + steps[row.i].title, endIso(row.i))" class="inline-flex h-6 w-6 items-center justify-center rounded text-gray-300 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Fixpunkt hier setzen, um die Termine neu zu berechnen') }}"><x-icon name="target" class="h-3.5 w-3.5" /></button>
                                </template>
                                <template x-if="row.type === 'milestone' && canEditPeriod && row.m.anchor_type !== 'fixed'">
                                    <button type="button" @click="openFix('milestone:' + row.m.id, row.m.title, row.m.date)" class="inline-flex h-6 w-6 items-center justify-center rounded text-gray-300 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Fixpunkt hier setzen, um die Termine neu zu berechnen') }}"><x-icon name="target" class="h-3.5 w-3.5" /></button>
                                </template>
                            @endif
                        </div>
                        </td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2 text-gray-500">
                            <div class="flex h-6 items-center gap-1">
                            <template x-if="row.type === 'phase'"><span x-text="steps[row.i].end_name" :title="steps[row.i].end_name"></span></template>
                            <template x-if="row.type === 'milestone'"><span x-text="row.m.rule"></span></template>
                        </div>
                        </td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2 text-gray-500" x-text="weekNo(row.date)"></td>
                        <td class="whitespace-nowrap py-0 align-middle pr-2">
                            <div class="flex h-6 items-center gap-1">
                            <template x-if="row.type === 'phase'">
                                <input
                                    type="number"
                                    min="1"
                                    :value="steps[row.i].days"
                                    :disabled="steps[row.i].locked"
                                    @focus="$event.target.select()"
                                    @mouseup="if ($event.offsetX < $event.target.clientWidth - 18) $event.preventDefault()"
                                    @change="setDays(row.i, $event.target.value, $event.target)"
                                    class="h-6 w-14 rounded border-gray-300 px-1 py-0 text-right text-xs disabled:bg-gray-50 disabled:text-gray-500"
                                    :title="'{{ __('Dauer in Arbeitstagen (AT)') }}' + (steps[row.i].fixed ? ' – {{ __('keine Dauer im Workflow eingetragen, zählt 1 Tag') }}' : ' – {{ __('laut Workflow') }}: ' + steps[row.i].workflow_days)"
                                >
                            </template>
                        </div>
                        </td>
                        @if ((int) $project->schedule_model === 2)
                            <td class="whitespace-nowrap py-0 align-middle">
                            <div class="flex h-6 items-center gap-1">
                                <template x-if="row.type === 'milestone' && canEditPeriod">
                                    <button type="button" @click="editMs(row.m)" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Meilenstein ändern') }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </button>
                                </template>
                            </div>
                        </td>
                        @endif
                    </tr>
                </template>
            </tbody>
        </table>
        @if ((int) $project->schedule_model === 2)
            <template x-if="canEditPeriod">
                <div x-show="fix !== null" x-cloak @keydown.enter.prevent="applyFix()" class="mt-1 rounded-md border border-gray-200 bg-gray-50 p-2">
                    <div class="mb-1.5 text-xs font-semibold text-gray-700">{{ __('Termine neu berechnen') }}</div>
                    <div class="flex flex-wrap items-end gap-x-4 gap-y-1">
                        <div>
                            <span class="block text-[10px] text-gray-500">{{ __('Fixpunkt') }}</span>
                            <span class="flex min-h-7 max-w-[24rem] items-center font-medium text-gray-700" x-text="fix ? fix.label : ''"></span>
                        </div>
                        <label class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('auf Datum') }}</span>
                            <input type="date" x-ref="fixDate" :value="fix ? fix.date : ''" @input="fix.date = $event.target.value" @change="fixPreview()" class="h-7 rounded border-gray-300 px-1.5 py-0 text-xs">
                        </label>
                        <div class="min-w-[24rem] flex-1">
                            <span class="block text-[10px] text-gray-500">{{ __('Ergebnis') }}</span>
                            <span class="flex min-h-7 items-center text-gray-700" :class="fix && fix.preview ? '' : 'invisible'">
                                <span x-text="@js(__('Neu berechnet')) + ': ' + @js(__('Projektstart')) + ' ' + (fix && fix.preview ? dateDe(fix.preview.start) : '00.00.0000') + ', ' + @js(__('Projektende')) + ' ' + (fix && fix.preview ? dateDe(fix.preview.end) : '00.00.0000') + ' (' + @js(__('noch nicht gespeichert')) + ')'"></span>
                            </span>
                        </div>
                    </div>
                    <p x-show="fix && fix.error" x-text="fix ? fix.error : ''" class="mt-1 text-red-600"></p>
                    <div class="mt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="fix = null" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="button" @click="applyFix()" :disabled="! fix || fix.busy || ! fix.date" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-60">{{ __('Speichern') }}</button>
                    </div>
                </div>
            </template>
            <template x-if="canEditPeriod">
                <div class="mt-1">
                    <button type="button" x-show="ms === null" @click="newMs()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Fügt einen Meilenstein hinzu: einen Zeitpunkt, der sich an Start oder Ende des Workflows oder einer Phase orientiert.') }}">+ {{ __('Meilenstein') }}</button>
                    {{-- bewusst kein <form>: die Overlay-Behandlung des Projekts schickt jedes Formular im Overlay selbst ab (Ralf-Bug 2026-10-09: Enter/Speichern löste ein Projekt-Anlegen aus) --}}
                    <template x-if="ms !== null">
                    <div x-show="ms !== null" x-cloak @keydown.enter.prevent="saveMs()" class="rounded-md border border-gray-200 bg-gray-50 p-2">
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="block">
                                <span class="block text-[10px] text-gray-500">{{ __('Name') }}</span>
                                <input type="text" x-ref="msName" x-model="ms.name" maxlength="255" class="w-48 rounded border-gray-300 px-1.5 py-0.5 text-xs">
                            </label>
                            <label class="block">
                                <span class="block text-[10px] text-gray-500">{{ __('Bezug') }}</span>
                                <select x-model="ms.anchor_type" class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                    <option value="workflow_start">{{ __('Workflow-Start') }}</option>
                                    <option value="workflow_end">{{ __('Workflow-Ende') }}</option>
                                    <option value="step_start">{{ __('Start einer Phase') }}</option>
                                    <option value="step_end">{{ __('Ende einer Phase') }}</option>
                                    <option value="fixed">{{ __('festes Datum') }}</option>
                                </select>
                            </label>
                            <label class="block" x-show="['step_start', 'step_end'].includes(ms.anchor_type)">
                                <span class="block text-[10px] text-gray-500">{{ __('Phase') }}</span>
                                <select x-model.number="ms.anchor_workflow_step_id" class="max-w-[14rem] rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                    <template x-for="step in steps" :key="'ms-step-' + step.id">
                                        <option :value="step.id" x-text="step.title"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="block" x-show="ms.anchor_type !== 'fixed'">
                                <span class="block text-[10px] text-gray-500" title="{{ __('Arbeitstage nach dem Bezugspunkt; ein negativer Wert liegt davor.') }}">{{ __('Abstand (AT)') }}</span>
                                <input type="number" x-model.number="ms.offset_days" min="-3650" max="3650" class="w-16 rounded border-gray-300 px-1.5 py-0.5 text-right text-xs" title="{{ __('Arbeitstage nach dem Bezugspunkt; ein negativer Wert liegt davor.') }}">
                            </label>
                            <label class="block" x-show="ms.anchor_type === 'fixed'">
                                <span class="block text-[10px] text-gray-500">{{ __('Datum') }}</span>
                                <input type="date" x-model="ms.fixed_date" class="rounded border-gray-300 px-1.5 py-0.5 text-xs">
                            </label>
                            <label class="block">
                                <span class="block text-[10px] text-gray-500" title="{{ __('Kritische Projekte meldet einen Konflikt, wenn die Prüfung nicht erfüllt ist.') }}">{{ __('Prüfung') }}</span>
                                <select x-model="ms.check_direction" class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs" title="{{ __('Kritische Projekte meldet einen Konflikt, wenn die Prüfung nicht erfüllt ist.') }}">
                                    <option value="">{{ __('keine') }}</option>
                                    <option value="target">{{ __('Ziel: Projekt soll bis dahin fertig sein') }}</option>
                                    <option value="prerequisite">{{ __('Voraussetzung: Projekt darf erst danach beginnen') }}</option>
                                </select>
                            </label>
                        </div>
                        <p x-show="msError" x-text="msError" class="mt-1 text-red-600"></p>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" x-show="ms && ms.id" @click="deleteMs()" :disabled="msSaving" class="rounded-md border border-red-200 bg-white px-2 py-0.5 font-medium text-red-700 hover:bg-red-50 disabled:opacity-50">{{ __('Löschen') }}</button>
                            <span class="flex-1"></span>
                            <button type="button" @click="ms = null" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                            <button type="button" @click="saveMs()" :disabled="msSaving" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-60">{{ __('Speichern') }}</button>
                        </div>
                    </div>
                    </template>
                </div>
            </template>
        @endif
    </div>

    <p x-show="milestones.length" class="mt-2 text-gray-500">
        <span x-show="milestones.some((m) => m.kind !== 'milestone')">
            <span class="mr-1 inline-block h-2 w-2 rotate-45 border-2 border-gray-600 bg-white align-middle"></span>{{ __('Phasenende mit Namen (Termin)') }}
        </span>
        <span x-show="milestones.some((m) => m.kind === 'milestone')" class="ml-3">
            <span class="mr-1 inline-block h-2.5 w-2.5 rotate-45 bg-indigo-600 align-middle"></span>{{ __('Meilenstein (zum Verschieben ziehen)') }}
        </span>
        @if ((int) $project->schedule_model !== 2)
            <span x-show="milestones.some((m) => m.kind !== 'milestone')" class="ml-3">
                <span class="mr-1 inline-block h-2 w-2 rotate-45 bg-red-600 align-middle"></span>{{ __('Schritt endet laut Plan nach dem Termin') }}
            </span>
        @endif
    </p>

    <div class="mt-2 flex flex-wrap items-center gap-2 text-gray-500">
        @if ((int) $project->schedule_model !== 2)
        <template x-if="canEditPeriod">
            <button type="button" onclick="window.openProjectSchedule({{ $project->id }})" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Berechnet die Termine der Schritte aus einem Fixpunkt und den Dauern und trägt sie auf Wunsch ein.') }}">{{ __('Termine berechnen …') }}</button>
        </template>
        @endif
        <span x-show="changed" x-cloak class="text-amber-700">{{ $isNewModel ? __('Nicht gespeicherte Änderungen: Dauern (und ein späterer Projektstart) werden erst mit „Speichern“ am Projekt übernommen, die Termine werden daraus neu berechnet.') : __('Nicht gespeicherte Änderungen: Dauern (und ein späterer Projektstart) werden erst mit „Speichern“ am Projekt übernommen, die Termine der Schritte bleiben unverändert.') }}</span>
        <span x-show="! canEditPeriod && changed" x-cloak class="text-gray-400">{{ __('Ihnen fehlt die Berechtigung, Termine und Dauern zu ändern.') }}</span>
        <span class="flex-1"></span>
        <button type="button" x-show="changed" x-cloak @click="reset()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Zurücksetzen') }}</button>
        <button type="button" x-show="changed && canEditPeriod" x-cloak :disabled="applying" @click="saveDurations()" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50" title="{{ $isNewModel ? __('Speichert die geänderten Dauern am Projekt. Die Termine werden daraus neu berechnet.') : __('Speichert die geänderten Dauern am Projekt. Die Termine der Schritte ändern sich dabei nicht.') }}">{{ __('Speichern') }}</button>
    </div>
</div>
