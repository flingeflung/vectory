<aside
    x-show="sidebarOpen"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="w-64 h-full shrink-0 bg-sidebar border-r border-gray-200 flex flex-col overflow-hidden"
>
    <div class="h-16 flex items-center px-4 border-b border-gray-100 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo class="h-8 w-auto shrink-0 fill-current text-topbar" />
            <span class="font-semibold text-gray-800 truncate">{{ config('app.name') }}</span>
        </a>
    </div>

    <div class="px-4 py-3 shrink-0">
        <label for="quicksearch" class="sr-only">{{ __('Schnellsuche') }}</label>
        <input
            id="quicksearch"
            type="search"
            placeholder="{{ __('Schnellsuche') }}"
            disabled
            class="w-full rounded-md border-gray-300 text-sm text-gray-400 placeholder-gray-400 focus:border-topbar focus:ring-topbar"
        />
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-2 space-y-1">
        <a
            href="{{ route('dashboard') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Startseite') }}
        </a>
        <a
            href="{{ route('projekte') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('projekte') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Projekte') }}
        </a>
        <button
            type="button"
            x-data
            @click="$dispatch('open-modal', 'favorites')"
            class="flex w-full items-center px-3 py-2 rounded-md text-sm font-medium text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover"
        >
            {{ __('Favoriten') }}
        </button>
        <a
            href="{{ route('aufgaben') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('aufgaben') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Aufgaben') }}
        </a>
    </nav>
</aside>
