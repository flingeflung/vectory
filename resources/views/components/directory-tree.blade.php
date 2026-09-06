@props(['nodes'])

<ul class="space-y-0.5 {{ $attributes->has('root') ? '' : 'ml-5 mt-0.5 border-l border-gray-100 pl-3' }}">
    @forelse ($nodes as $node)
        <li>
            <div class="flex items-center gap-1.5 text-gray-700">
                @if ($node['type'] === 'dir')
                    <svg class="h-4 w-4 shrink-0 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3.75 5.25A1.5 1.5 0 015.25 3.75h4.19a1.5 1.5 0 011.06.44l1.31 1.31h8.44a1.5 1.5 0 011.5 1.5v11.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5V5.25z" />
                    </svg>
                    <span class="truncate">{{ $node['name'] }}</span>
                @else
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-1.519-2.75L12 17.25m0 0l-1.481-2.75M12 17.25V21m-7.5-3.75h15A2.25 2.25 0 0021.75 15V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25V15a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span class="truncate text-gray-600">{{ $node['name'] }}</span>
                    <span class="shrink-0 text-xs text-gray-400">{{ \App\Services\ProjectDirectoryLocator::formatBytes($node['size']) }}</span>
                @endif
            </div>

            @if ($node['type'] === 'dir' && ! empty($node['children']))
                <x-directory-tree :nodes="$node['children']" />
            @endif
        </li>
    @empty
        <li class="text-xs text-gray-400">{{ __('Leer.') }}</li>
    @endforelse
</ul>
