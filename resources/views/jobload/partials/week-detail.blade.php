<div class="mb-2 font-medium text-gray-800">
    {{ __('KW') }} {{ $start->isoWeek() }}: {{ $start->format('d.m.Y') }} – {{ $start->addDays(6)->format('d.m.Y') }}
    <span class="block text-sm font-normal text-gray-600">{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</span>
</div>
<div class="overflow-x-auto">
    <table class="min-w-full border-collapse text-xs">
        <thead class="bg-gray-50 text-gray-600">
            <tr>
                <th class="border border-gray-200 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                @foreach ($days as $day)
                    <th class="border border-gray-200 px-2 py-1 text-center font-medium">{{ ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$loop->index] }}<br>{{ $day->format('d.m.') }}</th>
                @endforeach
                <th class="border border-gray-200 px-2 py-1 text-right font-medium">{{ __('Summe') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <th class="whitespace-nowrap border border-gray-200 px-2 py-1 text-left font-normal">{{ $row['name'] }}</th>
                    @foreach ($days as $day)
                        <td class="border border-gray-200 px-2 py-1 text-center tabular-nums">{{ isset($row['days'][$day->toDateString()]) ? number_format($row['days'][$day->toDateString()], $hourDecimals, ',', '.') : '' }}</td>
                    @endforeach
                    <td class="border border-gray-200 px-2 py-1 text-right font-medium tabular-nums">{{ number_format($row['total'], $hourDecimals, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="border border-gray-200 px-2 py-4 text-center text-gray-500">{{ __('Keine Stunden erfasst.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-50 font-semibold">
            <tr><th colspan="8" class="border border-gray-200 px-2 py-1.5 text-left">{{ __('Summe') }}</th><td class="border border-gray-200 px-2 py-1.5 text-right tabular-nums">{{ number_format($total, $hourDecimals, ',', '.') }}</td></tr>
        </tfoot>
    </table>
</div>
