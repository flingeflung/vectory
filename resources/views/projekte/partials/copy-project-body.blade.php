@php
    $templateFieldKeys = $templates->mapWithKeys(fn ($template) => [$template->id => $template->fields->pluck('key')->values()]);
    $firstTemplateId = $templates->first()?->id;
@endphp

<div
    x-data="{
        templateId: {{ \Illuminate\Support\Js::from($firstTemplateId) }},
        templateFieldKeys: {{ \Illuminate\Support\Js::from($templateFieldKeys) }},
        count: 1,
        has(key) { return (this.templateFieldKeys[this.templateId] || []).includes(key); },
    }"
>
    <div class="mb-3 text-xs text-gray-500">
        {{ __('Kopie von') }} <span class="font-medium text-gray-900">{{ $project->source_pn }} – {{ $project->title }}</span>
    </div>

    @if ($templates->isEmpty())
        <p class="text-sm text-gray-500">{{ __('Noch keine Vorlage angelegt - lege zuerst unter Admin > Projektkopie-Vorlagen eine an.') }}</p>
        <div class="flex justify-end border-t border-gray-200 pt-3">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-copy' }))" class="rounded border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Schließen') }}
            </button>
        </div>
    @else
        <form method="POST" action="{{ route('projekte.kopieren.store', $project) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500">{{ __('Vorlage') }}</label>
                <select
                    name="template_id"
                    x-model.number="templateId"
                    @change="$refs.title.value = has('title') ? {{ \Illuminate\Support\Js::from($project->title) }} : ''"
                    class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                >
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-gray-500">{{ __('Anzahl Kopien') }}</label>
                <input type="number" name="count" x-model.number="count" min="1" max="{{ $tenant->max_project_copies }}" required class="mt-0.5 w-24 rounded-md border-gray-300 text-sm">
            </div>

            <div>
                <label class="block text-xs text-gray-500">{{ __('Bezeichnung') }}</label>
                <input
                    type="text"
                    name="title"
                    x-ref="title"
                    value="{{ $templateFieldKeys->get($firstTemplateId, collect())->contains('title') ? $project->title : '' }}"
                    required
                    class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                >
                <p class="mt-0.5 text-xs text-gray-400" x-show="count > 1" x-cloak>{{ __('Bei mehreren Kopien wird automatisch „ Kopie 1“, „ Kopie 2“ usw. angehängt.') }}</p>
            </div>

            <label class="flex items-center gap-1.5 text-gray-700" x-show="has('version')" x-cloak>
                <input type="checkbox" name="increment_version" value="1" class="rounded border-gray-300">
                {{ __('Version um 1 erhöhen') }}
            </label>

            @if ($inactivePeopleNames->isNotEmpty())
                <div class="rounded-md bg-amber-50 px-2 py-1.5 text-xs text-amber-800" x-show="has('project_people')" x-cloak>
                    {{ __('Achtung: folgende Projektbeteiligte sind inaktiv: :names', ['names' => $inactivePeopleNames->implode(', ')]) }}
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-copy' }))"
                    class="rounded border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Abbrechen') }}
                </button>
                <button type="submit" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                    {{ __('Kopieren') }}
                </button>
            </div>
        </form>
    @endif
</div>
