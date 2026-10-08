{{--
    Planung übertragen (Ralf, 2026-10-08): Richtung (senden / holen), Gruppe oder Einzelprojekt, Bereiche, Umgang mit Vorhandenem.
    Erwartet $project, $ownInfo (Bereiche dieses Projekts), $groups, $groupMembers, $defaultGroupId, $projectOptions, $parts (Bereich => Bezeichnung, nur die erlaubten).
    Das Formular prüft vorab: Bereiche, die die Quelle nicht hat, und Dauern/Sperren/Termine ohne passenden Workflow im Ziel sind gesperrt, mit Hinweis.
--}}
<form
    id="planning-transfer-form"
    x-data="{
        direction: 'send',
        scope: '{{ $defaultGroupId ? 'group' : 'single' }}',
        groupId: @js($defaultGroupId ? (string) $defaultGroupId : ''),
        includeMain: false,
        groups: @js($groups->mapWithKeys(fn ($group) => [(string) $group->id => ['verbund' => (bool) $group->is_verbund]])),
        members: @js($groupMembers),
        ownInfo: @js($ownInfo),
        projects: @js($projectOptions),
        otherText: '',
        otherId: '',
        otherInfo: null,
        parts: { workflow: true, durations: true, planned_hours: true, people: true, milestones: false },
        busy: false,
        get isVerbund() { const group = this.groups[this.groupId]; return !! (group && group.verbund); },
        get usesGroup() { return this.direction === 'send' && this.scope === 'group'; },
        get groupTargets() { return (this.members[this.groupId] || []).filter((member) => this.includeMain || ! member.main); },
        get targetCount() { return this.usesGroup ? this.groupTargets.length : 1; },
        // Quelle: beim Senden dieses Projekt, beim Holen das gewählte Projekt (unbekannt, bis eines gewählt ist)
        get sourceInfo() { return this.direction === 'send' ? this.ownInfo : this.otherInfo; },
        // Workflow der Ziele als Liste der Workflow-Nummern (leer = noch unbekannt)
        get targetWorkflows() {
            if (this.direction === 'fetch') return [this.ownInfo.workflow_id];
            if (this.usesGroup) return this.groupTargets.map((member) => member.wf);
            return this.otherInfo ? [this.otherInfo.workflow_id] : [];
        },
        get mismatchCount() {
            const source = this.sourceInfo;
            if (! source) return 0;
            return this.targetWorkflows.filter((wf) => ! wf || wf !== source.workflow_id).length;
        },
        get workflowMatch() {
            const total = this.targetWorkflows.length;
            if (! this.sourceInfo || total === 0) return null;
            return this.mismatchCount === 0 ? 'all' : (this.mismatchCount === total ? 'none' : 'some');
        },
        missing: {
            workflow: @js(__('Die Quelle hat keinen Workflow.')),
            durations: @js(__('Die Quelle hat keine eigenen Dauern oder Sperren.')),
            planned_hours: @js(__('Die Quelle hat weder ein Aufwandsprofil noch eigene Planstunden.')),
            people: @js(__('Die Quelle hat keine Projektbeteiligten.')),
            milestones: @js(__('Die Quelle hat keine Termine an den Schritten.')),
        },
        // Grund, warum ein Bereich nicht übertragbar ist (leer = übertragbar)
        reason(key) {
            const source = this.sourceInfo;
            if (! source) return '';
            if (! source[key]) return this.missing[key];
            if ((key === 'durations' || key === 'milestones') && this.workflowMatch === 'none' && ! (this.parts.workflow && source.workflow)) return @js(__('Setzt denselben Workflow im Ziel voraus. Haken Sie zuerst „Workflow“ an.'));
            return '';
        },
        hint(key) {
            if ((key === 'durations' || key === 'milestones') && this.workflowMatch === 'some' && ! this.parts.workflow) {
                return @js(__('Bei :n von :m Zielen ist der Workflow ein anderer; dort wird übersprungen.')).replace(':n', this.mismatchCount).replace(':m', this.targetWorkflows.length);
            }
            return '';
        },
        partOn(key) { return !! this.parts[key] && this.reason(key) === ''; },
        async matchProject() {
            const found = this.projects.find((item) => item.label === this.otherText.trim());
            this.otherId = found ? String(found.id) : '';
            this.otherInfo = null;
            if (! this.otherId) return;
            const id = this.otherId;
            const response = await fetch(@js(route('projekte.planung-uebertragen.info', $project)) + '?other=' + id, { headers: { 'Accept': 'application/json' } });
            if (response.ok && this.otherId === id) this.otherInfo = await response.json();
        },
        setDirection(value) {
            this.direction = value;
            if (value === 'fetch') this.scope = 'single';
            this.otherInfo = null;
            if (this.otherId) this.matchProject();
            this.$nextTick(() => this.focusFirst());
        },
        focusFirst() { const field = this.$refs[this.usesGroup ? 'groupSelect' : 'projectInput']; if (field) field.focus(); },
        async submit() {
            if (this.busy) return;
            if (! this.usesGroup && ! this.otherId) { await window.notifyDialog(@js(__('Bitte ein Projekt aus der Liste auswählen. Tippen Sie dazu die PN oder einen Teil des Titels.'))); return; }
            if (this.usesGroup && ! this.groupId) { await window.notifyDialog(@js(__('Bitte eine Gruppe auswählen.'))); return; }
            if (! Object.keys(this.parts).some((key) => this.partOn(key))) { await window.notifyDialog(@js(__('Bitte mindestens einen Bereich auswählen.'))); return; }
            const form = this.$refs.form;
            const message = this.direction === 'fetch'
                ? @js(__('Die gewählten Bereiche dieses Projekts werden durch die Werte des anderen Projekts ersetzt.'))
                : @js(__('Die gewählten Bereiche werden in bis zu :n Projekten durch die Werte dieses Projekts ersetzt.')).replace(':n', this.targetCount);
            if (! await window.confirmDialog({
                signal: 'achtung',
                title: @js(__('Planung übertragen?')),
                message: message,
                consequence: @js(__('Bisherige Werte im Ziel gehen verloren, soweit Sie nicht „Vorhandenes im Ziel behalten“ gewählt haben.')),
                confirmLabel: @js(__('Übertragen')),
                cancelLabel: @js(__('Abbrechen')),
            })) return;
            this.busy = true;
            const response = await fetch(@js(route('projekte.planung-uebertragen.run', $project)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'text/html' },
                body: new FormData(form),
            });
            this.busy = false;
            if (! response.ok) {
                const data = await response.json().catch(() => ({}));
                await window.notifyDialog(data.message || @js(__('Die Planung konnte nicht übertragen werden.')));
                return;
            }
            document.getElementById('planning-transfer-body').innerHTML = await response.text();
            window.dispatchEvent(new CustomEvent('project-schedule-changed', { detail: { projectId: {{ $project->id }} } }));
            await window.refreshUnderlyingProject({{ $project->id }});
        },
    }"
    x-ref="form"
    @submit.prevent="submit()"
    x-init="$nextTick(() => focusFirst())"
    class="space-y-4 text-sm"
>
    <fieldset>
        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Richtung') }}</legend>
        <div class="flex flex-wrap gap-x-5 gap-y-1">
            <label class="inline-flex items-center gap-1.5"><input type="radio" name="direction" value="send" x-model="direction" @change="setDirection('send')" class="border-gray-300"> {{ __('Planung dieses Projekts an andere senden') }}</label>
            <label class="inline-flex items-center gap-1.5"><input type="radio" name="direction" value="fetch" x-model="direction" @change="setDirection('fetch')" class="border-gray-300"> {{ __('Planung von einem anderen Projekt holen') }}</label>
        </div>
    </fieldset>

    <fieldset>
        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="direction === 'send' ? @js(__('Ziel')) : @js(__('Quelle'))"></legend>
        <div class="flex flex-wrap gap-x-5 gap-y-1" x-show="direction === 'send'">
            <label class="inline-flex items-center gap-1.5"><input type="radio" name="scope" value="group" x-model="scope" @change="$nextTick(() => focusFirst())" class="border-gray-300"> {{ __('Gruppe') }}</label>
            <label class="inline-flex items-center gap-1.5"><input type="radio" name="scope" value="single" x-model="scope" @change="$nextTick(() => focusFirst())" class="border-gray-300"> {{ __('Einzelprojekt') }}</label>
        </div>

        <div class="mt-2" x-show="usesGroup">
            @if ($groups->isEmpty())
                <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ __('Es gibt noch keine Gruppe. Wählen Sie „Einzelprojekt“ oder legen Sie über „Gruppieren“ in der Projektübersicht oder den Projektdetails eine Gruppe an.') }}
                </p>
            @else
                <select name="group_id" x-model="groupId" x-ref="groupSelect" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('– Gruppe wählen –') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}{{ $group->is_verbund ? ' ('.__('Verbund').')' : '' }} · {{ $group->projects_count }}</option>
                    @endforeach
                </select>
                <label class="mt-2 inline-flex items-center gap-1.5" x-show="isVerbund">
                    <input type="checkbox" name="include_main" value="1" x-model="includeMain" class="rounded border-gray-300">
                    {{ __('Hauptprojekt einbeziehen') }}
                </label>
            @endif
        </div>

        <div class="mt-2" x-show="! usesGroup">
            <input
                type="text"
                x-ref="projectInput"
                x-model="otherText"
                @input="matchProject()"
                list="planning-transfer-projects"
                autocomplete="off"
                placeholder="{{ __('PN oder Titel eintippen …') }}"
                class="w-full rounded-md border-gray-300 text-sm"
            >
            <datalist id="planning-transfer-projects">
                <template x-for="item in projects" :key="item.id"><option :value="item.label"></option></template>
            </datalist>
            <input type="hidden" name="other_project_id" :value="otherId">
            <p class="mt-1 text-xs text-gray-500" x-show="otherText !== '' && otherId === ''">{{ __('Bitte einen Eintrag aus der Liste wählen.') }}</p>
        </div>
    </fieldset>

    <fieldset>
        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Bereiche') }}</legend>
        <div class="space-y-1">
            @foreach ($parts as $key => $label)
                <div>
                    <label class="flex items-center gap-1.5" :class="reason('{{ $key }}') !== '' ? 'text-gray-400' : ''">
                        <input
                            type="checkbox"
                            name="parts[]"
                            value="{{ $key }}"
                            :checked="partOn('{{ $key }}')"
                            :disabled="reason('{{ $key }}') !== ''"
                            @change="parts['{{ $key }}'] = $event.target.checked"
                            class="rounded border-gray-300"
                        >
                        {{ $label }}
                        @if ($key === \App\Services\PlanningTransfer::MILESTONES)
                            <span class="text-xs text-gray-400" title="{{ __('Ohne diesen Haken bleiben die Termine im Ziel unverändert; mit „Termine berechnen“ lassen sie sich dort aus den Dauern ab dem eigenen Projektstart bestimmen.') }}">ⓘ</span>
                        @endif
                    </label>
                    <p class="ml-6 text-xs text-amber-700" x-show="reason('{{ $key }}') !== '' || hint('{{ $key }}') !== ''" x-text="reason('{{ $key }}') || hint('{{ $key }}')"></p>
                </div>
            @endforeach
        </div>
    </fieldset>

    <label class="flex items-center gap-1.5">
        <input type="checkbox" name="keep_existing" value="1" class="rounded border-gray-300">
        {{ __('Vorhandenes im Ziel behalten (nur ergänzen, wo noch nichts steht)') }}
    </label>

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planning-transfer' }))" class="whitespace-nowrap rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
        <button type="submit" :disabled="busy" class="whitespace-nowrap rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50">{{ __('Übertragen') }}</button>
    </div>
</form>
