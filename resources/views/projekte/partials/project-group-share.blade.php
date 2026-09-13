{{--
    "Gruppe teilen" - Fragment, ersetzt #project-group-panel-body temporär
    (siehe project-group-modal.blade.php, openShare()/closeShare()).
--}}
<div class="mb-2 flex items-center justify-between">
    <div class="text-xs font-medium text-gray-700">{{ __('„:name" teilen mit:', ['name' => $group->name]) }}</div>
    <button type="button" @click="closeShare()" class="text-xs text-indigo-600 hover:underline">{{ __('Zurück') }}</button>
</div>
<div class="max-h-56 space-y-1 overflow-y-auto text-xs">
    @foreach ($users as $shareUser)
        <label class="flex items-center gap-1.5 text-gray-700">
            <input
                type="checkbox"
                class="rounded border-gray-300"
                @checked($viewerIds->contains($shareUser->id))
                {{ $shareUser->id === auth()->id() ? 'disabled title="'.__('Du selbst - kann nicht entfernt werden, solange du die Gruppe siehst').'"' : '' }}
                @change="toggleShare({{ $shareUser->id }}, $event.target.checked)"
            >
            {{ $shareUser->name }}
        </label>
    @endforeach
</div>
