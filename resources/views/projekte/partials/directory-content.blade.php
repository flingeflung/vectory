@php
    $sourceLabels = [
        'av' => __('Arbeitsverzeichnis'),
        'sv' => __('Gesperrtes Verzeichnis (Vectory)'),
    ];
@endphp

{{--
    Reiter nur, wenn der Nutzer mehr als ein Verzeichnis sehen darf (Ralf,
    2026-09-26): AV immer (falls konfiguriert), SV nur mit Recht
    project.directory.locked - ohne Recht wird das SV gar nicht erst
    angeboten.
--}}
@if (count($sources) > 1)
    <div class="mb-3 flex gap-1 border-b border-gray-200">
        @foreach ($sources as $tab)
            <button
                type="button"
                onclick="window.switchProjectDirectoryTab({{ $project->id }}, {{ \Illuminate\Support\Js::from($tab) }})"
                class="-mb-px border-b-2 px-3 py-1.5 text-xs font-medium {{ $tab === $source ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                {{ $sourceLabels[$tab] }}
            </button>
        @endforeach
    </div>
@else
    <div class="mb-2 text-xs font-medium text-gray-500">{{ $sourceLabels[$source] }}</div>
@endif

@switch ($status['status'])
    @case ('found')
        <div class="mb-2 flex items-center justify-between gap-2 text-xs text-gray-500">
            {{-- Ralf, 2026-09-22: nur der Ordnername wird angezeigt, nicht der volle Server-Pfad
                 (der legt intern Hosting-Verzeichnisstruktur offen) - "Pfad kopieren" liefert den
                 vollen Pfad weiterhin in die Zwischenablage. --}}
            <span class="truncate" title="{{ __('Vollständigen Pfad über „Pfad kopieren" sichern.') }}">{{ basename($status['path']) }}{{ $status['archived'] ? ' ('.__('Archiv').')' : '' }}</span>
            <button
                type="button"
                onclick="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($status['path']) }})"
                class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Pfad kopieren') }}
            </button>
        </div>
        <x-directory-tree :nodes="$contents" root />
    @break

    @case ('not_found')
        <div class="space-y-2 text-sm text-gray-600">
            <div>{{ __('Im :verzeichnis gibt es keinen Ordner, der mit :pn beginnt.', ['verzeichnis' => $sourceLabels[$source], 'pn' => $project->source_pn]) }}</div>
            @if ($source === 'av')
                {{-- Ralf, 2026-09-26: das AV wird nie von Hand befüllt - Daten kommen nur per Auschecken aus dem SV. --}}
                <div class="text-xs text-gray-400">{{ __('Daten gelangen erst durch Auschecken aus dem gesperrten Verzeichnis ins Arbeitsverzeichnis.') }}</div>
            @else
                <div class="text-xs text-gray-400">{{ __('Vorschlag für den Ordnernamen') }}: {{ $suggestedFolderName }}</div>
                @can ('project.edit')
                    <button
                        type="button"
                        onclick="window.openProjectDirectoryCreate({{ $project->id }}, {{ \Illuminate\Support\Js::from($suggestedFolderName) }})"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                    >
                        {{ __('Projektordner anlegen') }}
                    </button>
                @endcan
            @endif
        </div>
    @break

    @case ('ambiguous')
        <div class="text-sm text-red-600">{{ __('Achtung: Es existieren mehrere Verzeichnisse mit dieser Projektnummer.') }}</div>
    @break

    @case ('unreachable')
        <div class="text-sm text-red-600">{{ __('Das Basisverzeichnis ist nicht erreichbar. Bitte den Pfad unter :location prüfen.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}</div>
    @break

    @default
        <div class="text-sm text-gray-400">{{ __('Kein Verzeichnis hinterlegt.') }}</div>
@endswitch
