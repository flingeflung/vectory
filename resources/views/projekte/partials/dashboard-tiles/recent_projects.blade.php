<x-dashboard-tile :title="__('Zuletzt geöffnete Projekte')" :sortable="true">
    @forelse ($recentProjects as $entry)
        @php $project = $entry->project; @endphp
        @if ($project)
            @php $typeSub = $project->project_type_sub_model; @endphp
            <div class="flex items-center gap-1.5 border-b border-gray-100 py-0.5 last:border-0">
                @if ($typeSub?->smallSymbol())
                    <img
                        src="{{ asset('images/dashboard-icons/'.$typeSub->smallSymbol()) }}"
                        alt="{{ $typeSub->name }}"
                        title="{{ $typeSub->main ? $typeSub->main->name.': '.$typeSub->name : $typeSub->name }}"
                        class="h-3 w-auto shrink-0"
                    >
                @endif
                <x-pn-link :project="$project" />
                <span class="min-w-0 flex-1 truncate text-gray-700">{{ $project->title }}</span>
                <form method="POST" action="{{ route('dashboard.recent.destroy', $entry) }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="shrink-0 text-gray-300 hover:text-red-500" title="{{ __('Aus der Liste entfernen') }}">&times;</button>
                </form>
            </div>
        @endif
    @empty
        <div class="text-gray-400">&ndash; {{ __('Noch keine Projekte geöffnet') }} &ndash;</div>
    @endforelse
</x-dashboard-tile>
