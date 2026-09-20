@php $stammInfo = $project->stammPositionInfo(); @endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Version') }}</label>
    <div class="mt-0.5 flex items-center gap-2">
        <input type="number" name="version" value="{{ old('version', $project->version) }}" class="w-full max-w-[80px] rounded border-gray-300 py-1 text-sm">
        {{-- Ralf, 2026-09-20: der Zugang zur Versionsübersicht gehört neben die
             Version (wie in Vietto) und steht IMMER da, auch bei nur einer
             Version. Bewusst getrennt: das Symbol ist die AKTION (öffnet die
             Übersicht), der Text daneben nur die INFORMATION (Anzahl Versionen in
             der Kette) - Ralf: beides in einem breiten Button zu mischen ist
             ungewöhnlich. --}}
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
            <span class="whitespace-nowrap text-xs text-gray-500">{{ trans_choice(':count Version in der Kette|:count Versionen in der Kette', $stammInfo['total'], ['count' => $stammInfo['total']]) }}</span>
        @endif
    </div>
</div>
