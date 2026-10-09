{{--
    Unterreiter "Terminplan" im Tab "Planung" (Ralf, 2026-10-03): Jahresansicht der Projektfamilie (Hauptprojekt + Unterprojekte)
    mit Zeitraum-Balken und Meilensteinen, "die Gantt-Ansicht aus der Planung im Kleinen". Rein
    lesend und nicht klickbar. Daten: App\Support\ProjectFamilyTimeline. Die Darstellung läuft im
    Browser, damit der Jahreswechsel ohne Nachladen geht.
    ACHTUNG x-data: keine geraden Anführungszeichen im JavaScript (nur einfache).
--}}
@php
    $timelineRows = \App\Support\ProjectFamilyTimeline::for($project)->values();
    $timelineYears = $timelineRows->flatMap(fn ($row) => collect([$row['start'], $row['end'], ...collect($row['milestones'])->pluck('date')->all()])->filter()->map(fn ($date) => (int) substr($date, 0, 4)))->unique()->sort()->values();
    $timelineCurrentYear = (int) now()->year;
    $timelineInitialYear = $timelineYears->isEmpty() || $timelineYears->contains($timelineCurrentYear) ? $timelineCurrentYear : $timelineYears->first();
    // Blättern nur bis ein Jahr vor bzw. nach den Terminen der angezeigten Projekte.
    $timelineMinYear = ($timelineYears->isEmpty() ? $timelineCurrentYear : $timelineYears->first()) - 1;
    $timelineMaxYear = ($timelineYears->isEmpty() ? $timelineCurrentYear : $timelineYears->last()) + 1;
    // Termine im Verbund verschieben (Ralf, 2026-10-09): nur am Hauptprojekt und mit dem Recht für Termine der Workflow-Schritte
    $canShiftVerbund = (int) $project->verbund_rolle === 1 && auth()->user()->can('workflow_step.due_date');
    $shiftMembers = $canShiftVerbund
        ? app(\App\Services\VerbundScheduleShifter::class)->members($project)->filter(fn ($member) => $member->start_date !== null)
            ->map(fn ($member) => ['id' => $member->id, 'label' => $member->source_pn.' '.$member->title, 'start' => $member->start_date->format('Y-m-d')])->values()->all()
        : [];
@endphp

<div
    class="text-sm"
    x-data="{
        year: {{ $timelineInitialYear }},
        minYear: {{ $timelineMinYear }},
        maxYear: {{ $timelineMaxYear }},
        rows: {{ \Illuminate\Support\Js::from($timelineRows) }},
        years: {{ \Illuminate\Support\Js::from($timelineYears) }},
        monthNames: {{ \Illuminate\Support\Js::from(collect(range(1, 12))->map(fn ($m) => \Carbon\CarbonImmutable::create(2026, $m, 1)->translatedFormat('M'))->all()) }},
        today: {{ \Illuminate\Support\Js::from(now()->format('Y-m-d')) }},
        yearStart() { return Date.UTC(this.year, 0, 1); },
        daysInYear() { return (Date.UTC(this.year + 1, 0, 1) - this.yearStart()) / 86400000; },
        dayOf(iso) { const p = iso.split('-'); return (Date.UTC(+p[0], +p[1] - 1, +p[2]) - this.yearStart()) / 86400000; },
        pct(iso) { return Math.min(100, Math.max(0, this.dayOf(iso) / this.daysInYear() * 100)); },
        inYear(iso) { return iso.slice(0, 4) === String(this.year); },
        barStyle(row) {
            if (! row.start || ! row.end) return null;
            const from = this.dayOf(row.start), to = this.dayOf(row.end) + 1;
            if (to < 0 || from > this.daysInYear()) return null;
            const left = Math.max(0, from) / this.daysInYear() * 100;
            const right = Math.min(this.daysInYear(), to) / this.daysInYear() * 100;
            return { left: left + '%', width: Math.max(0.4, right - left) + '%', before: from < 0, after: to > this.daysInYear() };
        },
        monthStarts() { return Array.from({ length: 12 }, (_, m) => { const from = (Date.UTC(this.year, m, 1) - this.yearStart()) / 86400000, to = (Date.UTC(this.year, m + 1, 1) - this.yearStart()) / 86400000; return { name: this.monthNames[m], left: from / this.daysInYear() * 100, width: (to - from) / this.daysInYear() * 100 }; }); },
        fmt(iso) { const p = iso.split('-'); return p[2] + '.' + p[1] + '.' + p[0]; },
    }"
>
    @if ($timelineRows->isEmpty())
        <p class="text-gray-400">{{ __('Keine Termine vorhanden.') }}</p>
    @else
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs text-gray-500">
                @if ($timelineRows->count() > 1)
                    {{ __('Hauptprojekt und Unterprojekte im Überblick') }}
                @else
                    {{ __('Zeitraum und Meilensteine dieses Projekts') }}
                @endif
            </p>
            <div class="flex items-center gap-1 text-xs">
                @if ($canShiftVerbund)
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('verbund-shift-toggle'))" class="mr-2 inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Verschiebt die Termine aller Projekte dieses Verbunds um dieselbe Anzahl Arbeitstage.') }}">{{ __('Termine im Verbund verschieben') }}</button>
                @endif
                <button type="button" @click="year--" :disabled="year <= minYear" class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-40" aria-label="{{ __('Vorheriges Jahr') }}">&lsaquo;</button>
                <span class="w-12 text-center font-semibold tabular-nums text-gray-800" x-text="year"></span>
                <button type="button" @click="year++" :disabled="year >= maxYear" class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-40" aria-label="{{ __('Nächstes Jahr') }}">&rsaquo;</button>
            </div>
        </div>

        @if ($canShiftVerbund)
            <div
                x-data="{
                    open: false, mode: 'days', amount: 1, unit: 'at', direction: 'later', projectId: '', date: '',
                    members: {{ \Illuminate\Support\Js::from($shiftMembers) }},
                    preview: null, error: '', busy: false, timer: null,
                    fmt(iso) { if (! iso) return '–'; const p = iso.split('-'); return p[2] + '.' + p[1] + '.' + p[0]; },
                    ready() { return this.mode === 'days' ? Number(this.amount) > 0 : (this.projectId !== '' && this.date !== ''); },
                    queue() { clearTimeout(this.timer); this.preview = null; this.error = ''; if (! this.ready()) return; this.timer = setTimeout(() => this.run(true), 350); },
                    pickProject() { const member = this.members.find((m) => String(m.id) === String(this.projectId)); this.date = member ? member.start : ''; this.queue(); },
                    async run(preview) {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            const body = { mode: this.mode, preview: preview ? 1 : 0 };
                            if (this.mode === 'days') { body.amount = Number(this.amount); body.unit = this.unit; body.direction = this.direction; } else { body.project_id = this.projectId; body.date = this.date; }
                            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.verbund-verschieben', $project)) }}, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} }, body: JSON.stringify(body) });
                            const data = await response.json();
                            if (! response.ok) { this.preview = null; this.error = (data.errors ? Object.values(data.errors)[0][0] : data.message) || {{ \Illuminate\Support\Js::from(__('Das hat nicht geklappt.')) }}; return; }
                            if (preview) { this.preview = data; return; }
                            @if ($isOverlay ?? false)
                            window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $project->id }} } }));
                            @else
                            window.location.reload();
                            @endif
                        } finally { this.busy = false; }
                    },
                }"
                @verbund-shift-toggle.window="open = ! open; if (open) $nextTick(() => setTimeout(() => $refs.amount && $refs.amount.focus(), 30))"
                x-show="open" x-cloak
                class="mb-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700"
            >
                <div class="mb-2 text-xs font-semibold text-gray-700">{{ __('Termine im Verbund verschieben') }}</div>
                <p class="mb-2 text-gray-500">{{ __('Alle Projekte des Verbunds wandern um dieselbe Anzahl Arbeitstage. Jedes Projekt rechnet danach selbst neu, Erinnerungsmails folgen den neuen Terminen.') }}</p>
                <div class="space-y-2">
                    <label class="flex flex-wrap items-center gap-2">
                        <input type="radio" value="days" x-model="mode" @change="queue()">
                        <span>{{ __('Um') }}</span>
                        <input type="number" min="1" max="1000" x-ref="amount" x-model.number="amount" @input="queue()" @focus="mode = 'days'; queue()" class="h-7 w-16 rounded border-gray-300 px-1.5 py-0 text-right text-xs">
                        <select x-model="unit" @change="mode = 'days'; queue()" class="h-7 rounded border-gray-300 py-0 text-xs">
                            <option value="at">{{ __('Arbeitstage') }}</option>
                            <option value="weeks">{{ __('Wochen (je 5 Arbeitstage)') }}</option>
                        </select>
                        <select x-model="direction" @change="mode = 'days'; queue()" class="h-7 rounded border-gray-300 py-0 text-xs">
                            <option value="later">{{ __('später') }}</option>
                            <option value="earlier">{{ __('früher') }}</option>
                        </select>
                    </label>
                    <label class="flex flex-wrap items-center gap-2">
                        <input type="radio" value="project" x-model="mode" @change="queue()">
                        <span>{{ __('Start von') }}</span>
                        <select x-model="projectId" @change="mode = 'project'; pickProject()" class="h-7 max-w-[18rem] rounded border-gray-300 py-0 text-xs">
                            <option value="">{{ __('– Projekt wählen –') }}</option>
                            <template x-for="member in members" :key="member.id"><option :value="member.id" x-text="member.label"></option></template>
                        </select>
                        <span>{{ __('auf') }}</span>
                        <input type="date" x-model="date" @input="mode = 'project'; queue()" class="h-7 rounded border-gray-300 px-1.5 py-0 text-xs">
                    </label>
                </div>

                <p x-show="error" x-text="error" class="mt-2 text-red-600"></p>

                <template x-if="preview">
                    <div class="mt-3 space-y-2">
                        <p class="font-medium text-gray-800" x-text="preview.delta === 0 ? {{ \Illuminate\Support\Js::from(__('Keine Verschiebung.')) }} : {{ \Illuminate\Support\Js::from(__('Verschiebung')) }} + ': ' + (preview.delta > 0 ? '+' : '') + preview.delta + ' ' + {{ \Illuminate\Support\Js::from(__('Arbeitstage')) }}"></p>
                        <table class="w-full text-left">
                            <thead class="text-[10px] uppercase tracking-wide text-gray-400"><tr><th class="py-1 pr-2">{{ __('Projekt') }}</th><th class="py-1 pr-2">{{ __('Start') }}</th><th class="py-1 pr-2">{{ __('Ende') }}</th><th class="py-1"></th></tr></thead>
                            <tbody>
                                <template x-for="row in preview.projects" :key="row.id">
                                    <tr class="border-t border-gray-200 align-top" :class="row.reason ? 'text-gray-400' : ''">
                                        <td class="py-1 pr-2"><span class="font-semibold" x-text="row.pn"></span> <span x-show="row.isMain" class="rounded bg-slate-100 px-1 text-[10px] text-slate-600">HP</span> <span class="block max-w-[14rem] truncate" x-text="row.title"></span></td>
                                        <td class="whitespace-nowrap py-1 pr-2"><span x-text="fmt(row.startOld)"></span><template x-if="row.startNew"><span> → <b x-text="fmt(row.startNew)"></b></span></template></td>
                                        <td class="whitespace-nowrap py-1 pr-2"><span x-text="fmt(row.endOld)"></span><template x-if="row.endNew"><span> → <b x-text="fmt(row.endNew)"></b></span></template></td>
                                        <td class="py-1" x-text="row.reason || ''"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="preview.fixedMilestones.length > 0" class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-amber-800">
                            {{ __('Meilensteine mit festem Datum bleiben, wo sie sind:') }}
                            <template x-for="(milestone, index) in preview.fixedMilestones" :key="index"><span><span x-text="milestone.pn + ' ' + milestone.name + ' (' + milestone.date + ')'"></span><span x-show="index < preview.fixedMilestones.length - 1">, </span></span></template>
                        </p>
                    </div>
                </template>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                    <button type="button" x-show="preview && preview.delta !== 0 && preview.movable > 0" x-cloak :disabled="busy" @click="run(false)" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:opacity-50">{{ __('Speichern') }}</button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <div class="min-w-[640px]">
                {{-- Monatskopf --}}
                <div class="grid grid-cols-[13rem_minmax(0,1fr)] border-b border-gray-200 bg-gray-50 text-[11px] text-gray-500">
                    <div class="px-2 py-1">{{ __('Projekt') }}</div>
                    <div class="relative h-6">
                        <template x-for="month in monthStarts()" :key="month.name">
                            <div class="absolute inset-y-0 flex items-center justify-center border-l border-gray-200" :style="'left:' + month.left + '%;width:' + month.width + '%'" x-text="month.name"></div>
                        </template>
                    </div>
                </div>

                @foreach ($timelineRows as $index => $row)
                    <div class="grid grid-cols-[13rem_minmax(0,1fr)] items-center border-b border-gray-100 last:border-b-0 {{ $row['isCurrent'] ? 'bg-indigo-50/40' : '' }}">
                        <div class="min-w-0 px-2 py-1.5">
                            <div class="flex items-center gap-1">
                                <span class="shrink-0 text-xs font-semibold {{ $row['isCurrent'] ? 'text-indigo-700' : 'text-gray-800' }}">{{ $row['pn'] }}</span>
                                @if ($row['isMain'])
                                    <span class="shrink-0 rounded bg-slate-100 px-1 text-[10px] text-slate-600" title="{{ __('Hauptprojekt') }}">HP</span>
                                @endif
                            </div>
                            <div class="truncate text-[11px] text-gray-500" title="{{ $row['title'] }}">{{ $row['title'] }}</div>
                        </div>
                        <div class="relative h-9 border-l-2 border-gray-300" x-data="{ r: rows[{{ $index }}] }">
                            {{-- Monatslinien --}}
                            <template x-for="month in monthStarts()" :key="month.name">
                                <div x-show="month.left > 0" class="absolute inset-y-0 border-l border-gray-100" :style="'left:' + month.left + '%'"></div>
                            </template>
                            {{-- Heute --}}
                            <div x-show="inYear(today)" class="absolute inset-y-0 w-px bg-red-400" :style="'left:' + pct(today) + '%'" title="{{ __('Heute') }}"></div>
                            {{-- Zeitraum --}}
                            <template x-if="barStyle(r)">
                                <div
                                    class="absolute top-1/2 flex h-3 -translate-y-1/2 items-center rounded-sm border border-black/10 {{ $row['isMain'] ? 'bg-slate-300' : 'bg-indigo-200' }}"
                                    :style="'left:' + barStyle(r).left + ';width:' + barStyle(r).width"
                                    :title="fmt(r.start) + ' - ' + fmt(r.end)"
                                >
                                    <span x-show="barStyle(r).before" class="absolute left-0 text-[11px] font-bold leading-none text-gray-700">&lsaquo;</span>
                                    <span x-show="barStyle(r).after" class="absolute right-0 text-[11px] font-bold leading-none text-gray-700">&rsaquo;</span>
                                </div>
                            </template>
                            {{-- Meilensteine --}}
                            <template x-for="(milestone, mIndex) in r.milestones.filter((m) => inYear(m.date))" :key="mIndex">
                                <span
                                    class="absolute top-1/2 z-[1] block h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rotate-45 border border-white bg-fuchsia-600 shadow-sm"
                                    :style="'left:' + pct(milestone.date) + '%'"
                                    :title="milestone.title + ' (' + fmt(milestone.date) + ')'"
                                ></span>
                            </template>
                            <div x-show="! barStyle(r) && r.milestones.filter((m) => inYear(m.date)).length === 0" class="absolute inset-0 flex items-center pl-2 text-[11px] text-gray-300">
                                <span x-text="(r.start && r.end) ? '' : {{ \Illuminate\Support\Js::from(__('Zeitraum unvollständig')) }}"></span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rotate-45 border border-white bg-fuchsia-600 shadow-sm"></span>{{ __('Meilenstein') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-px w-3 bg-red-400"></span>{{ __('Heute') }}</span>
            <span>&lsaquo; &rsaquo; {{ __('Projekt beginnt vor bzw. läuft nach dem angezeigten Jahr') }}</span>
        </div>
    @endif
</div>
