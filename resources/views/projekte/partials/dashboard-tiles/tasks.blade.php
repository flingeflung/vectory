<x-dashboard-tile :title="__('Aufgaben')" :sortable="true">
    @forelse ($tasks as $task)
        @php $project = $task->project; @endphp
        @if ($project)
            @php $typeSub = $project->project_type_sub_model; @endphp
            <div class="flex items-center gap-1.5 border-b border-gray-100 py-0.5 last:border-0">
                @if ($typeSub?->smallSymbol())
                    <img src="{{ asset('images/dashboard-icons/'.$typeSub->smallSymbol()) }}" alt="" class="h-3 w-auto shrink-0">
                @endif
                <x-pn-link :project="$project" />
                <span class="min-w-0 flex-1 truncate text-gray-700">{{ $project->title }}</span>
                @if ($task->projectWorkflowStep?->workflowStep)
                    <span class="shrink-0 text-xs text-gray-400">{{ $task->projectWorkflowStep->workflowStep->short_title ?? $task->projectWorkflowStep->workflowStep->title }}</span>
                @endif
            </div>
        @endif
    @empty
        <div class="text-gray-400">&ndash; {{ __('Keine Aufgaben') }} &ndash;</div>
    @endforelse
</x-dashboard-tile>
