@props(['title'])

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            {{-- Nav-Ebene 2: weitere Admin-Unterseiten (Personen, Firmen, ...) kommen hier dazu. --}}
            <div class="mb-4 flex shrink-0 gap-4 border-b border-gray-200 text-sm">
                <a
                    href="{{ route('admin.personen') }}"
                    class="pb-2 {{ request()->routeIs('admin.personen*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Personen') }}
                </a>
                <a
                    href="{{ route('admin.rechte') }}"
                    class="pb-2 {{ request()->routeIs('admin.rechte') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Rechte') }}
                </a>
                <a
                    href="{{ route('admin.geschaeftsbereiche') }}"
                    class="pb-2 {{ request()->routeIs('admin.geschaeftsbereiche') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Geschäftsbereiche') }}
                </a>
            </div>

            <div class="flex flex-1 min-h-0 flex-col">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
