{{--
    Ralf, 2026-09-18: einem Projekt eine der Projektschablonen (Step 1 der
    Kapa-Planung) zuordnen können - reiner Verweis, keine Übernahme von
    Merkmalen/Stunden ins Projekt. Gleiches Auswahl-Muster wie
    project_type.blade.php.

    Ralf, 2026-09-19: kleiner Info-Button daneben (nur bei gültiger Auswahl),
    öffnet die Merkmale/Stunden der gewählten Schablone rein lesend im
    globalen Fetch-Overlay (window.openProjectTemplateInfo(), siehe
    layouts/app.blade.php) - reagiert live auf die Auswahl, nicht erst nach
    dem Speichern.
--}}
<div x-data="{ templateId: {{ \Illuminate\Support\Js::from((string) old('project_template_id', $project->project_template_id ?? '')) }} }">
    <div class="flex items-center gap-1.5">
        <label class="block text-xs text-gray-500">{{ __('Projektschablone') }}</label>
        <x-info-icon-button
            x-show="templateId"
            x-cloak
            @click="window.openProjectTemplateInfo(templateId)"
            :title="__('Merkmale der gewählten Schablone ansehen')"
        />
    </div>
    <select name="project_template_id" x-model="templateId" class="mt-0.5 w-full max-w-sm rounded border-gray-300 py-1 text-sm">
        <option value="">{{ __('– nicht zugewiesen –') }}</option>
        @foreach ($availableProjectTemplates as $template)
            <option
                value="{{ $template->id }}"
                @class(['text-gray-400' => ! $template->active])
            >{{ $template->name }}{{ ! $template->active ? ' [i]' : '' }}</option>
        @endforeach
    </select>
</div>
