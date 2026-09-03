@props(['project', 'sort' => null, 'direction' => null, 'filters' => []])

<a
    href="{{ route('projekte.show', array_filter(['project' => $project, 'sort' => $sort, 'direction' => $direction, 'filter' => $filters])) }}"
    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $project->id }}, sort: {{ \Illuminate\Support\Js::from($sort) }}, direction: {{ \Illuminate\Support\Js::from($direction) }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } }))"
    {{ $attributes->merge(['class' => 'text-indigo-600 hover:underline']) }}
>{{ $project->source_pn }}</a>
