<x-admin-layout>
    <script>
        window.__helpArticlesDirtyForms = new Set();
    </script>

    @if (session('status') === 'help-article-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('status') === 'help-article-deleted')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gelöscht.') }}</x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__helpArticlesDirtyForms.size > 0" class="flex flex-1 min-h-0 flex-col">
        @include('admin.help-articles.partials.content')
    </div>
</x-admin-layout>
