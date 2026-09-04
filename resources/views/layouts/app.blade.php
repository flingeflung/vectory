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
                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
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

        {{--
            Global (nicht im Projekt-Overlay verschachtelt!) Illustrationsaufträge-Modal -
            wird per window.openIllustrationOrders(projectId) geöffnet und lädt seinen
            Inhalt selbst per fetch(), genau wie project-overlay/favorites oben. Bewusst
            NICHT als Teil des Overlay-HTML verschachtelt: das führte zu Positionierungs-
            und Stacking-Problemen ("position:fixed" bricht in einem Vorfahren mit
            "transform"), die nur mit Alpine x-teleport lösbar waren - und selbst damit
            gab es noch Folgefehler. Als eigenständiges, globales Modal (wie project-overlay
            selbst) tritt das Problem gar nicht erst auf.
        --}}
        <x-modal name="illustration-orders" max-width="lg" :dirty-check="'illustrationOrdersIsDirty'">
            <div class="flex max-h-[85vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Illustrationsaufträge') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'illustration-orders' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="illustration-orders-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
                <div class="flex shrink-0 justify-end border-t border-gray-200 px-4 py-2">
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'illustration-orders' }))"
                        class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                    >
                        {{ __('Schließen') }}
                    </button>
                </div>
            </div>
        </x-modal>

        <script>
            // Aktualisiert die Projekt-Detailansicht (Overlay oder Vollseite - je
            // nachdem, welche Ansicht gerade offen ist) nach dem Speichern in
            // einem eigenständigen globalen Modal (Illustrationsaufträge,
            // WFS-Schritt aktivieren). Global statt pro Modal dupliziert, weil
            // mehrere solcher Modals dasselbe Bedürfnis haben.
            window.refreshUnderlyingProject = async (projectId) => {
                if (! projectId) {
                    return;
                }

                const overlayBody = document.getElementById('project-overlay-body');
                if (overlayBody && overlayBody.offsetParent !== null) {
                    overlayBody.innerHTML = await fetch(`/projekte/${projectId}`, {
                        headers: { 'X-Overlay': '1' },
                    }).then((r) => r.text());

                    return;
                }

                const container = document.getElementById('project-detail-container');
                if (container && window.location.pathname === `/projekte/${projectId}`) {
                    const html = await fetch(window.location.href).then((r) => r.text());
                    const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('project-detail-container');
                    if (fresh) {
                        container.innerHTML = fresh.innerHTML;
                    }
                }
            };
        </script>

        <script>
            (function () {
                const illuBody = () => document.getElementById('illustration-orders-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let currentProjectId = null;
                let savedSnapshot = null;

                // Alle Formulare zusammen (nicht nur "Neuer Auftrag") - es können
                // mehrere gleichzeitig im DOM stehen (je Auftrag ein "Status
                // ändern"-Formular, auch wenn per x-show gerade eingeklappt:
                // FormData erfasst Werte unabhängig von der CSS-Sichtbarkeit).
                const serializeAllForms = () => [...illuBody().querySelectorAll('form')]
                    .map((form) => new URLSearchParams(new FormData(form)).toString())
                    .join('|');

                const snapshot = () => {
                    savedSnapshot = serializeAllForms();
                };

                window.illustrationOrdersIsDirty = () => {
                    return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
                };

                window.openIllustrationOrders = async (projectId) => {
                    currentProjectId = projectId;
                    savedSnapshot = null;
                    illuBody().innerHTML = {{ \Illuminate\Support\Js::from(__('Lädt…')) }};
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'illustration-orders' }));
                    illuBody().innerHTML = await fetch(`/projekte/${projectId}/illustrationsauftraege`).then((r) => r.text());
                    snapshot();
                };

                document.addEventListener('submit', async (event) => {
                    if (!illuBody() || !illuBody().contains(event.target)) {
                        return;
                    }

                    event.preventDefault();

                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(event.target),
                    });
                    illuBody().innerHTML = await response.text();

                    if (response.ok) {
                        snapshot();
                        window.refreshUnderlyingProject(currentProjectId);
                    }
                });
            })();
        </script>

        {{--
            WFS-Schritt aktivieren - Bestätigungs-Dialog (Empfänger, E-Mail-
            Optionen) vor dem eigentlichen Wechsel. Gleiches Muster wie das
            Illustrationsaufträge-Modal: eigenständig, global, eigener fetch().
        --}}
        <x-modal name="activate-workflow-step" max-width="md">
            <div class="flex max-h-[85vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Workflow-Schritt aktivieren') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'activate-workflow-step' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="activate-workflow-step-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const activateBody = () => document.getElementById('activate-workflow-step-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let currentProjectId = null;

                window.openActivateWorkflowStep = async (projectId, projectWorkflowStepId) => {
                    currentProjectId = projectId;
                    activateBody().innerHTML = {{ \Illuminate\Support\Js::from(__('Lädt…')) }};
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'activate-workflow-step' }));
                    activateBody().innerHTML = await fetch(`/projekte/${projectId}/workflow-steps/${projectWorkflowStepId}/activate`).then((r) => r.text());
                };

                document.addEventListener('submit', async (event) => {
                    if (!activateBody() || !activateBody().contains(event.target)) {
                        return;
                    }

                    event.preventDefault();

                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(event.target),
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'activate-workflow-step' }));
                    await window.refreshUnderlyingProject(currentProjectId);

                    if (data.open_graphic_orders_count) {
                        alert({{ \Illuminate\Support\Js::from(__('Achtung, für dieses Projekt sind noch offene Illustrationsaufträge vorhanden:')) }} + ' ' + data.open_graphic_orders_count);
                    }
                });
            })();
        </script>
    </body>
</html>
