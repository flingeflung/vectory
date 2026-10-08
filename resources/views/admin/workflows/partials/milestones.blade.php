{{--
    Meilensteine der Workflow-Vorlage (Ralf, 2026-10-09; docs/ablaufplan-konzept.md): frei gesetzte Zeitpunkte ohne eigene Phase, bezogen auf
    Start oder Ende des Workflows oder einer Phase, mit Abstand in Arbeitstagen. Jedes Projekt auf diesem Workflow bekommt sie als Kopie.
    Aufklappbar (Zustand je Benutzer gemerkt); bei veröffentlichten Workflows nur lesbar. Absichtlich keine verschachtelten Formulare:
    das Bearbeiten-Formular ist ein eigenes <form>, Löschen läuft über ein verstecktes Formular.
--}}
@php
    $editable = ! $isPublished;
    $phases = $workSteps->map(fn ($step) => ['id' => $step->id, 'title' => $step->title])->values();
    $rule = function ($row) use ($workSteps) {
        $offset = (int) $row->offset_days;
        $suffix = $offset === 0 ? '' : ' '.($offset > 0 ? '+' : '−').abs($offset).' '.__('AT');
        $title = $workSteps->firstWhere('id', $row->anchor_workflow_step_id)?->title ?? '';

        return match ($row->anchor_type) {
            'workflow_start' => __('Workflow-Start').$suffix,
            'workflow_end' => __('Workflow-Ende').$suffix,
            'step_start' => __('Start von :step', ['step' => $title]).$suffix,
            'step_end' => __('Ende von :step', ['step' => $title]).$suffix,
            default => '',
        };
    };
@endphp
<div
    class="shrink-0 border-b border-gray-100"
    x-data="{
        open: {{ ($viewState['meilensteine'] ?? true) ? 'true' : 'false' }},
        form: null,
        phases: {{ \Illuminate\Support\Js::from($phases) }},
        toggle() {
            this.open = ! this.open;
            fetch({{ \Illuminate\Support\Js::from(route('admin.workflows.view-state')) }}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ section: 'meilensteine', open: this.open }),
            });
        },
        add() {
            this.form = { id: null, name: '', anchor_type: 'workflow_end', anchor_workflow_step_id: this.phases.length ? this.phases[this.phases.length - 1].id : '', offset_days: 0, is_market_launch: false };
            this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
        },
        edit(row) {
            this.form = { id: row.id, name: row.name, anchor_type: row.anchor_type, anchor_workflow_step_id: row.anchor_workflow_step_id || (this.phases.length ? this.phases[0].id : ''), offset_days: row.offset_days, is_market_launch: row.is_market_launch };
            this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
        },
        get isStep() { return this.form && ['step_start', 'step_end'].includes(this.form.anchor_type); },
    }"
>
    <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-500">
        <button type="button" @click="toggle()" class="flex items-center gap-2 text-left hover:text-gray-700" :aria-expanded="open">
            <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            {{ __('Meilensteine') }}
            <span class="font-normal text-gray-400">({{ $milestones->count() }})</span>
        </button>
        <span class="cursor-help font-normal text-gray-400" title="{{ __('Ein Meilenstein ist ein Zeitpunkt ohne eigene Phase, z. B. die Markteinführung. Er richtet sich nach Start oder Ende des Workflows oder einer Phase und wandert mit, wenn sich der Plan verschiebt.') }}">ⓘ</span>
    </div>

    <div x-show="open" x-cloak class="px-3 pb-3">
        @unless ($editable)
            <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">{{ __('Die Meilensteine sind eingefroren, weil der Workflow veröffentlicht ist. Für Änderungen eine neue Version erstellen.') }}</p>
        @endunless

        @if ($milestones->isEmpty())
            <p class="rounded-md border border-dashed border-gray-200 p-3 text-xs text-gray-400">{{ __('Noch keine Meilensteine.') }}</p>
        @else
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-[10px] font-medium uppercase tracking-wide text-gray-400">
                        <th class="py-1 pr-2">{{ __('Name') }}</th>
                        <th class="py-1 pr-2">{{ __('Bezug') }}</th>
                        <th class="py-1 pr-2">{{ __('Markteinführung') }}</th>
                        <th class="w-16 py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($milestones as $row)
                        <tr class="border-t border-gray-100">
                            <td class="py-1 pr-2 text-gray-700"><span class="mr-1 inline-block h-2 w-2 rotate-45 bg-gray-600 align-middle"></span>{{ $row->name }}</td>
                            <td class="py-1 pr-2 text-gray-600">{{ $rule($row) }}</td>
                            <td class="py-1 pr-2 text-gray-600">{{ $row->is_market_launch ? __('ja') : '' }}</td>
                            <td class="py-1 text-right">
                                @if ($editable)
                                    <button type="button" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Meilenstein ändern') }}"
                                        @click="edit({{ \Illuminate\Support\Js::from(['id' => $row->id, 'name' => $row->name, 'anchor_type' => $row->anchor_type, 'anchor_workflow_step_id' => $row->anchor_workflow_step_id, 'offset_days' => $row->offset_days, 'is_market_launch' => (bool) $row->is_market_launch]) }})">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($editable)
            <div class="mt-2">
                <button type="button" x-show="form === null" @click="add()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">+ {{ __('Meilenstein') }}</button>

                <form
                    x-show="form !== null" x-cloak
                    method="POST"
                    :action="form && form.id ? {{ \Illuminate\Support\Js::from(route('admin.workflows.milestones.store', $selectedWorkflow)) }} + '/' + form.id : {{ \Illuminate\Support\Js::from(route('admin.workflows.milestones.store', $selectedWorkflow)) }}"
                    class="rounded-md border border-gray-200 bg-gray-50 p-2 text-xs"
                >
                    @csrf
                    <template x-if="form && form.id"><input type="hidden" name="_method" value="PATCH"></template>
                    <div class="flex flex-wrap items-end gap-2" x-show="form !== null">
                        <label class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('Name') }}</span>
                            <input type="text" name="name" x-ref="name" :value="form ? form.name : ''" @input="form.name = $event.target.value" maxlength="255" required class="w-48 rounded border-gray-300 px-1.5 py-0.5 text-xs">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('Bezug') }}</span>
                            <select name="anchor_type" x-model="form.anchor_type" class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                <option value="workflow_start">{{ __('Workflow-Start') }}</option>
                                <option value="workflow_end">{{ __('Workflow-Ende') }}</option>
                                <option value="step_start">{{ __('Start einer Phase') }}</option>
                                <option value="step_end">{{ __('Ende einer Phase') }}</option>
                            </select>
                        </label>
                        <label class="block" x-show="isStep">
                            <span class="block text-[10px] text-gray-500">{{ __('Phase') }}</span>
                            <select name="anchor_workflow_step_id" x-model.number="form.anchor_workflow_step_id" :disabled="! isStep" class="max-w-[14rem] rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                <template x-for="phase in phases" :key="'ms-phase-' + phase.id">
                                    <option :value="phase.id" x-text="phase.title"></option>
                                </template>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] text-gray-500" title="{{ __('Arbeitstage nach dem Bezugspunkt; ein negativer Wert liegt davor.') }}">{{ __('Abstand (AT)') }}</span>
                            <input type="number" name="offset_days" x-model.number="form.offset_days" min="-3650" max="3650" class="w-16 rounded border-gray-300 px-1.5 py-0.5 text-right text-xs" title="{{ __('Arbeitstage nach dem Bezugspunkt; ein negativer Wert liegt davor.') }}">
                        </label>
                        <label class="flex items-center gap-1.5 pb-1 text-gray-700">
                            <input type="hidden" name="is_market_launch" value="0">
                            <input type="checkbox" name="is_market_launch" value="1" :checked="form && form.is_market_launch" @change="form.is_market_launch = $event.target.checked" class="rounded border-gray-300">
                            {{ __('Markteinführung') }}
                        </label>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" x-show="form && form.id" class="rounded-md border border-red-200 bg-white px-2 py-0.5 font-medium text-red-700 hover:bg-red-50"
                            @click="window.deleteWithConfirm($refs.deleteForm, {
                                title: {{ \Illuminate\Support\Js::from(__('Meilenstein löschen?')) }},
                                message: {{ \Illuminate\Support\Js::from(__('Der Meilenstein wird aus dem Workflow entfernt.')) }},
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }},
                            })">{{ __('Löschen') }}</button>
                        <span class="flex-1"></span>
                        <button type="button" @click="form = null" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="submit" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    </div>
                </form>
                <form x-ref="deleteForm" method="POST" :action="form && form.id ? {{ \Illuminate\Support\Js::from(route('admin.workflows.milestones.store', $selectedWorkflow)) }} + '/' + form.id : '#'" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        @endif
    </div>
</div>
