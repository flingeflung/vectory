@props(['person', 'filters' => []])

<a
    href="{{ route('admin.personen.edit', [...$filters, 'person' => $person->id]) }}"
    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $person->id }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } }))"
    {{ $attributes->merge(['class' => 'text-indigo-700 hover:underline']) }}
>{{ $person->fullName() }}</a>
