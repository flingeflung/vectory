<div x-data x-on:open-project.window="$dispatch('close-modal', 'recent-projects')">
    @forelse ($entries as $entry)
        @php($project = $entry->project)
        @if ($project)
            @php($typeSub = $project->project_type_sub_model)
            <div class="flex items-center gap-2 border-b border-gray-100 py-2 text-sm last:border-0">
                <div class="flex h-3 w-4 shrink-0 items-center justify-center">
                    @if ($typeSub?->symbol)
                        <img src="{{ asset('images/project-type-icons/'.$typeSub->symbol) }}" alt="{{ $typeSub->name }}" title="{{ $typeSub->main ? $typeSub->main->name.': '.$typeSub->name : $typeSub->name }}" class="h-3 w-3 shrink-0 object-contain">
                    @endif
                </div>
                <x-pn-link :project="$project" class="shrink-0 font-semibold" />
                <x-hauptprojekt-icon :project="$project" />
                <x-unterprojekt-icon :project="$project" />
                <span class="truncate text-gray-600">{{ $project->title }}</span>
            </div>
        @endif
    @empty
        <div class="py-4 text-center text-sm text-gray-400">{{ __('Noch keine Projekte geöffnet') }}</div>
    @endforelse
</div>
