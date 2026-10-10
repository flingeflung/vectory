<x-admin-layout>
    <script>
        window.__announcementsDirtyForms = new Set();
    </script>

    @if (session('status') === 'announcements-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__announcementsDirtyForms.size > 0" class="flex flex-1 min-h-0 flex-col">
        @include('admin.announcements.partials.content')
    </div>
</x-admin-layout>
