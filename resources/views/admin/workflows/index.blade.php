<x-admin-layout>
    {{--
        Gleiches Dirty-Tracking-Muster wie Projektkategorien (mehrere
        unabhängige Formulare: Workflow umbenennen + je ein Formular pro
        Schritt).
    --}}
    <script>
        window.__workflowsDirtyForms = new Set();
    </script>

    @if (session('status') === 'workflows-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    {{-- Für Workflow-Umbenennen/Schritt-Speichern (siehe <script> unten,
         laufen per AJAX statt vollem Seiten-Reload) - gleiches Toast-Muster
         wie die "klitzekleinen" Verwalten-Overlays. --}}
    <div id="workflows-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mb-3 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
        {{ __('Gespeichert.') }}
    </div>

    @include('admin.workflows.partials.content')

    {{--
        Gleiches Muster wie Projektkategorien: Workflow-Umbenennen und
        Schritt-Speichern laufen per fetch() statt vollem Formular-POST, um
        ungespeicherte Eingaben in anderen Zeilen nicht wegzuwischen.
        Anlegen-/Löschen-Formulare bleiben normale Seiten-POSTs.
    --}}
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const container = () => document.getElementById('workflows-content');

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
                window.showManageSavedToast('workflows-toast');
            });
        })();
    </script>
</x-admin-layout>
