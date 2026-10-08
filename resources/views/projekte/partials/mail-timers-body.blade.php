{{--
    Erinnerungen per Mail eines Projekts (Mail-Timer, Ralf, 2026-10-09): Liste der vorhandenen Timer und - mit Recht project.edit - das
    Anlegen weiterer. Bewusst ohne <form>-Element (die Overlays des Projekts schicken Formulare selbst ab): Alpine + fetch().
    Erwartet $project, $timers, $steps, $milestones, $templates, $groups, $canEdit, $preselectStep.
--}}
@php
    $firstStepId = $preselectStep ?? ($steps->firstWhere('lifecycle_status', 2)?->id ?? $steps->first()?->id);
@endphp
<div
    x-data="{
        adding: {{ $preselectStep && $canEdit ? 'true' : 'false' }},
        saving: false,
        error: '',
        base: {{ \Illuminate\Support\Js::from(route('projekte.mailtimer.store', $project)) }},
        csrf: {{ \Illuminate\Support\Js::from(csrf_token()) }},
        draft: { workflow_step_id: {{ \Illuminate\Support\Js::from($firstStepId) }}, mail_template_id: '', reference: 'fixed', fixed_date: '', amount: 7, unit: 7, direction: -1, function_group_ids: [], only_if_in_step: true },
        get isFixed() { return this.draft.reference === 'fixed'; },
        async send(method, url, body) {
            const response = await fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf }, body: body ? JSON.stringify(body) : undefined });
            if (response.status === 422) {
                const data = await response.json().catch(() => ({}));
                this.error = Object.values(data.errors || {}).flat()[0] || data.message || {{ \Illuminate\Support\Js::from(__('Das Speichern ist fehlgeschlagen.')) }};
                return false;
            }
            if (! response.ok) { this.error = {{ \Illuminate\Support\Js::from(__('Das Speichern ist fehlgeschlagen.')) }}; return false; }
            return true;
        },
        async reload() {
            document.getElementById('mail-timers-body').innerHTML = await fetch({{ \Illuminate\Support\Js::from(route('projekte.mailtimer.index', $project)) }}).then((r) => r.text());
            if (window.refreshUnderlyingProject) window.refreshUnderlyingProject({{ $project->id }});
        },
        async save() {
            if (this.saving) return;
            this.saving = true;
            this.error = '';
            const d = this.draft;
            const ok = await this.send('POST', this.base, {
                workflow_step_id: d.workflow_step_id,
                mail_template_id: d.mail_template_id,
                reference: d.reference,
                fixed_date: this.isFixed ? d.fixed_date : null,
                offset_days: this.isFixed ? 0 : d.direction * Math.abs(Number(d.amount || 0)) * Number(d.unit),
                function_group_ids: d.function_group_ids,
                only_if_in_step: d.only_if_in_step,
            });
            this.saving = false;
            if (ok) await this.reload();
        },
        async remove(id) {
            const confirmed = await window.confirmDialog({
                signal: 'achtung',
                title: {{ \Illuminate\Support\Js::from(__('Erinnerung löschen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Die Erinnerung wird aus diesem Projekt entfernt und nicht mehr gesendet.')) }},
                consequence: {{ \Illuminate\Support\Js::from(__('Das lässt sich nicht rückgängig machen.')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            });
            if (! confirmed) return;
            if (await this.send('DELETE', this.base + '/' + id, null)) await this.reload();
        },
    }"
    class="space-y-3"
>
    @if ($timers->isEmpty())
        <p class="rounded-md border border-dashed border-gray-200 p-3 text-gray-400">{{ __('Für dieses Projekt gibt es noch keine Erinnerungen.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] text-xs">
                <thead>
                    <tr class="text-left text-[10px] font-medium uppercase tracking-wide text-gray-400">
                        <th class="py-1 pr-2">{{ __('Sendedatum') }}</th>
                        <th class="py-1 pr-2">{{ __('Mail-Vorlage') }}</th>
                        <th class="py-1 pr-2">{{ __('Bezug') }}</th>
                        <th class="py-1 pr-2">{{ __('Empfänger') }}</th>
                        <th class="py-1 pr-2">{{ __('Status') }}</th>
                        @if ($canEdit)<th class="w-6 py-1"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timers as $timer)
                        <tr class="border-t border-gray-100 align-top">
                            <td class="whitespace-nowrap py-1.5 pr-2 font-medium text-gray-800">{{ $timer['send_date'] ? $timer['send_date']->format('d.m.Y') : __('wartet auf Termin') }}</td>
                            <td class="py-1.5 pr-2 text-gray-700">{{ $timer['template'] }}
                                @if ($timer['step'])<span class="block text-gray-400" title="{{ $timer['only_if_in_step'] ? __('Wird nur gesendet, wenn das Projekt dann noch in diesem Schritt steht.') : __('Wird unabhängig vom Schritt gesendet.') }}">{{ __('Schritt') }}: {{ $timer['step'] }}</span>@endif
                            </td>
                            <td class="py-1.5 pr-2 text-gray-600">{{ $timer['rule'] }}</td>
                            <td class="py-1.5 pr-2 text-gray-600" title="{{ __(':n Personen mit E-Mail-Adresse', ['n' => $timer['recipients']]) }}">{{ implode(', ', $timer['groups']) }} <span class="text-gray-400">({{ $timer['recipients'] }})</span></td>
                            <td class="py-1.5 pr-2">
                                @if ($timer['sent_at'])
                                    <span class="text-green-800">{{ __('gesendet am :date', ['date' => $timer['sent_at']->format('d.m.Y')]) }}</span>
                                @elseif ($timer['skipped_at'])
                                    <span class="text-gray-500" title="{{ __('Das Projekt war am Sendetag nicht mehr im Schritt.') }}">{{ __('übersprungen') }}</span>
                                @elseif ($timer['error'])
                                    <span class="text-red-700" title="{{ $timer['error'] }}">{{ __('Fehler') }}</span>
                                @else
                                    <span class="text-gray-700">{{ __('geplant') }}</span>
                                @endif
                            </td>
                            @if ($canEdit)
                                <td class="py-1.5 text-right">
                                    @if (! $timer['sent_at'])
                                        <button type="button" @click="remove({{ $timer['id'] }})" class="rounded p-0.5 text-gray-300 hover:bg-gray-100 hover:text-red-600" title="{{ __('Erinnerung löschen') }}" aria-label="{{ __('Erinnerung löschen') }}">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canEdit)
        <div>
            <button type="button" x-show="! adding" @click="adding = true; $nextTick(() => $refs.template && $refs.template.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">+ {{ __('Erinnerung') }}</button>

            <div x-show="adding" x-cloak @keydown.enter.prevent="save()" class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs">
                @if ($templates->isEmpty())
                    <p class="text-gray-600">{{ __('Es gibt noch keine Mail-Vorlagen. Legen Sie zuerst unter Administration › Kommunikation › Mail-Vorlagen eine an.') }}</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('Mail-Vorlage') }}</span>
                            <select x-ref="template" x-model="draft.mail_template_id" class="w-full rounded border-gray-300 py-1 text-xs">
                                <option value="">{{ __('– bitte wählen –') }}</option>
                                @foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] text-gray-500" title="{{ __('Der Schritt, in dem das Projekt zum Sendetag stehen muss.') }}">{{ __('Schritt') }}</span>
                            <select x-model.number="draft.workflow_step_id" class="w-full rounded border-gray-300 py-1 text-xs">
                                @foreach ($steps as $step)<option value="{{ $step->id }}">{{ $step->title }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('Bezug') }}</span>
                            <select x-model="draft.reference" class="w-full rounded border-gray-300 py-1 text-xs">
                                <option value="fixed">{{ __('festes Datum') }}</option>
                                @foreach ($milestones as $milestone)<option value="milestone:{{ $milestone->id }}">{{ __('Meilenstein') }}: {{ $milestone->name }}</option>@endforeach
                                @foreach ($steps->filter(fn ($s) => $s->milestone_title) as $step)<option value="phase_end:{{ $step->id }}">{{ __('Phasenende') }}: {{ $step->milestone_title }}</option>@endforeach
                            </select>
                        </label>
                        <div class="block">
                            <span class="block text-[10px] text-gray-500">{{ __('Sendedatum') }}</span>
                            <div x-show="isFixed"><input type="date" x-model="draft.fixed_date" class="rounded border-gray-300 py-1 text-xs"></div>
                            <div x-show="! isFixed" class="flex items-center gap-1">
                                <input type="number" min="0" max="3650" x-model.number="draft.amount" class="w-16 rounded border-gray-300 py-1 text-right text-xs">
                                <select x-model.number="draft.unit" class="rounded border-gray-300 py-1 text-xs"><option :value="1">{{ __('Tage') }}</option><option :value="7">{{ __('Wochen') }}</option></select>
                                <select x-model.number="draft.direction" class="rounded border-gray-300 py-1 text-xs"><option :value="-1">{{ __('davor') }}</option><option :value="1">{{ __('danach') }}</option></select>
                            </div>
                        </div>
                    </div>
                    <fieldset class="mt-3">
                        <legend class="text-[10px] text-gray-500">{{ __('Empfänger (Funktionsgruppen des Projekts)') }}</legend>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            @foreach ($groups as $group)
                                <label class="flex items-center gap-1.5 text-gray-700"><input type="checkbox" value="{{ $group->id }}" x-model.number="draft.function_group_ids" class="rounded border-gray-300">{{ $group->name }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="mt-3 flex items-center gap-1.5 text-gray-700"><input type="checkbox" x-model="draft.only_if_in_step" class="rounded border-gray-300">{{ __('Nur senden, wenn das Projekt dann noch in diesem Schritt steht') }}</label>
                    <p x-show="error" x-text="error" class="mt-2 text-red-600"></p>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <button type="button" @click="adding = false; error = ''" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="button" @click="save()" :disabled="saving || ! draft.mail_template_id || draft.function_group_ids.length === 0" class="rounded-md border border-transparent bg-btn-primary px-2.5 py-0.5 font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Speichern') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
