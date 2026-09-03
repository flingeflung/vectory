<div x-data x-on:open-project.window="$dispatch('close-modal', 'favorites')">
    @forelse ($projects as $project)
        <div class="flex items-center gap-2 border-b border-gray-100 py-2 text-sm last:border-0">
            <x-pn-link :project="$project" class="font-semibold shrink-0" />
            <span class="truncate text-gray-600">{{ $project->title }}</span>
        </div>
    @empty
        <div class="py-4 text-center text-sm text-gray-400">{{ __('Keine Favoriten gespeichert') }}</div>
    @endforelse
</div>
