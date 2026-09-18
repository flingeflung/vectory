{{--
    Ralf, 2026-09-19: reine Anzeige der Merkmale/Stunden einer Projekt-
    schablone, aufgerufen aus den Projektdetails heraus (Info-Button neben
    dem Projektschablone-Pulldown). Rendert dieselben Felder wie das
    Anlegen-/Bearbeiten-Formular in admin/project-templates/partials/
    fields.blade.php, aber rein lesend (kein Formular, keine Rechte-
    Voraussetzung für die Admin-Verwaltung nötig).
--}}
<div class="space-y-3">
    <div>
        <div class="text-sm font-medium text-gray-900">{{ $template->name }}</div>
        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
            <span>{{ __('Format') }}: {{ \App\Models\ProjectTemplate::formatOptions()[$template->format] ?? '–' }}</span>
            <span>{{ __('Dauer') }}: {{ rtrim(rtrim((string) $template->duration_value, '0'), '.') }} {{ \App\Models\ProjectTemplate::durationUnitOptions()[$template->duration_unit] }}</span>
            <span>{{ __('Workflow') }}: {{ $template->workflow?->name ?? __('– keiner –') }}</span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach (\App\Models\ProjectTemplate::characteristicFields() as $field => $meta)
            @php($option = $meta['options'][$template->$field] ?? null)
            <div>
                <div class="text-xs text-gray-500">{{ $meta['label'] }}</div>
                <div
                    @class([
                        'mt-0.5 rounded-md border px-2 py-1 text-xs',
                        'border-green-300 bg-green-50 text-green-800' => ($option['color'] ?? null) === 'green',
                        'border-lime-300 bg-lime-50 text-lime-800' => ($option['color'] ?? null) === 'lime',
                        'border-amber-300 bg-amber-50 text-amber-800' => ($option['color'] ?? null) === 'amber',
                        'border-red-300 bg-red-50 text-red-800' => ($option['color'] ?? null) === 'red',
                        'border-gray-300 bg-gray-50 text-gray-600' => ($option['color'] ?? null) === 'gray' || ! $option,
                    ])
                >{{ $option['label'] ?? '–' }}</div>
            </div>
        @endforeach
    </div>

    @if ($template->remarks)
        <div>
            <div class="text-xs text-gray-500">{{ __('Bemerkungen') }}</div>
            <div class="mt-0.5 whitespace-pre-line text-sm text-gray-700">{{ $template->remarks }}</div>
        </div>
    @endif

    @if ($template->functionGroups->isNotEmpty())
        <div class="border-t border-gray-100 pt-2">
            <div class="text-xs font-medium text-gray-600">{{ __('Stunden je Funktionsgruppe') }}</div>
            <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($template->functionGroups as $fg)
                    <div class="flex items-center justify-between gap-1 rounded-md border border-gray-200 px-1.5 py-1 text-xs text-gray-600">
                        <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                        <span class="shrink-0 font-medium text-gray-900">{{ rtrim(rtrim((string) $fg->pivot->planned_hours, '0'), '.') }} h</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
