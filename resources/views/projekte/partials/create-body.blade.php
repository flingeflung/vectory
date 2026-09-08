<div class="mb-3 text-xs text-gray-500">
    {{ __('Vergebene PN (Vorschau, wird erst beim Anlegen final zugewiesen)') }}:
    <span class="font-medium text-gray-900">{{ $suggestedPn }}</span>
</div>

<form method="POST" action="{{ route('projekte.store') }}" class="space-y-3">
    @csrf
    <div>
        <label class="text-xs text-gray-500">{{ __('Projektbezeichnung') }}</label>
        <input type="text" name="title" id="project-create-title" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
    </div>

    @if ($canAddCreatorAsParticipant)
        <label class="flex items-center gap-1.5 text-gray-700">
            <input type="checkbox" name="add_as_participant" value="1" checked class="rounded border-gray-300">
            {{ __('Mich als Projektbeteiligten eintragen') }}
        </label>
    @endif

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-create' }))"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="rounded bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
            {{ __('Anlegen') }}
        </button>
    </div>
</form>
