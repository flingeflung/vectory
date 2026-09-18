{{--
    Ralf, 2026-09-18: einem Projekt eine der Projektschablonen (Step 1 der
    Kapa-Planung) zuordnen können - reiner Verweis, keine Übernahme von
    Merkmalen/Stunden ins Projekt. Gleiches Auswahl-Muster wie
    project_type.blade.php.
--}}
<div>
    <label class="block text-xs text-gray-500">{{ __('Projektschablone') }}</label>
    <select name="project_template_id" class="mt-0.5 w-full max-w-sm rounded border-gray-300 py-1 text-sm">
        <option value="">{{ __('– nicht zugewiesen –') }}</option>
        @foreach ($availableProjectTemplates as $template)
            <option
                value="{{ $template->id }}"
                @selected(old('project_template_id', $project->project_template_id) == $template->id)
                @class(['text-gray-400' => ! $template->active])
            >{{ $template->name }}{{ ! $template->active ? ' [i]' : '' }}</option>
        @endforeach
    </select>
</div>
