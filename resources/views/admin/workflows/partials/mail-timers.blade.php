{{--
    Standard-Erinnerungen (Mail-Timer-Vorlagen) am Workflow (Ralf, 2026-10-09): je Schritt eine Erinnerungsmail mit Mail-Vorlage, Bezugstermin
    (Meilenstein oder benanntes Phasenende des Workflows), Abstand in Tagen und Empfänger-Funktionsgruppen. Jedes Projekt auf diesem Workflow
    bekommt sie als Kopie und kann sie ändern. Betreff und Text gibt es nur in den Mail-Vorlagen. Aufklappbar; bei veröffentlichten
    Workflows nur lesbar. Absichtlich keine verschachtelten Formulare (Bearbeiten-Formular + verstecktes Löschen-Formular).
--}}
@php
    $editable = ! $isPublished;
    $namedSteps = $steps->filter(fn ($step) => trim((string) $step->milestone_title) !== '');
    $stepTitles = $steps->pluck('title', 'id');
    $templateNames = $mailTemplates->pluck('name', 'id');
    $groupNames = $functionGroups->pluck('name', 'id');
    $ruleText = function ($row) use ($milestones, $stepTitles) {
        $reference = $row->reference_type === 'milestone'
            ? __('Meilenstein „:name“', ['name' => $milestones->firstWhere('id', $row->reference_milestone_id)?->name ?? '?'])
            : __('Ende von „:name“', ['name' => $stepTitles[$row->reference_step_id] ?? '?']);
        $days = abs((int) $row->offset_days);
        if ($days === 0) {
            return __('am Tag von :reference', ['reference' => $reference]);
        }
        $amount = $days % 7 === 0 ? ($days / 7).' '.($days === 7 ? __('Woche') : __('Wochen')) : $days.' '.($days === 1 ? __('Tag') : __('Tage'));

        return $row->offset_days < 0 ? __(':amount vor :reference', ['amount' => $amount, 'reference' => $reference]) : __(':amount nach :reference', ['amount' => $amount, 'reference' => $reference]);
    };
@endphp
<div
    class="shrink-0 border-b border-gray-100"
    x-data="{
        open: {{ ($viewState['erinnerungen'] ?? true) ? 'true' : 'false' }},
        form: null,
        base: {{ \Illuminate\Support\Js::from(route('admin.workflows.mailtimers.store', $selectedWorkflow)) }},
        toggle() {
            this.open = ! this.open;
            fetch({{ \Illuminate\Support\Js::from(route('admin.workflows.view-state')) }}, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ section: 'erinnerungen', open: this.open }),
            });
        },
        blank() {
            return { id: null, workflow_step_id: {{ \Illuminate\Support\Js::from($workSteps->last()?->id ?? $steps->first()?->id) }}, mail_template_id: '', reference: '', unit: 7, amount: 7, direction: -1, function_group_ids: [], only_if_in_step: true };
        },
        add() { this.form = this.blank(); this.$nextTick(() => this.$refs.template && this.$refs.template.focus()); },
        edit(row) {
            const days = Math.abs(row.offset_days);
            this.form = { id: row.id, workflow_step_id: row.workflow_step_id, mail_template_id: row.mail_template_id, reference: row.reference, unit: days > 0 && days % 7 === 0 ? 7 : 1, amount: days > 0 && days % 7 === 0 ? days / 7 : days, direction: row.offset_days > 0 ? 1 : -1, function_group_ids: row.function_group_ids, only_if_in_step: row.only_if_in_step };
            this.$nextTick(() => this.$refs.template && this.$refs.template.focus());
        },
        get offset() { return this.form ? this.form.direction * Math.abs(Number(this.form.amount || 0)) * Number(this.form.unit) : 0; },
    }"
>
    <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-500">
        <button type="button" @click="toggle()" class="flex items-center gap-2 text-left hover:text-gray-700" :aria-expanded="open">
            <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            {{ __('Erinnerungen per Mail') }}
            <span class="font-normal text-gray-400">({{ $mailTimers->count() }})</span>
        </button>
        <span class="cursor-help font-normal text-gray-400" title="{{ __('Eine Erinnerungsmail wird für jedes Projekt auf diesem Workflow angelegt und zum errechneten Datum automatisch an die gewählten Funktionsgruppen des Projekts gesendet. Betreff und Text stammen aus einer Mail-Vorlage.') }}">ⓘ</span>
    </div>

    <div x-show="open" x-cloak class="px-3 pb-3">
        @unless ($editable)
            <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">{{ __('Die Erinnerungen sind eingefroren, weil der Workflow veröffentlicht ist. Für Änderungen eine neue Version erstellen.') }}</p>
        @endunless

        @if ($mailTimers->isEmpty())
            <p class="rounded-md border border-dashed border-gray-200 p-3 text-xs text-gray-400">{{ __('Noch keine Erinnerungen.') }}</p>
        @else
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-[10px] font-medium uppercase tracking-wide text-gray-400">
                        <th class="py-1 pr-2">{{ __('Schritt') }}</th>
                        <th class="py-1 pr-2">{{ __('Mail-Vorlage') }}</th>
                        <th class="py-1 pr-2">{{ __('Zeitpunkt') }}</th>
                        <th class="py-1 pr-2">{{ __('Empfänger') }}</th>
                        <th class="w-16 py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mailTimers as $row)
                        <tr class="border-t border-gray-100 align-top">
                            <td class="py-1 pr-2 text-gray-700">{{ $stepTitles[$row->workflow_step_id] ?? '?' }}</td>
                            <td class="py-1 pr-2 text-gray-700">{{ $templateNames[$row->mail_template_id] ?? '?' }}</td>
                            <td class="py-1 pr-2 text-gray-600">{{ $ruleText($row) }}</td>
                            <td class="py-1 pr-2 text-gray-600">{{ collect($row->function_group_ids)->map(fn ($id) => $groupNames[$id] ?? '?')->implode(', ') }}</td>
                            <td class="py-1 text-right">
                                @if ($editable)
                                    <button type="button" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Erinnerung ändern') }}"
                                        @click="edit({{ \Illuminate\Support\Js::from(['id' => $row->id, 'workflow_step_id' => $row->workflow_step_id, 'mail_template_id' => $row->mail_template_id, 'reference' => $row->reference_type.':'.($row->reference_type === 'milestone' ? $row->reference_milestone_id : $row->reference_step_id), 'offset_days' => $row->offset_days, 'function_group_ids' => array_map('intval', (array) $row->function_group_ids), 'only_if_in_step' => (bool) $row->only_if_in_step]) }})">
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
                @if ($mailTemplates->isEmpty())
                    <p class="text-xs text-gray-500">{{ __('Für Erinnerungen wird zuerst eine Mail-Vorlage gebraucht (Administration › Kommunikation › Mail-Vorlagen).') }}</p>
                @elseif ($milestones->isEmpty() && $namedSteps->isEmpty())
                    <p class="text-xs text-gray-500">{{ __('Für Erinnerungen wird ein Bezugstermin gebraucht: ein Meilenstein oder ein Schritt mit benanntem Phasenende.') }}</p>
                @else
                    <button type="button" x-show="form === null" @click="add()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">+ {{ __('Erinnerung') }}</button>

                    <form x-show="form !== null" x-cloak method="POST" :action="form && form.id ? base + '/' + form.id : base" class="rounded-md border border-gray-200 bg-gray-50 p-2 text-xs">
                        @csrf
                        <template x-if="form && form.id"><input type="hidden" name="_method" value="PATCH"></template>
                        <input type="hidden" name="offset_days" :value="offset">
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="block">
                                <span class="block text-[10px] text-gray-500">{{ __('Mail-Vorlage') }}</span>
                                <select name="mail_template_id" x-ref="template" x-model.number="form.mail_template_id" required class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                    <option value="">{{ __('– bitte wählen –') }}</option>
                                    @foreach ($mailTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-[10px] text-gray-500" title="{{ __('Der Schritt, in dem das Projekt zum Sendetag stehen muss.') }}">{{ __('Schritt') }}</span>
                                <select name="workflow_step_id" x-model.number="form.workflow_step_id" class="max-w-[14rem] rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                    @foreach ($steps->where('is_active', true) as $step)<option value="{{ $step->id }}">{{ $step->title }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-[10px] text-gray-500">{{ __('Bezug') }}</span>
                                <select name="reference" x-model="form.reference" required class="max-w-[14rem] rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs">
                                    <option value="">{{ __('– bitte wählen –') }}</option>
                                    @foreach ($milestones as $milestone)<option value="milestone:{{ $milestone->id }}">{{ __('Meilenstein') }}: {{ $milestone->name }}</option>@endforeach
                                    @foreach ($namedSteps as $step)<option value="phase_end:{{ $step->id }}">{{ __('Phasenende') }}: {{ $step->milestone_title }}</option>@endforeach
                                </select>
                            </label>
                            <div class="block">
                                <span class="block text-[10px] text-gray-500">{{ __('Zeitpunkt') }}</span>
                                <div class="flex items-center gap-1">
                                    <input type="number" min="0" max="3650" x-model.number="form.amount" class="w-16 rounded border-gray-300 px-1.5 py-0.5 text-right text-xs">
                                    <select x-model.number="form.unit" class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs"><option :value="1">{{ __('Tage') }}</option><option :value="7">{{ __('Wochen') }}</option></select>
                                    <select x-model.number="form.direction" class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs"><option :value="-1">{{ __('davor') }}</option><option :value="1">{{ __('danach') }}</option></select>
                                </div>
                            </div>
                        </div>
                        <fieldset class="mt-2">
                            <legend class="text-[10px] text-gray-500">{{ __('Empfänger (Funktionsgruppen des Projekts)') }}</legend>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($functionGroups as $group)
                                    <label class="flex items-center gap-1.5 text-gray-700"><input type="checkbox" name="function_group_ids[]" value="{{ $group->id }}" x-model.number="form.function_group_ids" class="rounded border-gray-300">{{ $group->name }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="mt-2 flex items-center gap-1.5 text-gray-700">
                            <input type="hidden" name="only_if_in_step" value="0">
                            <input type="checkbox" name="only_if_in_step" value="1" :checked="form && form.only_if_in_step" @change="form.only_if_in_step = $event.target.checked" class="rounded border-gray-300">
                            {{ __('Nur senden, wenn das Projekt dann noch in diesem Schritt steht') }}
                        </label>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" x-show="form && form.id" class="rounded-md border border-red-200 bg-white px-2 py-0.5 font-medium text-red-700 hover:bg-red-50"
                                @click="window.deleteWithConfirm($refs.deleteForm, {
                                    title: {{ \Illuminate\Support\Js::from(__('Erinnerung löschen?')) }},
                                    message: {{ \Illuminate\Support\Js::from(__('Die Erinnerung wird aus dem Workflow entfernt. Bereits angelegte Erinnerungen in Projekten bleiben bestehen.')) }},
                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }},
                                })">{{ __('Löschen') }}</button>
                            <span class="flex-1"></span>
                            <button type="button" @click="form = null" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                            <button type="submit" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </div>
                    </form>
                    <form x-ref="deleteForm" method="POST" :action="form && form.id ? base + '/' + form.id : '#'" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
