@php $stammInfo = $project->stammPositionInfo(); @endphp
<div>
    {{-- Ralf, 2026-09-20: "Stamm-Version" = unsere Zählung (Position in der Versionskette), immer
         vorhanden und schreibgeschützt. Die Version, wie der KUNDE sie nennt, ist ein normales
         Zusatzfeld (Standard: "Kundenversion"), das jeder Kunde selbst festlegt oder weglässt. --}}
    <label class="block text-xs text-gray-500">{{ __('Stamm-Version') }}</label>
    <div class="mt-0.5 flex items-center gap-2">
        <div class="rounded border border-gray-200 bg-gray-50 px-2 py-1 text-sm text-gray-700" title="{{ __('Position dieses Projekts in der Versionskette (Zählung von Vectory, unabhängig von der Kundenversion)') }}">
            {{ __(':n von :total', ['n' => $stammInfo['position'], 'total' => $stammInfo['total']]) }}
        </div>
        {{-- Zugang zur Versionsübersicht neben der Stamm-Version (wie in Vietto neben der Version)
             und IMMER da, auch bei nur einer Version. --}}
        @if ($project->stamm_id)
            <button
                type="button"
                onclick="window.openStammIdChain({{ $project->id }})"
                class="shrink-0 rounded border border-gray-300 bg-btn-secondary p-1 text-gray-500 hover:bg-btn-secondary-hover hover:text-gray-700"
                title="{{ __('Versionsübersicht öffnen') }}"
                aria-label="{{ __('Versionsübersicht öffnen') }}"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        @endif
    </div>
</div>
