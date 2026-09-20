@php $stammInfo = $project->stammPositionInfo(); @endphp
<div>
    {{-- Ralf, 2026-09-20: "Kundenversion" = die Version, wie der KUNDE sie nennt
         (Freitext, z.B. "V3", "1.2", "Rev. 04") - getrennt von unserer
         "Stamm-Version" (Position in der Versionskette, rechts daneben). --}}
    <label class="block text-xs text-gray-500">{{ __('Kundenversion') }}</label>
    <div class="mt-0.5 flex items-center gap-2">
        <input type="text" name="version" maxlength="50" value="{{ old('version', $project->version) }}" class="w-full max-w-[120px] rounded border-gray-300 py-1 text-sm">
        {{-- Der Zugang zur Versionsübersicht steht neben der Version (wie in Vietto)
             und IMMER da, auch bei nur einer Version. Bewusst getrennt: das Symbol
             ist die AKTION (öffnet die Übersicht), der Text daneben nur die
             INFORMATION (Stamm-Version) - beides in einem Button zu mischen ist
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
            <span class="whitespace-nowrap text-xs text-gray-500" title="{{ __('Position dieses Projekts in der Versionskette (Zählung von Vectory, unabhängig von der Kundenversion)') }}">
                {{ __('Stamm-Version :n von :total', ['n' => $stammInfo['position'], 'total' => $stammInfo['total']]) }}
            </span>
        @endif
    </div>
</div>
