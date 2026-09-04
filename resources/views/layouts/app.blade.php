<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- Eigenständiges, leeres Teleport-Ziel für Modals (siehe components/modal.blade.php) -
             NICHT direkt nach body teleportieren: die Modal-<template>-Quellen selbst liegen
             ebenfalls als Kinder von body, das Anhängen eines Klons dorthin während desselben
             Baum-Durchlaufs ließ Alpine bei den letzten beiden Modals (project-overlay,
             favorites) den Teleport-Vorgang verlieren (reproduzierbar: die ersten beiden
             klappten, die letzten beiden nicht). Ein separates, von Anfang an leeres Ziel
             umgeht das. --}}
        <div id="modal-root"></div>
        <div
            x-data="{ sidebarOpen: localStorage.getItem('vectory-sidebar-open') !== 'false' }"
            x-init="$watch('sidebarOpen', value => localStorage.setItem('vectory-sidebar-open', value))"
            class="h-screen flex overflow-hidden bg-gray-100"
        >
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                @include('layouts.topbar')

                <!-- Page Heading -->
                @isset($header)
                    <div class="bg-white shadow-sm shrink-0">
                        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-confirm-dialog />

        {{-- Global Projekt-Detail-Overlay: von überall im Tool per PN-Klick (x-pn-link) öffenbar. --}}
        <x-modal name="project-overlay" max-width="2xl" :dirty-check="'projectOverlayIsDirty'" :draggable="true" :resizable="true">
            <div class="relative h-full">
                <div id="project-overlay-body" class="flex h-full min-h-0 flex-col text-sm text-gray-500">
                    <div class="p-4">{{ __('Lädt…') }}</div>
                </div>
                <div id="project-overlay-loading" class="absolute inset-0 hidden items-center justify-center bg-white/70">
                    <x-loading-spinner class="h-8 w-8 text-gray-400" />
                </div>
            </div>
        </x-modal>

        {{-- Global Favoriten-Overlay: über den Sidebar-Button erreichbar. --}}
        <x-modal name="favorites" max-width="md">
            <div class="p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">{{ __('Favoriten') }}</h2>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'favorites' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="favorites-body" class="max-h-96 overflow-y-auto text-sm text-gray-500">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const favoritesBody = () => document.getElementById('favorites-body');

                window.addEventListener('open-modal', async (event) => {
                    if (event.detail !== 'favorites') {
                        return;
                    }

                    favoritesBody().innerHTML = {{ \Illuminate\Support\Js::from(__('Lädt…')) }};
                    favoritesBody().innerHTML = await fetch({{ \Illuminate\Support\Js::from(route('favoriten')) }}).then((r) => r.text());
                });
            })();
        </script>

        <script>
            (function () {
                const body = () => document.getElementById('project-overlay-body');
                const loading = () => document.getElementById('project-overlay-loading');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let savedSnapshot = null;

                const showLoading = () => loading().classList.replace('hidden', 'flex');
                const hideLoading = () => loading().classList.replace('flex', 'hidden');

                const serializeForm = (form) => form ? new URLSearchParams(new FormData(form)).toString() : null;

                const snapshot = () => {
                    savedSnapshot = serializeForm(body().querySelector('form'));
                };

                window.projectOverlayIsDirty = () => {
                    const current = serializeForm(body().querySelector('form'));
                    return current !== null && current !== savedSnapshot;
                };

                // PHP-kompatible bracket-Notation (filter[key]=x, filter[key][from]=y) für URLSearchParams.
                const appendNested = (params, prefix, value) => {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }
                    if (typeof value === 'object') {
                        Object.entries(value).forEach(([k, v]) => appendNested(params, `${prefix}[${k}]`, v));
                        return;
                    }
                    params.append(prefix, value);
                };

                window.addEventListener('open-project', async (event) => {
                    const { id, sort, direction, filters } = event.detail;
                    if (!id) {
                        return;
                    }

                    // Alten Inhalt stehen lassen, nur ein Spinner-Overlay drüberlegen -
                    // kein Leeren/"Zucken" mehr beim Blättern zwischen Projekten.
                    showLoading();
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'project-overlay' }));

                    // Teleportierte Modals aus dem VORHERIGEN Overlay-Inhalt (z.B.
                    // Illustrationsaufträge, siehe components/modal.blade.php) hängen
                    // an #modal-root, nicht an #project-overlay-body - werden also vom
                    // gleich folgenden body().innerHTML NICHT mit entsorgt. Ohne dieses
                    // Aufräumen sammeln sich bei jedem Projektwechsel weitere Klone samt
                    // eigener window-Listener an.
                    document.getElementById('modal-root').innerHTML = '';

                    const params = new URLSearchParams();
                    if (sort) params.set('sort', sort);
                    if (direction) params.set('direction', direction);
                    if (filters) appendNested(params, 'filter', filters);
                    const query = params.toString() ? '?' + params.toString() : '';

                    const response = await fetch('/projekte/' + id + query, {
                        headers: { 'X-Overlay': '1' },
                    });
                    body().innerHTML = await response.text();
                    hideLoading();
                    savedSnapshot = null;
                    snapshot();

                    // Öffnen selbst ändert schon Daten (Vietto: "zuletzt geöffnet"),
                    // unabhängig von einem Speichern - betrifft z.B. die Dashboard-
                    // Kachel "Zuletzt geöffnete Projekte". No-op auf Seiten ohne
                    // diese Funktion (nur auf der Startseite definiert).
                    window.refreshDashboardTiles?.();
                });

                // Event-Delegation: das Formular wird erst nach dem Öffnen per fetch eingefügt.
                // Erfasst auch Formulare in teleportierten Modals (siehe components/modal.blade.php,
                // z.B. Illustrationsaufträge) - die hängen an #modal-root, NICHT mehr an
                // #project-overlay-body, zählen aber trotzdem zum Overlay-Kontext, solange
                // das Overlay gerade offen ist (offsetParent === null, wenn ein Vorfahre
                // display:none hat, z.B. weil das Overlay geschlossen ist).
                document.addEventListener('submit', async (event) => {
                    const overlayOpen = !!body() && body().offsetParent !== null;
                    const inOverlay = !!body() && body().contains(event.target);
                    const inTeleportedModal = overlayOpen && document.getElementById('modal-root').contains(event.target);
                    if (!inOverlay && !inTeleportedModal) {
                        return;
                    }

                    event.preventDefault();
                    showLoading();

                    const formData = new FormData(event.target);
                    const submitter = event.submitter;
                    if (submitter && submitter.name) {
                        formData.append(submitter.name, submitter.value);
                    }

                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-Overlay': '1', 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    // Siehe open-project-Handler oben: alte teleportierte Modal-Klone
                    // (#modal-root) hängen nicht mehr an body() und würden sonst bei
                    // jedem Speichern einen weiteren verwaisten Klon anhäufen.
                    document.getElementById('modal-root').innerHTML = '';
                    body().innerHTML = await response.text();
                    hideLoading();

                    if (response.ok) {
                        snapshot();
                        if (submitter && submitter.name === 'close_after_save') {
                            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-overlay' }));
                        }
                        window.refreshProjekteListInBackground();
                    }
                });

                // Speichern im Overlay ändert oft genau die Daten, die im
                // Hintergrund als Tabelle sichtbar sind (Märkte, Status, ...).
                // Ohne das hier stünde dort bis zum nächsten manuellen Reload
                // der alte Stand.
                // Global (statt nur in diesem Closure-Scope), da auch andere
                // Stellen außerhalb dieses Formulars (z.B. der Favorit-Stern
                // im Overlay-Header) die Hintergrundliste anstoßen müssen.
                window.refreshProjekteListInBackground = async function refreshProjekteListInBackground() {
                    const current = document.getElementById('projekte-content');
                    if (!current) {
                        return;
                    }

                    const html = await fetch(window.location.href).then((r) => r.text());
                    const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('projekte-content');
                    if (fresh) {
                        current.innerHTML = fresh.innerHTML;
                    }
                };
            })();
        </script>
    </body>
</html>
