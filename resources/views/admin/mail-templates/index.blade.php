<x-admin-layout>
    <script>
        window.__mailTemplatesDirtyForms = new Set();
    </script>

    @if (session('status') === 'mail-templates-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__mailTemplatesDirtyForms.size > 0" class="flex flex-1 min-h-0 flex-col">
        @include('admin.mail-templates.partials.content')
    </div>
</x-admin-layout>
