@props(['title', 'sortable' => false])

<div class="w-80 shrink-0 overflow-hidden rounded-lg bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2">
        <h3 class="text-sm font-semibold text-gray-800">{{ $title }}</h3>
        @if ($sortable)
            <span x-sort:handle class="cursor-move text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
        @endif
    </div>
    <div class="h-[300px] overflow-y-auto p-3 text-sm">
        {{ $slot }}
    </div>
</div>
