<x-admin-layout>
    @if (session('status') === 'projektschablonen-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div id="project-templates-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mb-3 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
        {{ __('Gespeichert.') }}
    </div>

    <p class="mb-3 shrink-0 text-xs text-gray-500">
        {{ __('Erfahrungswerte-Katalog für die Redaktionsleitung: Merkmale eines typischen Projekts + geschätzte Brutto-Bearbeitungsdauer. Grundlage für die spätere Kapazitätsplanung: ein gekoppelter Workflow bestimmt die beteiligten Funktionsgruppen, dafür lassen sich geplante Stunden hinterlegen.') }}
    </p>

    <form method="GET" action="{{ route('admin.projektschablonen') }}" class="mb-3 shrink-0 flex flex-wrap items-end gap-3">
        @foreach (\App\Models\ProjectTemplate::filterableFields() as $field)
            @php($meta = \App\Models\ProjectTemplate::characteristicFields()[$field])
            <div>
                <label class="block text-xs text-gray-500">{{ $meta['label'] }}</label>
                <select name="{{ $field }}" onchange="this.form.submit()" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
                    <option value="">{{ __('– alle –') }}</option>
                    @foreach ($meta['options'] as $value => $option)
                        <option value="{{ $value }}" @selected((string) request($field) === (string) $value)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        @if (collect(\App\Models\ProjectTemplate::filterableFields())->contains(fn ($field) => request()->filled($field)))
            <a href="{{ route('admin.projektschablonen') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Filter löschen') }}
            </a>
        @endif
    </form>

    <div id="project-templates-content" class="min-h-0 flex-1 overflow-y-auto">
        @include('admin.project-templates.partials.content')
    </div>

    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const container = () => document.getElementById('project-templates-content');

            document.addEventListener('submit', async (event) => {
                if (!container() || !container().contains(event.target)) {
                    return;
                }

                event.preventDefault();
                const isRowForm = event.target.hasAttribute('data-row-form');

                const formData = new FormData(event.target);
                const response = await fetch(event.target.action, {
                    method: event.target.method,
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });

                if (!response.ok) {
                    await window.reloadManageListPreservingEdits(container(), window.location.href, { 'X-Overlay': '1' });
                    await window.notifyDialog({{ \Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte Eingaben prüfen.')) }});
                    return;
                }

                await window.reloadManageListPreservingEdits(container(), window.location.href, { 'X-Overlay': '1' });
                if (isRowForm) {
                    window.showManageSavedToast('project-templates-toast');
                }
            });
        })();
    </script>
</x-admin-layout>
