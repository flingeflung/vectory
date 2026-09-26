<div>
    @if ($project->verbund_rolle === 2 && $project->hauptprojekt)
        <div class="mb-1.5 text-xs text-gray-500">
            {{ __('Ist Unterprojekt zu Hauptprojekt') }}
            <a
                href="{{ route('projekte.show', $navParams($project->hauptprojekt)) }}"
                @if ($isOverlay)
                    onclick="event.preventDefault(); window.confirmDiscardIfDirty('projectOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $project->hauptprojekt->id }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } })))"
                @endif
                class="font-medium text-indigo-600 hover:underline"
            >{{ $project->hauptprojekt->source_pn }} – {{ $project->hauptprojekt->title }}</a>
        </div>
    @elseif ($project->verbund_rolle === 1)
        <details class="mb-1.5 text-xs text-gray-500">
            <summary class="cursor-pointer select-none">{{ __('Dies ist ein Hauptprojekt mit folgenden Unterprojekten:') }}</summary>
            <div class="mt-1 max-h-36 space-y-0.5 overflow-y-auto pl-4">
                @forelse ($project->unterprojekte as $unterprojekt)
                    <div>
                        <a
                            href="{{ route('projekte.show', $navParams($unterprojekt)) }}"
                            @if ($isOverlay)
                                onclick="event.preventDefault(); window.confirmDiscardIfDirty('projectOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $unterprojekt->id }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } })))"
                            @endif
                            class="font-medium text-indigo-600 hover:underline"
                        >{{ $unterprojekt->source_pn }} – {{ $unterprojekt->title }}</a>
                    </div>
                @empty
                    <div>{{ __('Keine Unterprojekte zugeordnet.') }}</div>
                @endforelse
            </div>
        </details>
    @endif
    <label class="block text-xs text-gray-500">{{ __('Bezeichnung') }}<span class="text-red-500" title="{{ __('Pflichtfeld') }}"> *</span></label>
    <input type="text" name="title" value="{{ old('title', $project->title) }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm" required>
</div>
