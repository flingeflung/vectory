<form method="POST" action="{{ route('projekte.request-submit') }}" class="space-y-3">
    @csrf
    <div>
        <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}</label>
        <input type="text" name="title" id="project-request-title" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500">{{ __('Modellnummer(n)/Systemname') }}</label>
        <input type="text" name="model_or_system" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500">{{ __('Termin') }}</label>
        <input type="date" name="due_date" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
        <textarea name="remarks" rows="3" class="mt-0.5 w-full rounded-md border-gray-300 text-sm"></textarea>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-request' }))"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="rounded bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
            {{ __('Anfrage abschicken') }}
        </button>
    </div>
</form>
