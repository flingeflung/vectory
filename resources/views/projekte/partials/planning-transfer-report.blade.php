{{-- Bericht nach "Planung übertragen": je Zielprojekt, was passiert ist. Erwartet $project, $rows, $direction. --}}
<div class="space-y-3 text-sm">
    @if (count($rows) === 0)
        <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">{{ __('Es gab kein Zielprojekt, auf das die Planung übertragen werden konnte.') }}</p>
    @else
        <p class="text-gray-600">{{ __('Fertig. Übertragen auf :n Projekt(e).', ['n' => count($rows)]) }}</p>
        <div class="space-y-2">
            @foreach ($rows as $row)
                <div class="rounded-md border border-gray-200 px-3 py-2">
                    <p class="font-medium text-gray-900">{{ $row['target']->source_pn }} <span class="font-normal text-gray-500">{{ $row['target']->title }}</span></p>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        @foreach ($row['results'] as $result)
                            <li class="flex gap-1.5 {{ $result['status'] === 'done' ? 'text-green-700' : ($result['status'] === 'skipped' ? 'text-amber-700' : 'text-gray-500') }}">
                                <span aria-hidden="true">{{ $result['status'] === 'done' ? '✓' : ($result['status'] === 'skipped' ? '!' : '–') }}</span>
                                <span>@if ($result['part'] !== '')<span class="font-medium">{{ $result['part'] }}:</span> @endif{{ $result['note'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
    <div class="flex justify-end border-t border-gray-200 pt-3">
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'planning-transfer' }))" class="whitespace-nowrap rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Schließen') }}</button>
    </div>
</div>
