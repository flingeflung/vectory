<x-admin-layout>
    {{-- Gleiches Dirty-Tracking-Muster wie Workflows/Projektkategorien
         (mehrere unabhängige Formulare: Haupt-Formular + Stunden-je-Fktgrp). --}}
    <script>
        window.__projectTemplatesDirtyForms = new Set();
    </script>

    @if (session('status') === 'projektschablonen-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div id="project-templates-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mb-3 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
        {{ __('Gespeichert.') }}
    </div>

    @include('admin.project-templates.partials.content')

    {{-- Gleiches Muster wie Workflows/Projektkategorien: Speichern läuft per
         fetch() statt vollem Formular-POST, um ungespeicherte Eingaben in
         der jeweils anderen Formularhälfte nicht wegzuwischen. Anlegen-/
         Löschen-Formulare bleiben normale Seiten-POSTs. --}}
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const container = () => document.getElementById('project-templates-content');

            document.addEventListener('submit', async (event) => {
                if (!container() || !container().contains(event.target) || !event.target.hasAttribute('data-row-form')) {
                    return;
                }

                event.preventDefault();

                const formData = new FormData(event.target);
                const response = await fetch(event.target.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });

                if (!response.ok) {
                    await window.reloadManageListPreservingEdits(container(), window.location.href, { 'X-Overlay': '1' });
                    await window.notifyDialog({{ \Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte Eingaben prüfen.')) }});
                    return;
                }

                await window.reloadManageListPreservingEdits(container(), window.location.href, { 'X-Overlay': '1' });
                window.showManageSavedToast('project-templates-toast');
            });
        })();
    </script>
</x-admin-layout>
