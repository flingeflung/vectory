@props(['project', 'status', 'source' => 'sv', 'suggestedFolderName' => null])

<span
    x-data="{ copied: false }"
    class="inline-flex shrink-0 items-center gap-1 text-gray-400"
    data-directory-status
    data-project-id="{{ $project->id }}"
>
    @switch($status['status'])
        @case('found')
            {{--
                Ralf-Bug-Report, 2026-09-14: Icons wurden in der PN-Spalte
                nur in der Breite auf 1-2px zusammengequetscht (Höhe blieb
                korrekt bei h-4) - klassisches Flex-Shrink-Problem: dieser
                Button sitzt (verschachtelt) in zwei inline-flex-Containern
                (dieser hier + der äußere in rows.blade.php), Flex-Items
                schrumpfen dort standardmäßig, sobald der verfügbare Platz
                knapp wird (mehr Spalten = schmalere PN-Zelle). shrink-0 auf
                Button UND Bild verhindert das.
            --}}
            <button
                type="button"
                @click="
                    navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($status['path']) }});
                    copied = true;
                    setTimeout(() => copied = false, 1500);
                "
                class="shrink-0 hover:opacity-75"
                title="{{ $status['archived'] ? __('Pfad des Projektverzeichnisses (Archiv) in die Zwischenablage kopieren') : __('Pfad des Projektverzeichnisses in die Zwischenablage kopieren') }}"
            >
                <img x-show="!copied" src="{{ asset('images/directory-status/copy2clipboard.png') }}" alt="" class="h-4 w-4 shrink-0 object-contain">
                <img x-show="copied" x-cloak src="{{ asset('images/directory-status/copy2clipboard_ok.png') }}" alt="" class="h-4 w-4 shrink-0 object-contain">
            </button>
            <button
                type="button"
                onclick="window.openProjectDirectoryContent({{ $project->id }})"
                class="shrink-0 hover:opacity-75"
                title="{{ $status['archived'] ? __('Verzeichnisinhalt auflisten (Archiv)') : __('Verzeichnisinhalt auflisten') }}"
            >
                <img src="{{ asset('images/directory-status/'.($status['archived'] ? 'show_dircontentArchiv.png' : 'show_dircontent.png')) }}" alt="" class="h-4 w-4 shrink-0 object-contain">
            </button>
        @break

        @case('not_found')
            @if ($source === 'av')
                {{-- Ralf, 2026-09-26: im Arbeitsverzeichnis wird nie von Hand angelegt, nur per Auschecken aus dem SV. --}}
                <span title="{{ __('Noch nicht im Arbeitsverzeichnis - Daten gelangen erst durch Auschecken aus dem gesperrten Verzeichnis dorthin.') }}" class="shrink-0 opacity-30">
                    <img src="{{ asset('images/directory-status/show_directory0.png') }}" alt="" class="h-4 w-4 shrink-0 object-contain grayscale">
                </span>
            @elseif ($suggestedFolderName)
                <button
                    type="button"
                    onclick="window.openProjectDirectoryCreate({{ $project->id }}, {{ \Illuminate\Support\Js::from($suggestedFolderName) }})"
                    class="shrink-0 hover:opacity-75"
                    title="{{ __('Kein Projektverzeichnis vorhanden - anlegen') }}"
                >
                    <img src="{{ asset('images/directory-status/show_directory0.png') }}" alt="" class="h-4 w-4 shrink-0 object-contain">
                </button>
            @else
                {{-- Anders als der Button oben (Detailansicht) hier bewusst
                     nicht klickbar - Anlegen geht nur aus den Projektdetails
                     heraus. Sichtbar abgeblasst, damit nicht wie ein
                     Button aussieht, der nichts tut (Ralf: "gleiche Buttons
                     = selbe Funktionalität"). --}}
                <span title="{{ __('Kein Projektverzeichnis vorhanden - Anlegen nur in den Projektdetails möglich.') }}" class="shrink-0 opacity-30">
                    <img src="{{ asset('images/directory-status/show_directory0.png') }}" alt="" class="h-4 w-4 shrink-0 object-contain grayscale">
                </span>
            @endif
        @break

        @case('ambiguous')
            <span class="shrink-0 text-red-500" title="{{ __('Achtung: Es existieren mehrere Verzeichnisse mit dieser Projektnummer.') }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
        @break

        @case('unreachable')
            <span class="shrink-0 text-red-500" title="{{ __('Projektpfad nicht erreichbar. Basisverzeichnis unter :location prüfen.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
        @break

        @default {{-- not_configured --}}
            <span class="shrink-0" title="{{ __('Kein Projektpfad in :location hinterlegt.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M4.5 9.75V6.75A2.25 2.25 0 016.75 4.5h4.5l1.5 1.5h5.5a2.25 2.25 0 012.25 2.25v1.5" />
                </svg>
            </span>
    @endswitch
</span>
