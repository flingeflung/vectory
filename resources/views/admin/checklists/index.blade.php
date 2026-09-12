<x-admin-layout>
    <script>
        window.__checklistsDirtyForms = new Set();
    </script>

    @if (session('status') === 'checklists-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('status') === 'checklist-copied-to-tenant')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Zum Kunden kopiert.') }}</x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__checklistsDirtyForms.size > 0" class="flex flex-1 min-h-0 flex-col">
        @include('admin.checklists.partials.content')
    </div>
</x-admin-layout>
