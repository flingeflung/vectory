@props(['person'])

<a
    href="{{ route('admin.personen.edit', $person) }}"
    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $person->id }}, name: {{ \Illuminate\Support\Js::from($person->fullName()) }} } }))"
    {{ $attributes->merge(['class' => 'text-indigo-700 hover:underline']) }}
>{{ $person->fullName() }}</a>
