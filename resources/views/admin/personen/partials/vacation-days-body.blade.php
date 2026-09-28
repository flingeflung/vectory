{{--
    Urlaubstage-Historie (Ralf, 2026-09-28) - gleiche Systematik wie
    weekly-hours-body.blade.php. Rein anhängbar: nur "Neuer Datensatz", keine
    Bearbeitung/Löschen bestehender Zeilen. Das Ende eines Datensatzes wird
    NIE hier eingegeben, sondern automatisch beim nächsten Datensatz gesetzt
    (siehe PersonController::storeVacationDays()).
--}}
@php
    $sorted = $history->sortBy(fn ($row) => $row->valid_from?->toDateString() ?? '0000-01-01')->values();
    $openEntry = $history->firstWhere('valid_to', null);
    $fmtDate = fn ($date) => $date?->format('d.m.Y') ?? '–';
    $fmtEnd = fn ($date) => $date?->format('d.m.Y') ?? '…';
    $fmtDays = fn ($days) => rtrim(rtrim(number_format((float) $days, 1, '.', ''), '0'), '.');
@endphp
<div class="space-y-3" x-data="{ adding: false }">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-xs text-gray-500">
                <th class="py-1 pr-3 text-left font-medium">{{ __('Von') }}</th>
                <th class="px-3 py-1 text-left font-medium">{{ __('Bis') }}</th>
                <th class="py-1 pl-3 text-right font-medium">{{ __('Urlaubstage') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sorted as $row)
                <tr class="border-b border-gray-100 {{ $row->valid_to === null ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                    <td class="py-1 pr-3">{{ $fmtDate($row->valid_from) }}</td>
                    <td class="px-3 py-1">{{ $fmtEnd($row->valid_to) }}</td>
                    <td class="py-1 pl-3 text-right tabular-nums">{{ $fmtDays($row->days) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-400">{{ __('Noch keine Historie vorhanden.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($canEdit)
        <div x-show="! adding">
            <button type="button" @click="adding = true; $nextTick(() => $refs.newDays.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                + {{ __('Neuer Datensatz') }}
            </button>
        </div>
        <form x-show="adding" x-cloak method="POST" action="{{ route('admin.personen.urlaubstage.store', $person) }}" class="flex flex-wrap items-end gap-2 rounded-md border border-gray-200 p-2">
            @csrf
            <div class="w-24">
                <label class="block text-xs text-gray-500">{{ __('Urlaubstage') }}</label>
                <input type="number" name="days" x-ref="newDays" min="0" max="100" step="0.5" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm @error('days') border-red-300 @enderror">
                @error('days')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            @if ($openEntry)
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Start') }}</label>
                    <input type="date" name="valid_from" required class="mt-0.5 rounded-md border-gray-300 text-sm @error('valid_from') border-red-300 @enderror">
                    @error('valid_from')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <p class="w-full text-xs text-gray-400">
                    {{ __('Der bisher letzte Datensatz (:days Tage, seit :date) endet automatisch am Vortag des neuen Starts.', ['days' => $fmtDays($openEntry->days), 'date' => $fmtDate($openEntry->valid_from)]) }}
                </p>
            @endif
            <button type="button" @click="adding = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Abbrechen') }}
            </button>
            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern') }}
            </button>
        </form>
    @endif
</div>
