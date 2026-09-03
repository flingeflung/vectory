<x-dashboard-tile :title="__('Favoriten')" :sortable="true">
    @forelse ($favoriteProjects as $project)
        @php $typeSub = $project->project_type_sub_model; @endphp
        <div class="flex items-center gap-1.5 border-b border-gray-100 py-0.5 last:border-0">
            @if ($typeSub?->smallSymbol())
                <img src="{{ asset('images/dashboard-icons/'.$typeSub->smallSymbol()) }}" alt="" class="h-3 w-auto shrink-0">
            @endif
            <x-pn-link :project="$project" />
            <span class="min-w-0 flex-1 truncate text-gray-700">{{ $project->title }}</span>
            <x-favorite-star :project="$project" :is-favorite="true" size="h-3.5 w-3.5" />
        </div>
    @empty
        <div class="text-gray-400">&ndash; {{ __('Keine Favoriten') }} &ndash;</div>
    @endforelse
</x-dashboard-tile>
