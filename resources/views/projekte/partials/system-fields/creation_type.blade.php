{{--
    Ralf, 2026-09-11: editierbar (Vietto-Vorbild: einfaches Dropdown
    Neuerstellung/Änderung, projekte.intTyp). Tri-state wie Lokalisierung -
    leere Auswahl bleibt "nicht gesetzt", kein erzwungener Standardwert.
--}}
@php
    $creationTypeOptions = [1 => __('Neuerstellung'), 2 => __('Änderung')];
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Erstellungsstatus') }}</label>
    <select name="creation_type" class="mt-0.5 w-full max-w-[200px] rounded border-gray-300 py-1 text-sm">
        <option value="" @selected(old('creation_type', $project->creation_type) === null)>{{ __('– nicht gesetzt –') }}</option>
        @foreach ($creationTypeOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('creation_type', $project->creation_type) == $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
