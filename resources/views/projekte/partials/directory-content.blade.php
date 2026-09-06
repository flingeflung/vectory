@if ($status['status'] === 'found')
    <div class="mb-2 flex items-center justify-between gap-2 text-xs text-gray-500">
        <span class="truncate" title="{{ $status['path'] }}">{{ $status['path'] }}{{ $status['archived'] ? ' ('.__('Archiv').')' : '' }}</span>
        <button
            type="button"
            onclick="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($status['path']) }})"
            class="shrink-0 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
            {{ __('Pfad kopieren') }}
        </button>
    </div>
    <x-directory-tree :nodes="$contents" root />
@else
    <div class="text-sm text-gray-400">{{ __('Verzeichnis nicht (mehr) gefunden.') }}</div>
@endif
