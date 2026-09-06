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
                    <img src="{{ asset(\App\Services\ProjectDirectoryLocator::fileTypeIcon($node['name'])) }}" alt="" class="h-4 w-4 shrink-0 object-contain">
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
