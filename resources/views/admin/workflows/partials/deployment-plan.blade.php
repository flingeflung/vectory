{{--
    Einsatzplan (Ralf, 2026-10-04): unten die Arbeitsschritte ("In Bearbeitung") als Kästchen, darüber je
    Funktionsgruppe ein Regler von/bis-Schritt. Standard ist die ganze Breite. Die Ressourcenplanung nutzt das
    später, um Stunden über den echten Einsatzzeitraum statt über das ganze Projekt zu verteilen.
    Aufklappbar; der Zustand wird je Benutzer gemerkt. Bei veröffentlichten Workflows nur lesbar.
--}}
@php
    $editable = ! $isPublished;
    $stepsForJs = $workSteps->map(fn ($step) => [
        'id' => $step->id,
        'number' => $steps->search(fn ($s) => $s->id === $step->id) + 1,
        'title' => $step->title,
        'groups' => $step->functionGroups->pluck('id')->all(),
        'shorts' => $step->functionGroups->map(fn ($g) => $g->short_name ?: $g->name)->values()->all(),
        'color' => $lifecycleColors[$step->lifecycle_status] ?? $lifecycleColors[2],
    ])->values();
@endphp
<div
    class="shrink-0 border-b border-gray-100"
    x-data="{
        open: {{ $viewState['einsatzplan'] ? 'true' : 'false' }},
        toggle() {
            this.open = ! this.open;
            fetch({{ \Illuminate\Support\Js::from(route('admin.workflows.view-state')) }}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ section: 'einsatzplan', open: this.open }),
            });
        },
    }"
>
    <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-500">
        <button type="button" @click="toggle()" class="flex items-center gap-2 text-left hover:text-gray-700" :aria-expanded="open">
            <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            {{ __('Einsatzplan') }}
        </button>
        <span class="cursor-help font-normal text-gray-400" title="{{ __('Hier legen Sie fest, in welchem Zeitraum die geplanten Stunden in der Ressourcenplanung berücksichtigt werden.') }}">ⓘ</span>
    </div>

    <div x-show="open" x-cloak class="px-3 pb-3">
        @if ($workSteps->isEmpty() || $deploymentRows->isEmpty())
            <p class="rounded-md border border-dashed border-gray-200 p-3 text-xs text-gray-400">
                {{ $workSteps->isEmpty()
                    ? __('Der Einsatzplan erscheint, sobald der Workflow Schritte „In Bearbeitung“ hat.')
                    : __('Der Einsatzplan erscheint, sobald bei den Schritten Funktionsgruppen als zuständig eingetragen sind.') }}
            </p>
        @else
            @unless ($editable)
                <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">{{ __('Der Einsatzplan ist eingefroren, weil der Workflow veröffentlicht ist. Für Änderungen bitte eine neue Version erstellen.') }}</p>
            @endunless
            <form
                method="POST"
                action="{{ route('admin.workflows.deployment-plan', $selectedWorkflow) }}"
                x-data="{
                    steps: {{ \Illuminate\Support\Js::from($stepsForJs) }},
                    rows: {{ \Illuminate\Support\Js::from($deploymentRows) }},
                    initial: {{ \Illuminate\Support\Js::from($deploymentRows->map(fn ($r) => [$r['from'], $r['to']])) }},
                    editable: {{ $editable ? 'true' : 'false' }},
                    drag: null,
                    get dirty() { return this.rows.some((row, i) => row.from !== this.initial[i][0] || row.to !== this.initial[i][1]); },
                    cellAt(event, track) {
                        const rect = track.getBoundingClientRect();
                        const index = Math.floor(((event.clientX - rect.left) / rect.width) * this.steps.length) + 1;
                        return Math.min(this.steps.length, Math.max(1, index));
                    },
                    start(event, row, edge, track) {
                        if (! this.editable) return;
                        this.drag = { row, edge, track };
                        event.target.setPointerCapture?.(event.pointerId);
                    },
                    move(event) {
                        if (! this.drag) return;
                        const cell = this.cellAt(event, this.drag.track);
                        if (this.drag.edge === 'from') this.drag.row.from = Math.min(cell, this.drag.row.to);
                        else this.drag.row.to = Math.max(cell, this.drag.row.from);
                    },
                    end() { this.drag = null; },
                    pick(event, row, track) {
                        if (! this.editable) return;
                        const cell = this.cellAt(event, track);
                        if (cell < row.from) row.from = cell;
                        else if (cell > row.to) row.to = cell;
                        else if (cell - row.from <= row.to - cell) row.from = cell;
                        else row.to = cell;
                    },
                    full(row) { row.from = 1; row.to = this.steps.length; },
                    fromResponsibilities() {
                        this.rows.forEach((row) => {
                            const positions = this.steps.map((step, i) => step.groups.includes(row.id) ? i + 1 : null).filter(Boolean);
                            if (positions.length) { row.from = Math.min(...positions); row.to = Math.max(...positions); }
                        });
                    },
                    allFull() { this.rows.forEach((row) => this.full(row)); },
                    label(row) { return row.from === row.to ? 'S' + this.steps[row.from - 1].number : 'S' + this.steps[row.from - 1].number + ' – S' + this.steps[row.to - 1].number; },
                }"
                x-init="$watch('rows', () => { const open = window.__workflowsDirtyForms; if (open) { dirty ? open.add($el) : open.delete($el); } })"
                @pointermove.window="move($event)"
                @pointerup.window="end()"
                @submit="window.__workflowsDirtyForms?.delete($el)"
            >
                @csrf
                <div class="overflow-x-auto">
                    <div class="min-w-[32rem]">
                        <template x-for="row in rows" :key="row.id">
                            <div class="flex items-center gap-2 py-0.5">
                                <div class="w-36 shrink-0 truncate text-xs text-gray-700" x-text="row.name" :title="row.name"></div>
                                <div
                                    class="relative grid h-5 flex-1 items-center rounded bg-gray-100"
                                    :style="'grid-template-columns: repeat(' + steps.length + ', minmax(0, 1fr))'"
                                    x-ref="track"
                                    @click="pick($event, row, $el)"
                                    @dblclick="full(row)"
                                    title="{{ $editable ? __('Enden ziehen oder in die Leiste klicken. Doppelklick = ganze Breite.') : __('Eingefroren, weil der Workflow veröffentlicht ist.') }}"
                                >
                                    <div class="relative h-4 rounded-sm bg-sky-500" :style="'grid-column: ' + row.from + ' / ' + (row.to + 1)" :class="editable ? '' : 'opacity-70'" @click.stop>
                                        <span x-show="editable" class="absolute inset-y-0 left-0 w-3 cursor-ew-resize rounded-l-sm bg-sky-800/70 hover:bg-sky-900" @pointerdown.stop.prevent="start($event, row, 'from', $el.closest('.grid'))"></span>
                                        <span x-show="editable" class="absolute inset-y-0 right-0 w-3 cursor-ew-resize rounded-r-sm bg-sky-800/70 hover:bg-sky-900" @pointerdown.stop.prevent="start($event, row, 'to', $el.closest('.grid'))"></span>
                                    </div>
                                </div>
                                <div class="w-20 shrink-0 text-right text-xs tabular-nums text-gray-400" x-text="label(row)"></div>
                                <input type="hidden" :name="'windows[' + row.id + '][from]'" :value="steps[row.from - 1].id">
                                <input type="hidden" :name="'windows[' + row.id + '][to]'" :value="steps[row.to - 1].id">
                            </div>
                        </template>

                        <div class="flex items-start gap-2 pt-1">
                            <div class="w-36 shrink-0"></div>
                            <div class="grid flex-1 gap-0.5" :style="'grid-template-columns: repeat(' + steps.length + ', minmax(0, 1fr))'">
                                <template x-for="step in steps" :key="step.id">
                                    <div class="min-w-0">
                                        <div class="rounded-md border border-green-600/40 px-1 py-1 text-center" :style="'background-color: ' + step.color">
                                            <div class="truncate text-[11px] font-medium leading-tight text-gray-800" x-text="step.title" :title="step.title"></div>
                                            <div class="text-[10px] text-gray-500" x-text="'S' + step.number"></div>
                                        </div>
                                        <div class="mt-0.5 text-center text-[11px] font-medium leading-tight text-gray-700">
                                            <template x-for="short in step.shorts" :key="short"><div class="truncate" x-text="short" :title="short"></div></template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="w-20 shrink-0"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-400">{{ __('Näherung: Die Dauer der einzelnen Schritte steht nicht fest, deshalb dient der Plan nur als grobe Verteilung.') }}</p>
                    @if ($editable)
                        <div class="flex items-center gap-2">
                            <button type="button" @click="fromResponsibilities()" title="{{ __('Setzt jede Funktionsgruppe von ihrem ersten bis zu ihrem letzten Schritt, bei dem sie als zuständig eingetragen ist.') }}" class="whitespace-nowrap rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Vorbelegen') }}</button>
                            <button type="button" @click="allFull()" class="whitespace-nowrap rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Ganze Breite') }}</button>
                            <button type="submit" x-show="dirty" x-cloak class="whitespace-nowrap rounded-md bg-btn-primary px-3 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </div>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>
