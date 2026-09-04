@php
    $orders = $project->graphicOrders->sortByDesc('created_at');
@endphp

<div class="mb-3 text-xs text-gray-500">{{ __('Projekt') }} {{ $project->source_pn }}</div>

<div x-data="{ showNew: false }" class="mb-4">
    <button
        type="button"
        x-show="!showNew"
        @click="showNew = true"
        class="rounded border border-gray-300 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
    >
        {{ __('Neuer Auftrag') }}
    </button>

    <form
        x-show="showNew"
        x-cloak
        method="POST"
        action="{{ route('projekte.illustration-orders.store', $project) }}"
        class="mt-2 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3"
    >
        @csrf
        <div>
            <label class="text-xs text-gray-500">{{ __('Beschreibung') }}</label>
            <textarea name="description" rows="3" required class="mt-0.5 w-full rounded border-gray-300 text-sm"></textarea>
        </div>
        <div class="flex gap-3">
            <div>
                <label class="text-xs text-gray-500">{{ __('Anzahl Bilder') }}</label>
                <input type="number" name="image_count" min="0" class="mt-0.5 w-24 rounded border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">{{ __('Fertigstellung bis') }}</label>
                <input type="date" name="due_date" class="mt-0.5 rounded border-gray-300 text-sm">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded bg-gray-800 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700">
                {{ __('Auftrag speichern') }}
            </button>
            <button type="button" @click="showNew = false" class="rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                {{ __('Abbrechen') }}
            </button>
        </div>
    </form>
</div>

<div class="mt-3 space-y-2">
    @forelse ($orders as $order)
        @php
            $bgClass = 'bg-white';
            if ($order->status?->is_discarded) {
                $bgClass = 'bg-gray-50 text-gray-500';
            } elseif ($order->status && ! $order->status->is_open) {
                $bgClass = 'bg-green-50';
            }
        @endphp
        <div x-data="{ editing: false }" class="rounded-md border border-gray-200 p-2 {{ $bgClass }}">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="font-semibold">Illu-{{ $order->id }}</span>
                    <span class="text-xs text-gray-500">{{ __('von') }} {{ $order->initiatedBy?->fullName() ?? '–' }}, {{ $order->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <button
                    type="button"
                    @click="editing = !editing"
                    class="shrink-0 rounded border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                >
                    {{ __('Status ändern') }}
                </button>
            </div>
            <div class="mt-1">{{ __('Status') }}: {{ $order->status?->name }}</div>
            <div>{{ __('Illustrator') }}: {{ $order->illustrator?->fullName() ?? '–' }}{{ $order->illustrator && ! $order->illustrator->active ? ' [i]' : '' }}</div>
            @if ($order->description)
                <div class="mt-1 whitespace-pre-line text-gray-700">{{ $order->description }}</div>
            @endif
            <div class="mt-1 text-xs text-gray-600">
                {{ __('Anz. Bilder') }}: {{ $order->image_count }}
                @if ($order->due_date)
                    &middot; {{ __('fällig') }}: {{ $order->due_date->format('d.m.Y') }}
                @endif
            </div>
            @if ($order->done_at)
                <div class="mt-1 text-xs text-gray-600">
                    {{ __('erledigt von') }} {{ $order->completedBy?->fullName() ?? '–' }} {{ __('am') }} {{ $order->done_at->format('d.m.Y') }}
                </div>
            @endif

            <form
                x-show="editing"
                x-cloak
                x-data="{ illustrator: {{ \Illuminate\Support\Js::from((string) $order->illustrator_person_id) }} }"
                method="POST"
                action="{{ route('projekte.illustration-orders.update', [$project, $order]) }}"
                class="mt-2 flex flex-wrap items-end gap-2 border-t border-gray-200 pt-2"
            >
                @csrf
                @method('PATCH')
                <div>
                    <label class="text-xs text-gray-500">{{ __('Status') }}</label>
                    <select name="graphic_order_status_id" class="mt-0.5 rounded border-gray-300 py-1 text-xs">
                        @foreach ($graphicOrderStatuses as $status)
                            <option value="{{ $status->id }}" @selected($status->id === $order->graphic_order_status_id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">{{ __('Illustrator') }}</label>
                    <select name="illustrator_person_id" x-model="illustrator" class="mt-0.5 rounded border-gray-300 py-1 text-xs">
                        <option value="" @selected(! $order->illustrator_person_id)>&ndash; {{ __('nicht zugewiesen') }} &ndash;</option>
                        @foreach ($illustrationPersons as $person)
                            <option value="{{ $person->id }}" @selected($person->id === $order->illustrator_person_id) @class(['text-gray-400' => ! $person->active])>{{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button
                    type="button"
                    @click="illustrator = {{ \Illuminate\Support\Js::from((string) auth()->user()->person_id) }}"
                    class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ __('Mir zuweisen') }}
                </button>
                <button type="submit" class="rounded bg-gray-800 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700">
                    {{ __('Speichern') }}
                </button>
            </form>
        </div>
    @empty
        <div class="text-gray-400">&ndash; {{ __('Keine Aufträge vorhanden') }} &ndash;</div>
    @endforelse
</div>
