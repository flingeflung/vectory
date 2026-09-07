@props(['person', 'filters' => []])

{{--
    Super-Admin-Konten dürfen nur von einem anderen Super-Admin geöffnet
    werden (siehe PersonController::abortIfProtectedFromEditing()) - der
    Link wird für alle anderen hier schon gar nicht erst anklickbar
    gemacht, statt erst beim Öffnen mit einer hässlichen 403-Antwort in
    der Overlay-Modal zu landen (Ralf: "Sperre doch bitte in der
    Personenliste den Link! bzw. inaktiv setzen, ausgrauen").
--}}
@if ($person->user?->role === 'super_admin' && auth()->user()->role !== 'super_admin')
    <span class="text-gray-400" title="{{ __('Nur ein Super-Admin darf dieses Konto öffnen.') }}">{{ $person->fullName() }}</span>
@else
    <a
        href="{{ route('admin.personen.edit', [...$filters, 'person' => $person->id]) }}"
        onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $person->id }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } }))"
        {{ $attributes->merge(['class' => 'text-indigo-700 hover:underline']) }}
    >{{ $person->fullName() }}</a>
@endif
