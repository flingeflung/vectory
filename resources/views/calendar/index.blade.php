<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Kalender') }}</h2>
    </x-slot>

    <div class="flex h-full flex-col gap-2 p-2 sm:p-3">
        <div class="flex shrink-0 flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <form method="GET" action="{{ route('kalender') }}" class="flex items-center gap-2">
                <input type="hidden" name="month" value="{{ $month }}">
                <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($years as $itemYear)
                            <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            <div class="flex items-center gap-3">
                @if ($previousMonth)
                    <a href="{{ route('kalender', ['year' => $previousMonth->year, 'month' => $previousMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Voriger Monat') }}" aria-label="{{ __('Voriger Monat') }}">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                <span class="min-w-36 text-center font-semibold text-gray-800">{{ $monthStart->translatedFormat('F Y') }}</span>
                @if ($nextMonth)
                    <a href="{{ route('kalender', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Nächster Monat') }}" aria-label="{{ __('Nächster Monat') }}">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-max border-collapse text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 text-gray-700">
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($weekSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold" title="{{ $segment['year'] }}">
                                {{ __('KW') }} {{ $segment['week'] }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                        @foreach ($days as $day)
                            <th class="min-w-10 border-b border-r border-gray-200 px-1 py-1 text-center font-medium {{ $day->isWeekend() ? 'bg-[#fffaeb]' : '' }}" title="{{ $day->translatedFormat('l, d.m.Y') }}">
                                <span class="block text-[10px] text-gray-400">{{ $day->translatedFormat('D') }}</span>
                                <span class="block tabular-nums">{{ $day->format('d') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="{{ $days->count() + 1 }}" class="px-4 py-8 text-center text-gray-400">
                            {{ __('Noch keine Einträge vorhanden.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
