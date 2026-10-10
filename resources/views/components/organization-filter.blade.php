@props(['organizations', 'selected'])

{{-- Organisationsauswahl für Listen, die mehrere Organisationen zugleich zeigen können (Aufgaben, Illustrationen;
     Ralf, 2026-10-09). Gehört in das GET-Filterformular. Bei nur einer Organisation erscheint nichts. --}}
@if ($organizations->count() > 1)
    <input type="hidden" name="organizations_submitted" value="1">
    <x-filter-dropdown label="{{ __('Organisationen') }} ({{ $selected->count() }}/{{ $organizations->count() }})">
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">{{ __('Organisationen') }}</span>
            <span class="flex gap-2 text-xs">
                <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Alle') }}</button>
                <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Keiner') }}</button>
            </span>
        </div>
        <div class="max-h-56 space-y-1 overflow-y-auto">
            @foreach ($organizations as $organization)
                <label class="flex items-center gap-1.5 text-gray-700">
                    <input type="checkbox" name="organizations[]" value="{{ $organization->id }}" class="rounded border-gray-300" @checked($selected->contains((int) $organization->id))>
                    {{ $organization->name }}
                </label>
            @endforeach
        </div>
        <button type="submit" class="mt-2 w-full rounded bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Anwenden') }}</button>
    </x-filter-dropdown>
@endif
