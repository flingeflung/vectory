<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Illustrationen') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <form method="GET" action="{{ route('illustrationen') }}" class="mb-3 flex shrink-0 flex-wrap items-start gap-x-6 gap-y-3 text-sm">
                <x-filter-dropdown label="{{ __('Status') }} ({{ $selectedStatuses->count() }}/{{ $statuses->count() }})">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500">{{ __('Status') }}</span>
                        <span class="flex gap-2 text-xs">
                            <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true)" class="text-indigo-600 hover:underline">{{ __('Alle') }}</button>
                            <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false)" class="text-indigo-600 hover:underline">{{ __('Keiner') }}</button>
                        </span>
                    </div>
                    <div class="max-h-56 space-y-1 overflow-y-auto">
                        @foreach ($statuses as $status)
                            <label class="flex items-center gap-1.5 text-gray-700">
                                <input type="checkbox" name="status[]" value="{{ $status->id }}" class="rounded border-gray-300" @checked($selectedStatuses->contains($status->id))>
                                {{ $status->name }}
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-2 w-full rounded bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">{{ __('Anwenden') }}</button>
                </x-filter-dropdown>

                <x-filter-dropdown label="{{ __('Illustrator') }} ({{ $selectedIllustrators->count() }})">
                    <div x-data="{ showInactive: false }">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500">{{ __('Illustrator') }}</span>
                            <span class="flex gap-2 text-xs">
                                <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox][name^=illustrator]').forEach(cb => cb.checked = true)" class="text-indigo-600 hover:underline">{{ __('Alle') }}</button>
                                <button type="button" onclick="this.closest('[x-data]').querySelectorAll('input[type=checkbox][name^=illustrator]').forEach(cb => cb.checked = false)" class="text-indigo-600 hover:underline">{{ __('Keiner') }}</button>
                            </span>
                        </div>
                        <div class="max-h-56 space-y-1 overflow-y-auto">
                            <label class="flex items-center gap-1.5 text-gray-700">
                                <input type="checkbox" name="illustrator[]" value="none" class="rounded border-gray-300" @checked($selectedIllustrators->contains('none'))>
                                {{ __('– nicht zugewiesen –') }}
                            </label>
                            @foreach ($illustrationPersons as $person)
                                <label
                                    class="flex items-center gap-1.5"
                                    @class(['text-gray-400' => ! $person->active])
                                    @unless ($person->active) x-show="showInactive" x-cloak @endunless
                                >
                                    <input type="checkbox" name="illustrator[]" value="{{ $person->id }}" class="rounded border-gray-300" @checked($selectedIllustrators->contains($person->id))>
                                    {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                                </label>
                            @endforeach
                        </div>
                        {{-- Nur ein Anzeige-Umschalter, kein eigener Submit - die
                             Checkboxen der inaktiven Personen sind schon im Formular
                             (Standard: mit angehakt), nur unsichtbar. So bleibt "alle
                             (auch inaktive) ausgewählt" der Default, ohne dass ihn ein
                             Klick auf diesen Umschalter verändert. --}}
                        <label class="mt-2 flex items-center gap-1.5 border-t border-gray-100 pt-2 text-xs text-gray-600">
                            <input type="checkbox" x-model="showInactive" class="rounded border-gray-300">
                            {{ __('Inaktive auch anzeigen') }}
                        </label>
                    </div>
                    <button type="submit" class="mt-2 w-full rounded bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">{{ __('Anwenden') }}</button>
                </x-filter-dropdown>

                @php $selectedInitiator = $initiatorOptions->firstWhere('id', (int) $initiatorId); @endphp
                <x-filter-dropdown label="{{ __('Auftraggeber') }}{{ $selectedInitiator ? ': '.$selectedInitiator->fullName() : '' }}">
                    <div class="mb-2 text-xs font-medium text-gray-500">{{ __('Auftraggeber') }}</div>
                    <div class="max-h-56 space-y-1 overflow-y-auto">
                        <label class="flex items-center gap-1.5 text-gray-700">
                            <input type="radio" name="initiator" value="" class="border-gray-300" @checked(! $initiatorId)>
                            {{ __('– Alle –') }}
                        </label>
                        @foreach ($initiatorOptions as $person)
                            <label class="flex items-center gap-1.5 @class(['text-gray-400' => ! $person->active])">
                                <input type="radio" name="initiator" value="{{ $person->id }}" class="border-gray-300" @checked($initiatorId == $person->id)>
                                {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-2 w-full rounded bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">{{ __('Anwenden') }}</button>
                </x-filter-dropdown>

                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('Termin von') }}:</span>
                    <input type="date" name="due_from" value="{{ $dueFrom }}" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                </label>
                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('bis') }}:</span>
                    <input type="date" name="due_to" value="{{ $dueTo }}" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                </label>

                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('PN/Illu-Nr.') }}:</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Suche') }}" class="w-32 rounded-md border-gray-300 py-1 text-sm">
                </label>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200">
                        {{ __('Anwenden') }}
                    </button>
                    <a href="{{ route('illustrationen') }}" class="text-xs text-gray-500 hover:text-gray-700">
                        {{ __('Filter zurücksetzen') }}
                    </a>
                </div>
            </form>

            <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                {{ __(':count Auftrag/Aufträge gefunden', ['count' => $orders->count()]) }}
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col">
                <div class="flex-1 min-h-0 overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Auftr.-Nr.') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Projekt') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap" title="{{ __('Anzahl Bilder') }}">{{ __('Anz.') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Status') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Aufgabe') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Termin') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Auftrag von') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Illustrator') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Firma') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('erledigt') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($orders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">Illu-{{ $order->id }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        <x-pn-link :project="$order->project" />
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $order->image_count }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $order->status?->name }}</td>
                                    <td class="px-4 py-2 max-w-xs text-gray-900">
                                        <span x-data="{ expanded: false }">
                                            <span x-show="!expanded" @click="expanded = true" class="cursor-pointer" title="{{ __('Klicken zum Erweitern') }}">
                                                {{ \Illuminate\Support\Str::limit($order->description, 30) }}
                                            </span>
                                            <span x-show="expanded" x-cloak @click="expanded = false" class="cursor-pointer">
                                                {{ $order->description }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $order->due_date?->format('d.m.Y') ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        {{ $order->initiatedBy?->fullName() ?? '–' }}
                                        <div class="text-xs text-gray-400">{{ $order->created_at?->format('d.m.Y H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        @if ($order->illustrator)
                                            <span @class(['text-gray-400' => ! $order->illustrator->active])>{{ $order->illustrator->fullName() }}{{ ! $order->illustrator->active ? ' [i]' : '' }}</span>
                                        @else
                                            &ndash;
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $order->illustrator?->company?->short_name ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $order->done_at?->format('d.m.Y') ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="window.openIllustrationOrders({{ $order->project_id }})"
                                            class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            {{ __('Ändern') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-6 text-center text-gray-500">{{ __('– Keine Aufträge mit den gewählten Filtereinstellungen gefunden –') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
