@php $stammInfo = $project->stammPositionInfo(); @endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Version') }}</label>
    <div class="mt-0.5 flex items-center gap-2">
        <input type="number" name="version" value="{{ old('version', $project->version) }}" class="w-full max-w-[80px] rounded border-gray-300 py-1 text-sm">
        {{-- Ralf, 2026-09-20: der Button zur Versionsübersicht gehört neben die
             Version (wie in Vietto) und steht IMMER da, auch bei nur einer
             Version - sonst fragt man sich, wann er erscheint. --}}
        @if ($project->stamm_id)
            <button
                type="button"
                onclick="window.openStammIdChain({{ $project->id }})"
                class="inline-flex items-center whitespace-nowrap rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                title="{{ __('Versionsübersicht öffnen') }}"
            >{{ $stammInfo['total'] > 1
                ? __('Position :n von :total in der Versionskette', ['n' => $stammInfo['position'], 'total' => $stammInfo['total']])
                : __('Versionskette: nur 1 Version') }}</button>
        @endif
    </div>
</div>
