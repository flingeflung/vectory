@php
    $urgencyClasses = [
        'blocked' => 'border-red-200 bg-red-50 text-red-700',
        'critical' => 'border-orange-300 bg-orange-50 text-orange-800',
        'watch' => 'border-slate-300 bg-slate-100 text-slate-700',
    ];
@endphp

<x-modal name="project-error-check-{{ $project->id }}" max-width="3xl" :draggable="true">
    <div data-drag-handle class="flex cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
        <div>
            <h3 class="font-semibold text-gray-900">{{ __('Fehlercheck') }}</h3>
            <p class="text-xs text-gray-500">{{ $project->source_pn }} – {{ $project->title }}</p>
        </div>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-error-check-{{ $project->id }}' }))" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div class="max-h-[70vh] overflow-y-auto p-4 text-sm">
        @if (in_array((int) $project->status, [2, 3], true))
            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-3 text-gray-600">
                {{ __('Beendete und verworfene Projekte werden nicht mehr geprüft.') }}
            </div>
        @elseif ($findings->isEmpty())
            <div class="rounded-md border border-green-200 bg-green-50 px-3 py-3 text-green-700">
                {{ __('Die derzeit hinterlegten Prüfregeln haben keine Befunde ergeben.') }}
            </div>
        @else
            <div class="mb-2 text-xs text-gray-500">
                {{ trans_choice(':count Befund|:count Befunde', $findings->count(), ['count' => $findings->count()]) }}
            </div>
            <div class="space-y-3">
                @foreach ($findings->sortByDesc('rank') as $finding)
                    <section class="rounded-md border border-gray-200 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold {{ $urgencyClasses[$finding['severity']] }}">{{ $finding['severity_label'] }}</span>
                            <span class="text-xs text-gray-400">{{ $finding['area'] }}</span>
                            <h4 class="font-semibold text-gray-900">{{ $finding['title'] }}</h4>
                        </div>
                        <p class="mt-1.5 text-gray-700">{{ $finding['detail'] }}</p>
                        <p class="mt-1 text-xs text-gray-500"><span class="font-medium">{{ __('Mögliche Lösung') }}:</span> {{ $finding['solution'] }}</p>
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex justify-end border-t border-gray-100 p-3">
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-error-check-{{ $project->id }}' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Schließen') }}</button>
    </div>
</x-modal>
