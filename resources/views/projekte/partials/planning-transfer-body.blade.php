{{--
    Planung übertragen (Ralf, 2026-10-08): Richtung (senden / holen), Gruppe oder Einzelprojekt, Bereiche, Umgang mit Vorhandenem.
    Erwartet $project, $groups, $defaultGroupId, $projectOptions, $parts (Bereich => Bezeichnung, nur die erlaubten).
--}}
<form
    id="planning-transfer-form"
    x-data="{
        direction: 'send',
        scope: '{{ $defaultGroupId ? 'group' : 'single' }}',
        groupId: @js($defaultGroupId ? (string) $defaultGroupId : ''),
        groups: @js($groups->mapWithKeys(fn ($group) => [(string) $group->id => ['verbund' => (bool) $group->is_verbund, 'count' => (int) $group->projects_count]])),
        projects: @js($projectOptions),
        otherText: '',
        otherId: '',
        busy: false,
        get isVerbund() { const group = this.groups[this.groupId]; return !! (group && group.verbund); },
        get usesGroup() { return this.direction === 'send' && this.scope === 'group'; },
        get targetCount() { return this.usesGroup ? ((this.groups[this.groupId] || {}).count || 0) : 1; },
        matchProject() { const found = this.projects.find((item) => item.label === this.otherText.trim()); this.otherId = found ? String(found.id) : ''; },
        setDirection(value) {
            this.direction = value;
            if (value === 'fetch') this.scope = 'single';
            this.$nextTick(() => this.focusFirst());
        },
        focusFirst() { const field = this.$refs[this.usesGroup ? 'groupSelect' : 'projectInput']; if (field) field.focus(); },
        async submit() {
            if (this.busy) return;
            if (! this.usesGroup && ! this.otherId) { await window.notifyDialog(@js(__('Bitte ein Projekt aus der Liste auswählen. Tippen Sie dazu die PN oder einen Teil des Titels.'))); return; }
            if (this.usesGroup && ! this.groupId) { await window.notifyDialog(@js(__('Bitte eine Gruppe auswählen.'))); return; }
            const form = this.$refs.form;
            if (! form.querySelector('input[name=\'parts[]\']:checked')) { await window.notifyDialog(@js(__('Bitte mindestens einen Bereich auswählen.'))); return; }
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
            <select name="group_id" x-model="groupId" x-ref="groupSelect" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">{{ __('– Gruppe wählen –') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}{{ $group->is_verbund ? ' ('.__('Verbund').')' : '' }} · {{ $group->projects_count }}</option>
                @endforeach
            </select>
            <label class="mt-2 inline-flex items-center gap-1.5" x-show="isVerbund">
                <input type="checkbox" name="include_main" value="1" class="rounded border-gray-300">
                {{ __('Hauptprojekt einbeziehen') }}
            </label>
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
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="parts[]" value="{{ $key }}" @checked($key !== \App\Services\PlanningTransfer::MILESTONES) class="rounded border-gray-300">
                    {{ $label }}
                    @if ($key === \App\Services\PlanningTransfer::MILESTONES)
                        <span class="text-xs text-gray-400" title="{{ __('Ohne diesen Haken bleiben die Termine im Ziel unverändert; mit „Termine berechnen“ lassen sie sich dort aus den Dauern ab dem eigenen Projektstart bestimmen.') }}">ⓘ</span>
                    @endif
                </label>
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
