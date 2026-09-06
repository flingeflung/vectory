@props(['project', 'status', 'suggestedFolderName' => null])

<span
    x-data="{ copied: false }"
    class="inline-flex items-center gap-1 text-gray-400"
    data-directory-status
    data-project-id="{{ $project->id }}"
>
    @switch($status['status'])
        @case('found')
            <button
                type="button"
                @click="
                    navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($status['path']) }});
                    copied = true;
                    setTimeout(() => copied = false, 1500);
                "
                class="hover:text-gray-700"
                title="{{ $status['archived'] ? __('Pfad des Projektverzeichnisses (Archiv) in die Zwischenablage kopieren') : __('Pfad des Projektverzeichnisses in die Zwischenablage kopieren') }}"
            >
                <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M4.5 9.75V6.75A2.25 2.25 0 016.75 4.5h4.5l1.5 1.5h5.5a2.25 2.25 0 012.25 2.25v1.5" />
                </svg>
                <svg x-show="copied" x-cloak class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </button>
            <button
                type="button"
                onclick="window.openProjectDirectoryContent({{ $project->id }})"
                class="hover:text-gray-700"
                title="{{ $status['archived'] ? __('Verzeichnisinhalt auflisten (Archiv)') : __('Verzeichnisinhalt auflisten') }}"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                </svg>
            </button>
        @break

        @case('not_found')
            @if ($suggestedFolderName)
                <button
                    type="button"
                    onclick="window.openProjectDirectoryCreate({{ $project->id }}, {{ \Illuminate\Support\Js::from($suggestedFolderName) }})"
                    class="hover:text-gray-700"
                    title="{{ __('Kein Projektverzeichnis vorhanden - anlegen') }}"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M4.5 9.75V6.75A2.25 2.25 0 016.75 4.5h4.5l1.5 1.5h5.5a2.25 2.25 0 012.25 2.25v1.5M12 13.5v3.75m1.875-1.875H10.125" />
                    </svg>
                </button>
            @else
                <span title="{{ __('Kein Projektverzeichnis vorhanden.') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M4.5 9.75V6.75A2.25 2.25 0 016.75 4.5h4.5l1.5 1.5h5.5a2.25 2.25 0 012.25 2.25v1.5" />
                    </svg>
                </span>
            @endif
        @break

        @case('ambiguous')
            <span class="text-red-500" title="{{ __('Achtung: Es existieren mehrere Verzeichnisse mit dieser Projektnummer.') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
        @break

        @case('unreachable')
            <span class="text-red-500" title="{{ __('Projektpfad nicht erreichbar. Basisverzeichnis unter Admin > Konfig prüfen.') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
        @break

        @default {{-- not_configured --}}
            <span title="{{ __('Kein Projektpfad in Admin > Konfig hinterlegt.') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M4.5 9.75V6.75A2.25 2.25 0 016.75 4.5h4.5l1.5 1.5h5.5a2.25 2.25 0 012.25 2.25v1.5" />
                </svg>
            </span>
    @endswitch
</span>
