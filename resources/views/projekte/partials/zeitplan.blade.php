{{--
    Tab "Zeitplan" (Ralf, 2026-10-03): Jahresansicht der Projektfamilie (Hauptprojekt + Unterprojekte)
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
@endphp

<div
    class="text-sm"
    x-data="{
        year: {{ $timelineInitialYear }},
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
        monthStarts() { return Array.from({ length: 12 }, (_, m) => ({ name: this.monthNames[m], left: (Date.UTC(this.year, m, 1) - this.yearStart()) / 86400000 / this.daysInYear() * 100 })); },
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
                <button type="button" @click="year--" class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 hover:bg-btn-secondary-hover" aria-label="{{ __('Vorheriges Jahr') }}">&lsaquo;</button>
                <span class="w-12 text-center font-semibold tabular-nums text-gray-800" x-text="year"></span>
                <button type="button" @click="year++" class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 hover:bg-btn-secondary-hover" aria-label="{{ __('Nächstes Jahr') }}">&rsaquo;</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <div class="min-w-[640px]">
                {{-- Monatskopf --}}
                <div class="grid grid-cols-[13rem_minmax(0,1fr)] border-b border-gray-200 bg-gray-50 text-[11px] text-gray-500">
                    <div class="px-2 py-1">{{ __('Projekt') }}</div>
                    <div class="relative h-6">
                        <template x-for="month in monthStarts()" :key="month.name">
                            <div class="absolute inset-y-0 border-l border-gray-200 pl-1 pt-1" :style="'left:' + month.left + '%'" x-text="month.name"></div>
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
                        <div class="relative h-9" x-data="{ r: rows[{{ $index }}] }">
                            {{-- Monatslinien --}}
                            <template x-for="month in monthStarts()" :key="month.name">
                                <div class="absolute inset-y-0 border-l border-gray-100" :style="'left:' + month.left + '%'"></div>
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
