{{--
    Ralf, 2026-09-20 (Stamm-ID): Versionsübersicht eines Dokuments - Vorbild
    Viettos Dialog "Versionen mit dieser Mat.-Nr./ODN", nur dass die Kette hier
    über die Stamm-ID läuft und nicht über eine Kundennummer. Reine Anzeige plus
    (mit Recht) "Aus der Kette lösen".
--}}
<div class="space-y-3">
    <div class="text-xs text-gray-500">
        {{ __('Stamm-ID') }}: <span class="font-mono font-medium text-gray-800">{{ \App\Support\StammId::format($project->stamm_id) }}</span>
    </div>

    <div class="max-h-80 overflow-auto rounded-md border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="sticky top-0 bg-gray-50">
                <tr>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">#</th>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Projekt') }}</th>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Version') }}</th>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Erstellungsstatus') }}</th>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500" title="{{ __('Publikationsdatum') }}">{{ __('PD') }}</th>
                    <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($chain as $member)
                    <tr @class(['bg-indigo-50' => $member->id === $project->id])>
                        <td class="px-2 py-1.5 text-gray-400">{{ $loop->iteration }}</td>
                        <td class="px-2 py-1.5">
                            <a
                                href="#"
                                class="font-medium text-gray-900 hover:underline"
                                onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('close-modal', { detail: 'stamm-id-chain' })); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $member->id }} } }));"
                            >{{ $member->source_pn }}</a>
                            <span class="text-gray-600">{{ $member->title }}</span>
                            @if ($member->id === $project->id)
                                <span class="ml-1 text-[11px] text-indigo-600">{{ __('(angezeigt)') }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $member->version ?? '–' }}</td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $member->creation_type_label ?: '–' }}</td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $member->publication_date?->format('d.m.Y') ?? '–' }}</td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $member->status_label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($canManage && $chain->count() > 1)
        <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-3">
            <p class="text-xs text-gray-500">{{ __('Wurde dieses Projekt fälschlich als neue Version angelegt? Dann lösen Sie es aus der Kette; es erhält eine eigene Stamm-ID.') }}</p>
            <button
                type="button"
                onclick="window.detachStammId({{ $project->id }})"
                class="inline-flex shrink-0 items-center rounded-md border border-red-300 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
            >{{ __('Aus der Kette lösen') }}</button>
        </div>
    @endif
</div>
