<x-admin-layout>
    {{--
        Diese Seite hat mehrere unabhängige Formulare (Kategorie umbenennen +
        je ein Formular pro Art) - ein gemeinsames Set sammelt, welche davon
        gerade ungespeichert geändert sind (gleiches Muster wie
        admin/config/index.blade.php: window.__configDirtyForms). Wichtig:
        <script> HIER läuft vor dem <script> in layouts/app.blade.php, das
        window.adminPageIsDirty auf den Standardwert zurücksetzt - siehe
        dortiger Kommentar zur Reihenfolge.
    --}}
    <script>
        window.__projektkategorienDirtyForms = new Set();
    </script>

    @if (session('status') === 'projektkategorien-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    {{-- Für Kategorie-Umbenennen/Art-Speichern (siehe <script> unten, laufen
         per AJAX statt vollem Seiten-Reload) - gleiches Toast-Muster wie die
         "klitzekleinen" Verwalten-Overlays (window.showManageSavedToast). --}}
    <div id="projektkategorien-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mb-3 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
        {{ __('Gespeichert.') }}
    </div>

    @include('admin.projektkategorien.partials.content')

    {{--
        Kategorie umbenennen und Art-Zeilen speichern liefen bisher über
        einen vollen Formular-POST/Seiten-Reload - genau das Problem, das
        window.reloadManageListPreservingEdits() bei den "klitzekleinen"
        Verwalten-Overlays schon löst (ein Speichern in einer Zeile wischt
        sonst ungespeicherte Eingaben in anderen Zeilen weg). Hier dasselbe
        Muster auf eine echte Seite (nicht ein Fetch-Overlay) übertragen:
        das Formular wird per fetch() abgeschickt, danach nur das
        Inhalts-Partial (X-Overlay-Header, siehe ProjectTypeController)
        statt der kompletten Seite neu geladen. Anlegen-/Löschen-Formulare
        bleiben bewusst normale Seiten-POSTs (kein data-row-form) - die
        sollen nach dem Absenden ohnehin zurückgesetzt werden bzw. lösen
        (beim Löschen, über window.deleteWithConfirm) ohnehin ein natives
        form.submit() aus, das gar kein 'submit'-Event feuert und diesen
        Listener hier folglich nie erreicht.
    --}}
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const container = () => document.getElementById('projektkategorien-content');

            document.addEventListener('submit', async (event) => {
                if (!container() || !container().contains(event.target) || !event.target.hasAttribute('data-row-form')) {
                    return;
                }

                event.preventDefault();

                const formData = new FormData(event.target);
                await fetch(event.target.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });

                await window.reloadManageListPreservingEdits(container(), window.location.href, { 'X-Overlay': '1' });
                window.showManageSavedToast('projektkategorien-toast');
            });
        })();
    </script>
</x-admin-layout>
