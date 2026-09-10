<x-admin-layout>
    @if (session('status') === 'mail-templates-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <script>
        window.__mailTemplatesDirtyForms = new Set();
    </script>
    <div class="max-w-2xl" x-data x-init="window.adminPageIsDirty = () => window.__mailTemplatesDirtyForms.size > 0">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">{{ __('Mail-Vorlagen') }}</div>
            <p class="mb-3 text-xs text-gray-400">{{ __('Textbausteine mit einfügbaren Projekt-Feldern.') }}</p>

            @include('admin.mail-templates.partials.manage-body')
        </div>
    </div>
</x-admin-layout>
