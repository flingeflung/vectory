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

        {{--
            Personen-Liste (Rechte-/Funktionsgruppen-Verwaltung, ggf. weitere
            künftig) wahlweise alphabetisch oder nach Abteilung gruppiert
            anzeigen - Ralfs Anforderung für mehr Übersichtlichkeit. Sortiert
            NICHT neu vom Server, sondern verschiebt die schon vorhandenen
            DOM-Knoten (Checkboxen/Links bleiben dieselben Elemente, kein
            Duplizieren) - wichtig, weil manche dieser Listen ein
            Zuordnungs-Formular mit Checkboxen sind (Bulk-Zuordnung Rechte-
            Set / Funktionsgruppen-Mitglieder), deren Zustand beim
            Neu-Rendern verloren ginge. Voraussetzung pro Zeile:
            data-person-row, data-department-name, data-sort-index (Server-
            Reihenfolge, für "zurück zu alphabetisch"). Optional eine Liste
            ALLER Abteilungsnamen (dritter Parameter) - auch Abteilungen
            ohne aktuell zugeordnete Person bekommen dann eine Überschrift
            + "keine Person zugeordnet"-Hinweis, statt einfach zu fehlen
            (Ralfs Anforderung: auf einen Blick sehen, welche Abteilung
            noch niemanden hat).
        --}}
        <script>
            window.applyPersonGrouping = function (root, mode, allDepartments = []) {
                if (!root) {
                    return;
                }
                root.querySelectorAll('[data-group-header], [data-group-empty]').forEach((el) => el.remove());

                const items = [...root.querySelectorAll('[data-person-row]')];
                if (items.length === 0) {
                    return;
                }
                const parent = items[0].parentElement;
                const byOriginalOrder = (a, b) => Number(a.dataset.sortIndex) - Number(b.dataset.sortIndex);

                if (mode !== 'department') {
                    items.sort(byOriginalOrder).forEach((item) => parent.appendChild(item));
                    return;
                }

                const groups = new Map();
                items.forEach((item) => {
                    const dept = item.dataset.departmentName || '';
                    if (!groups.has(dept)) {
                        groups.set(dept, []);
                    }
                    groups.get(dept).push(item);
                });
                // Auch Abteilungen ohne aktuell zugeordnete (bzw. gerade
                // sichtbare) Person als leere Gruppe mit aufnehmen.
                allDepartments.forEach((dept) => {
                    if (!groups.has(dept)) {
                        groups.set(dept, []);
                    }
                });
                const deptNames = [...groups.keys()].sort((a, b) => {
                    if (a === '' || b === '') {
                        return a === b ? 0 : (a === '' ? 1 : -1);
                    }
                    return a.localeCompare(b, 'de');
                });
                deptNames.forEach((dept) => {
                    const header = document.createElement('div');
                    header.setAttribute('data-group-header', '');
                    header.className = 'px-2 pt-3 pb-1 text-xs font-semibold text-gray-400 first:pt-1';
                    header.textContent = '– ' + (dept || {{ \Illuminate\Support\Js::from(__('Ohne Abteilung')) }}) + ' –';
                    parent.appendChild(header);

                    const groupItems = groups.get(dept);
                    if (groupItems.length === 0) {
                        const empty = document.createElement('div');
                        empty.setAttribute('data-group-empty', '');
                        empty.className = 'px-2 py-1 text-xs italic text-gray-300';
                        empty.textContent = {{ \Illuminate\Support\Js::from(__('– keine Person zugeordnet –')) }};
                        parent.appendChild(empty);
                        return;
                    }
                    groupItems.sort(byOriginalOrder).forEach((item) => parent.appendChild(item));
                });
            };
        </script>

        {{--
            Live-Suche für debounced Textfeld-Filter (Nachname-Suche in der
            Personenverwaltung, PN/Illu-Nr.-Suche bei Illustrationen, ...):
            statt bei jedem Tastendruck nach 1-2 Sek. die ganze Seite neu zu
            laden (verliert Fokus/Cursor im Suchfeld!), wird nur der per ID
            angegebene Ergebnis-Container per fetch() ausgetauscht - das
            Suchfeld selbst bleibt unangetastet, Fokus/Cursor bleiben erhalten.
            Generelle Regel (CLAUDE.md): jede neue/bestehende debounced
            Text-Suche soll dieses Muster nutzen, kurzes Delay (400ms).
        --}}
        <script>
            window.liveFilterSearch = (function () {
                const timers = new WeakMap();
                return function (input, resultContainerId, delay = 400) {
                    clearTimeout(timers.get(input));
                    const timer = setTimeout(async () => {
                        const form = input.form;
                        const params = new URLSearchParams(new FormData(form));
                        const url = form.action + '?' + params.toString();
                        const html = await fetch(url).then((r) => r.text());
                        const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById(resultContainerId);
                        const current = document.getElementById(resultContainerId);
                        if (fresh && current) {
                            current.innerHTML = fresh.innerHTML;
                        }
                        history.replaceState(null, '', url);
                    }, delay);
                    timers.set(input, timer);
                };
            })();
        </script>

        {{--
            Neu laden einer "klitzekleine Unterbereiche"-Verwalten-Liste
            (Firma/Abteilung/Geschäftsbereich/Rolle), OHNE ungespeicherte
            Eingaben in anderen Zeilen zu verlieren: jede Zeile hat ihr
            eigenes Speichern-Formular, ein einzelnes Speichern lädt bisher
            aber die KOMPLETTE Liste per fetch() neu - das wischt getippte,
            aber noch nicht abgeschickte Änderungen in anderen Zeilen weg
            (Ralfs konkreter Bug-Report: mehrere Abteilungs-Kürzel getippt,
            nur eine Zeile gespeichert, Rest weg). Fix: vor dem Neuladen
            die aktuellen Feldwerte aller Zeilen-Formulare sichern, danach
            wieder einsetzen - nur die gerade gespeicherte Zeile hat ohnehin
            denselben Wert (kein Unterschied), alle anderen behalten ihre
            unfertige Eingabe. Setzt data-row-form auf dem jeweiligen
            Zeilen-Formular voraus (NICHT auf Anlegen-/Löschen-Formularen -
            die sollen nach dem Neuladen normal zurückgesetzt werden).
        --}}
        <script>
            window.reloadManageListPreservingEdits = async function (body, url) {
                const snapshot = new Map();
                body.querySelectorAll('form[data-row-form]').forEach((form) => {
                    const values = {};
                    form.querySelectorAll('input[type="text"], input[type="checkbox"]').forEach((input) => {
                        values[input.name] = input.type === 'checkbox' ? input.checked : input.value;
                    });
                    snapshot.set(form.action, values);
                });

                // Scrollposition sichern: das komplette Neuladen (innerHTML)
                // setzt sonst auf 0 zurück, man müsste nach jedem Speichern
                // wieder zu der Zeile runterscrollen, an der man gerade war
                // (Ralfs konkreter Report bei den Abteilungen). Sowohl den
                // äußeren Body als auch die innere scrollende Zeilenliste
                // sichern, je nachdem welcher tatsächlich scrollt.
                const scrollTop = body.scrollTop;
                const innerListBefore = body.querySelector('.overflow-y-auto');
                const innerScrollTop = innerListBefore ? innerListBefore.scrollTop : null;

                body.innerHTML = await fetch(url).then((r) => r.text());

                body.scrollTop = scrollTop;
                const innerListAfter = body.querySelector('.overflow-y-auto');
                if (innerListAfter && innerScrollTop !== null) {
                    innerListAfter.scrollTop = innerScrollTop;
                }

                // Alpine initialisiert neu eingefügtes HTML (x-data, @input
                // ...) über einen MutationObserver, NICHT synchron sofort
                // nach dem innerHTML-Zuweisen - ein hier sofort ausgelöstes
                // input-Event würde ins Leere laufen, weil der @input-
                // Listener der Zeile noch gar nicht existiert. Ein Makrotask
                // warten reicht (MutationObserver-Callbacks sind Microtasks,
                // laufen also garantiert vorher durch) - Alpine.nextTick()
                // NICHT verwenden, das hängt sich auf, wenn es außerhalb
                // eines laufenden Alpine-Zyklus aufgerufen wird (kein
                // ausstehendes Update zum "Abwarten").
                await new Promise((resolve) => setTimeout(resolve, 0));

                body.querySelectorAll('form[data-row-form]').forEach((form) => {
                    const values = snapshot.get(form.action);
                    if (!values) {
                        return;
                    }
                    form.querySelectorAll('input[type="text"], input[type="checkbox"]').forEach((input) => {
                        if (!(input.name in values)) {
                            return;
                        }
                        const fresh = input.type === 'checkbox' ? input.checked : input.value;
                        const restored = values[input.name];
                        if (input.type === 'checkbox') {
                            input.checked = restored;
                        } else {
                            input.value = restored;
                        }
                        // Nur ein synthetisches input-Event feuern, wenn der
                        // wiederhergestellte Wert wirklich vom frisch
                        // geladenen abweicht (= eine ECHTE ungespeicherte
                        // Änderung in dieser Zeile) - das lässt Alpines
                        // dirty-Tracking (siehe manage-body-Partials) den
                        // Speichern-Button für genau diese Zeile wieder
                        // einblenden, nicht aber für die gerade gespeicherte.
                        if (fresh !== restored) {
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                });
            };
        </script>

        {{--
            Kurzer grüner "Gespeichert."-Hinweis (2 Sek., dann ausgeblendet)
            für die Firma/Abteilung/Geschäftsbereich/Rolle-Verwalten-Overlays -
            Ralfs Anforderung: nach dem Speichern einer Zeile tut sich sonst
            optisch nichts (Formular zeigt ja ohnehin schon die gerade
            eingegebenen Werte), das wirkte wie "passiert nichts".
        --}}
        <script>
            window.showManageSavedToast = (function () {
                const timers = new Map();
                return function (toastId) {
                    const toast = document.getElementById(toastId);
                    if (!toast) {
                        return;
                    }
                    const data = Alpine.$data(toast);
                    data.show = true;
                    clearTimeout(timers.get(toastId));
                    timers.set(toastId, setTimeout(() => { data.show = false; }, 2000));
                };
            })();
        </script>

        {{--
            Filter-Pulldowns (Firma/Abteilung/Geschäftsbereich/Rolle) in der
            Personenverwaltung live aktuell halten, wenn eine dieser Listen
            über die neuen Stift-Buttons direkt aus dem Filterbereich heraus
            bearbeitet wurde (z.B. Firma umbenannt) - sonst zeigt das
            Pulldown bis zum nächsten normalen Seiten-Reload noch den alten
            Namen. No-op, wenn die Personenliste gerade gar nicht offen ist.
        --}}
        <script>
            window.refreshPersonenFiltersInBackground = async function refreshPersonenFiltersInBackground() {
                const ids = ['filter-company_id', 'filter-department_id', 'filter-business_unit_id', 'filter-legacy_role_id'];
                if (! ids.some((id) => document.getElementById(id))) {
                    return;
                }

                const html = await fetch(window.location.href).then((r) => r.text());
                const fresh = new DOMParser().parseFromString(html, 'text/html');
                ids.forEach((id) => {
                    const current = document.getElementById(id);
                    const updated = fresh.getElementById(id);
                    if (current && updated) {
                        current.innerHTML = updated.innerHTML;
                    }
                });
            };
        </script>

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

        {{--
            Global Personen-Detail-Overlay: von der Personenverwaltung per
            Namensklick (x-person-link) öffenbar - gleiches Grundmuster wie
            project-overlay oben, inkl. draggable/resizable (generelle Regel
            für Overlays dieser Art, nicht nur fürs Projekt-Overlay).
        --}}
        <x-modal name="person-overlay" max-width="2xl" :dirty-check="'personOverlayIsDirty'" :draggable="true" :resizable="true">
            <div class="relative h-full">
                <div id="person-overlay-body" class="flex h-full min-h-0 flex-col text-sm text-gray-500">
                    <div class="p-4">{{ __('Lädt…') }}</div>
                </div>
                <div id="person-overlay-loading" class="absolute inset-0 hidden items-center justify-center bg-white/70">
                    <x-loading-spinner class="h-8 w-8 text-gray-400" />
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const body = () => document.getElementById('person-overlay-body');
                const loading = () => document.getElementById('person-overlay-loading');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let savedSnapshot = null;

                const showLoading = () => loading().classList.replace('hidden', 'flex');
                const hideLoading = () => loading().classList.replace('flex', 'hidden');

                const serializeForms = () => Array.from(body().querySelectorAll('form')).map((form) => new URLSearchParams(new FormData(form)).toString()).join('|');

                const snapshot = () => {
                    savedSnapshot = serializeForms();
                };

                window.personOverlayIsDirty = () => {
                    const current = serializeForms();
                    return current !== null && current !== savedSnapshot;
                };

                // PHP-kompatible bracket-Notation für die mitgegebenen Filter
                // (siehe appendNested beim Projekt-Overlay - hier reicht ein
                // flaches Objekt, da Personen-Filter keine verschachtelten
                // Werte wie Datumsbereiche haben).
                let currentPersonId = null;
                let currentPersonFilters = {};

                const loadPerson = async (id, filters) => {
                    showLoading();

                    const params = new URLSearchParams();
                    Object.entries(filters || {}).forEach(([key, value]) => {
                        if (value !== null && value !== undefined && value !== '') {
                            params.append(key, value);
                        }
                    });
                    const query = params.toString() ? '?' + params.toString() : '';

                    const response = await fetch('/admin/personen/' + id + query, {
                        headers: { 'X-Overlay': '1' },
                    });
                    body().innerHTML = await response.text();
                    hideLoading();
                    savedSnapshot = null;
                    snapshot();
                };

                window.addEventListener('open-person', async (event) => {
                    const { id, filters } = event.detail;
                    if (!id) {
                        return;
                    }

                    currentPersonId = id;
                    currentPersonFilters = filters || {};
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'person-overlay' }));
                    await loadPerson(currentPersonId, currentPersonFilters);
                });

                // Von der Firmen-Verwaltung (o.ä. Unterbereiche) aufgerufen,
                // wenn die dort verwaltete Liste sich geändert haben könnte -
                // lädt das gerade offene Personen-Overlay neu, damit z.B. ein
                // umbenannter Firmenname sofort im Firma-Dropdown auftaucht.
                window.reopenCurrentPersonOverlay = async function reopenCurrentPersonOverlay() {
                    if (currentPersonId === null) {
                        return;
                    }
                    await loadPerson(currentPersonId, currentPersonFilters);
                };

                // Event-Delegation: die Formulare werden erst nach dem Öffnen per fetch eingefügt.
                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
                        return;
                    }

                    event.preventDefault();
                    showLoading();

                    const formData = new FormData(event.target);
                    const response = await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-Overlay': '1', 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    body().innerHTML = await response.text();
                    hideLoading();
                    snapshot();
                    window.refreshPersonenListInBackground?.();
                });

                // Speichern im Overlay ändert Daten, die im Hintergrund als
                // Tabelle sichtbar sind - ohne das hier stünde dort bis zum
                // nächsten manuellen Reload der alte Stand.
                window.refreshPersonenListInBackground = async function refreshPersonenListInBackground() {
                    const current = document.getElementById('personen-list');
                    if (!current) {
                        return;
                    }

                    const html = await fetch(window.location.href).then((r) => r.text());
                    const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('personen-list');
                    if (fresh) {
                        current.innerHTML = fresh.innerHTML;
                    }
                };

                // Kürzel-Vorschlag aus Vor-/Nachname (Vorname-Initial + erste
                // zwei Buchstaben Nachname, z.B. "Ralf Geyer" -> "RGy") - nur
                // solange das Feld noch leer ist, überschreibt also nie eine
                // manuelle Eingabe. Global (nicht Overlay-Closure), weil das
                // Feld auch auf der normalen Vollseite (nicht nur im Overlay)
                // vorkommt.
                window.suggestPersonShortName = function () {
                    const first = document.getElementById('person-first-name');
                    const last = document.getElementById('person-last-name');
                    const short = document.getElementById('person-short-name');
                    if (!first || !last || !short || short.value.trim() !== '') {
                        return;
                    }
                    const f = first.value.trim();
                    const l = last.value.trim();
                    if (!f || !l) {
                        return;
                    }
                    short.value = f.charAt(0).toUpperCase() + l.charAt(0).toUpperCase() + (l.charAt(1) || '').toLowerCase();
                };
            })();
        </script>

        {{--
            Firmen-Verwaltung: kleines, nicht verschiebbares Overlay (wie
            Illustrationsaufträge) - aus dem Personen-Overlay heraus per Link
            neben "Firma" öffenbar. Legt sich als zweite Ebene VOR ein
            offenes Personen-Overlay (beide <x-modal>-Instanzen können
            gleichzeitig offen sein, das Personen-Overlay bleibt dahinter
            erhalten, graue Wand dazwischen). Erster von perspektivisch
            mehreren gleichartigen "klitzekleine Unterbereiche"-Overlays
            (als Nächstes: Abteilung, Geschäftsbereich) - bewusst noch nicht
            generisch abstrahiert, bis das Muster ein zweites Mal steht.
        --}}
        <x-modal name="company-manager" max-width="lg" :dirty-check="'companyManagerIsDirty'">
            <div class="flex max-h-[80vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Firmen verwalten') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'company-manager' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="company-manager-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mx-4 mt-2 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
                    {{ __('Gespeichert.') }}
                </div>
                <div id="company-manager-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const body = () => document.getElementById('company-manager-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let wasOpened = false;
                let savedSnapshot = null;

                // Alle Formulare zusammen (Anlegen + je Firma ein Umbenennen-
                // und ein Löschen-Formular) - gleiches Muster wie bei den
                // Illustrationsaufträgen (illustrationOrdersIsDirty).
                const serializeAllForms = () => [...body().querySelectorAll('form')]
                    .map((form) => new URLSearchParams(new FormData(form)).toString())
                    .join('|');

                const snapshot = () => {
                    savedSnapshot = serializeAllForms();
                };

                window.companyManagerIsDirty = () => {
                    return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
                };

                const load = async () => {
                    await window.reloadManageListPreservingEdits(body(), {{ \Illuminate\Support\Js::from(route('admin.companies')) }});
                    snapshot();
                };

                window.addEventListener('open-modal', (event) => {
                    if (event.detail !== 'company-manager') {
                        return;
                    }
                    wasOpened = true;
                    load();
                });

                window.addEventListener('close-modal', (event) => {
                    if (event.detail !== 'company-manager' || !wasOpened) {
                        return;
                    }
                    wasOpened = false;
                    // Zurück im Personen-Overlay den Firma-Vorschlag aktuell
                    // halten - einfach neu laden statt gezielt nur die
                    // <option>-Liste auszutauschen.
                    window.reopenCurrentPersonOverlay?.();
                    window.refreshPersonenFiltersInBackground?.();
                });

                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
                        return;
                    }
                    event.preventDefault();
                    const isRowForm = event.target.hasAttribute('data-row-form');

                    const formData = new FormData(event.target);
                    await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    await load();
                    if (isRowForm) {
                        window.showManageSavedToast('company-manager-toast');
                    }
                });
            })();
        </script>

        {{--
            Abteilungs-Verwaltung: gleiches Muster wie company-manager oben
            (klitzekleines Unterbereich-Overlay, aus dem Personen-Overlay
            heraus per Link neben "Abteilung" öffenbar).
        --}}
        <x-modal name="department-manager" max-width="lg" :dirty-check="'departmentManagerIsDirty'">
            <div class="flex max-h-[80vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Abteilungen verwalten') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'department-manager' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="department-manager-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mx-4 mt-2 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
                    {{ __('Gespeichert.') }}
                </div>
                <div id="department-manager-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const body = () => document.getElementById('department-manager-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let wasOpened = false;
                let savedSnapshot = null;

                const serializeAllForms = () => [...body().querySelectorAll('form')]
                    .map((form) => new URLSearchParams(new FormData(form)).toString())
                    .join('|');

                const snapshot = () => {
                    savedSnapshot = serializeAllForms();
                };

                window.departmentManagerIsDirty = () => {
                    return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
                };

                const load = async () => {
                    await window.reloadManageListPreservingEdits(body(), {{ \Illuminate\Support\Js::from(route('admin.departments')) }});
                    snapshot();
                };

                window.addEventListener('open-modal', (event) => {
                    if (event.detail !== 'department-manager') {
                        return;
                    }
                    wasOpened = true;
                    load();
                });

                window.addEventListener('close-modal', (event) => {
                    if (event.detail !== 'department-manager' || !wasOpened) {
                        return;
                    }
                    wasOpened = false;
                    window.reopenCurrentPersonOverlay?.();
                    window.refreshPersonenFiltersInBackground?.();
                });

                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
                        return;
                    }
                    event.preventDefault();
                    const isRowForm = event.target.hasAttribute('data-row-form');

                    const formData = new FormData(event.target);
                    await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    await load();
                    if (isRowForm) {
                        window.showManageSavedToast('department-manager-toast');
                    }
                });
            })();
        </script>

        {{--
            Geschäftsbereichs-Verwaltung: gleiches Muster wie company-manager
            oben. Ersetzt die frühere eigenständige Seite (admin/business-units).
        --}}
        <x-modal name="business-unit-manager" max-width="lg" :dirty-check="'businessUnitManagerIsDirty'">
            <div class="flex max-h-[80vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Geschäftsbereiche verwalten') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'business-unit-manager' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="business-unit-manager-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mx-4 mt-2 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
                    {{ __('Gespeichert.') }}
                </div>
                <div id="business-unit-manager-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const body = () => document.getElementById('business-unit-manager-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let wasOpened = false;
                let savedSnapshot = null;

                const serializeAllForms = () => [...body().querySelectorAll('form')]
                    .map((form) => new URLSearchParams(new FormData(form)).toString())
                    .join('|');

                const snapshot = () => {
                    savedSnapshot = serializeAllForms();
                };

                window.businessUnitManagerIsDirty = () => {
                    return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
                };

                const load = async () => {
                    await window.reloadManageListPreservingEdits(body(), {{ \Illuminate\Support\Js::from(route('admin.geschaeftsbereiche')) }});
                    snapshot();
                };

                window.addEventListener('open-modal', (event) => {
                    if (event.detail !== 'business-unit-manager') {
                        return;
                    }
                    wasOpened = true;
                    load();
                });

                window.addEventListener('close-modal', (event) => {
                    if (event.detail !== 'business-unit-manager' || !wasOpened) {
                        return;
                    }
                    wasOpened = false;
                    window.reopenCurrentPersonOverlay?.();
                    window.refreshPersonenFiltersInBackground?.();
                });

                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
                        return;
                    }
                    event.preventDefault();
                    const isRowForm = event.target.hasAttribute('data-row-form');

                    const formData = new FormData(event.target);
                    await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    await load();
                    if (isRowForm) {
                        window.showManageSavedToast('business-unit-manager-toast');
                    }
                });
            })();
        </script>

        {{--
            Rollen-Verwaltung (LegacyRole): gleiches Muster wie company-manager
            oben, aus dem Personen-Overlay heraus per Link neben "Rolle" öffenbar.
        --}}
        <x-modal name="legacy-role-manager" max-width="lg" :dirty-check="'legacyRoleManagerIsDirty'">
            <div class="flex max-h-[80vh] flex-col">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Rollen verwalten') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'legacy-role-manager' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="legacy-role-manager-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mx-4 mt-2 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
                    {{ __('Gespeichert.') }}
                </div>
                <div id="legacy-role-manager-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                    {{ __('Lädt…') }}
                </div>
            </div>
        </x-modal>

        <script>
            (function () {
                const body = () => document.getElementById('legacy-role-manager-body');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                let wasOpened = false;
                let savedSnapshot = null;

                const serializeAllForms = () => [...body().querySelectorAll('form')]
                    .map((form) => new URLSearchParams(new FormData(form)).toString())
                    .join('|');

                const snapshot = () => {
                    savedSnapshot = serializeAllForms();
                };

                window.legacyRoleManagerIsDirty = () => {
                    return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
                };

                const load = async () => {
                    await window.reloadManageListPreservingEdits(body(), {{ \Illuminate\Support\Js::from(route('admin.legacy-roles')) }});
                    snapshot();
                };

                window.addEventListener('open-modal', (event) => {
                    if (event.detail !== 'legacy-role-manager') {
                        return;
                    }
                    wasOpened = true;
                    load();
                });

                window.addEventListener('close-modal', (event) => {
                    if (event.detail !== 'legacy-role-manager' || !wasOpened) {
                        return;
                    }
                    wasOpened = false;
                    window.reopenCurrentPersonOverlay?.();
                    window.refreshPersonenFiltersInBackground?.();
                });

                document.addEventListener('submit', async (event) => {
                    if (!body() || !body().contains(event.target)) {
                        return;
                    }
                    event.preventDefault();
                    const isRowForm = event.target.hasAttribute('data-row-form');

                    const formData = new FormData(event.target);
                    await fetch(event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: formData,
                    });
                    await load();
                    if (isRowForm) {
                        window.showManageSavedToast('legacy-role-manager-toast');
                    }
                });
            })();
        </script>

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
                        await window.notifyDialog(
                            {{ \Illuminate\Support\Js::from(__('Achtung, für dieses Projekt sind noch offene Illustrationsaufträge vorhanden:')) }} + ' ' + data.open_graphic_orders_count
                        );
                    }
                });
            })();
        </script>
    </body>
</html>
