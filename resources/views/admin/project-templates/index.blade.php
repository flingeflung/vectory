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

    {{-- "Von anderem Kunden holen" (Ralf, 2026-09-18): pull-basiert wie bei
         den Papierformaten abgestimmt, hier aber gezielt EINE einzelne
         Schablone statt des ganzen Katalogs. Normaler Seiten-POST (wie das
         Papierformate-Pendant), kein fetch-Intercept nötig. --}}
    @if ($otherTenants->isNotEmpty())
        <x-modal name="projektschablonen-uebernehmen" max-width="sm">
            <form
                method="POST"
                action="{{ route('admin.projektschablonen.uebernehmen') }}"
                x-data="{
                    sourceTenantId: '',
                    templates: [],
                    loading: false,
                    templateId: '',
                    async loadTemplates() {
                        this.templateId = '';
                        this.templates = [];
                        if (!this.sourceTenantId) {
                            return;
                        }
                        this.loading = true;
                        const response = await fetch({{ \Illuminate\Support\Js::from(route('admin.projektschablonen.fremdkatalog')) }} + '?tenant_id=' + this.sourceTenantId);
                        this.templates = await response.json();
                        this.loading = false;
                    },
                }"
            >
                @csrf
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Schablone von anderem Kunden holen') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektschablonen-uebernehmen' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3 p-4 text-sm">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kunde, von dem geholt werden soll') }}</label>
                        <select name="source_tenant_id" x-model="sourceTenantId" @change="loadTemplates()" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– bitte wählen –') }}</option>
                            @foreach ($otherTenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="sourceTenantId" x-cloak>
                        <label class="block text-xs text-gray-500">{{ __('Schablone') }}</label>
                        <select name="source_template_id" x-model="templateId" :disabled="loading || templates.length === 0" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            {{-- x-if statt x-show: <option>-Elemente reagieren
                                 browserübergreifend unzuverlässig auf
                                 CSS-display-Toggling (x-show), x-if entfernt
                                 sie stattdessen ganz aus dem DOM. --}}
                            <template x-if="loading">
                                <option value="">{{ __('Lädt…') }}</option>
                            </template>
                            <template x-if="!loading && templates.length === 0">
                                <option value="">{{ __('– keine Schablonen bei diesem Kunden –') }}</option>
                            </template>
                            <template x-for="t in templates" :key="t.id">
                                <option :value="t.id" x-text="t.label"></option>
                            </template>
                        </select>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('Übernimmt Merkmale, Format und Dauer der gewählten Schablone. Workflow-Kopplung und Stunden je Funktionsgruppe werden NICHT mit übernommen, da diese je Kunde unterschiedlich sind - bitte im Zielkunden neu zuweisen. Die Kopie startet inaktiv.') }}</p>
                </div>
                <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 p-3">
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektschablonen-uebernehmen' }))"
                        class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        {{ __('Abbrechen') }}
                    </button>
                    <button type="submit" :disabled="!templateId" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:opacity-40">
                        {{ __('Holen') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

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
