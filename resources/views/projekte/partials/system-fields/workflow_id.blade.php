<div x-data="{ editingWorkflow: false }">
    <div class="flex items-center gap-2">
        <label class="text-xs text-gray-500">{{ __('Workflow') }}</label>
        <x-edit-icon-button x-show="!editingWorkflow" @click="editingWorkflow = true" :title="__('Ändern')" />
        <button type="button" x-show="editingWorkflow" x-cloak @click="editingWorkflow = false" class="{{ $secondaryBtn }}">
            {{ __('Fertig') }}
        </button>
    </div>

    <div x-show="!editingWorkflow" class="mt-0.5 text-gray-700">
        {{ $project->workflow?->name ?? __('– kein Workflow zugewiesen –') }}
    </div>

    <div x-show="editingWorkflow" x-cloak class="mt-0.5">
        <select name="workflow_id" class="w-full max-w-sm rounded border-gray-300 py-1 text-sm">
            <option value="">{{ __('– kein Workflow zugewiesen –') }}</option>
            @foreach ($availableWorkflows as $availableWorkflow)
                <option
                    value="{{ $availableWorkflow->id }}"
                    @selected(old('workflow_id', $project->workflow_id) == $availableWorkflow->id)
                    @class(['text-gray-400' => ! $availableWorkflow->active])
                >{{ $availableWorkflow->name }}{{ ! $availableWorkflow->active ? ' [i]' : '' }}</option>
            @endforeach
        </select>
    </div>
</div>
