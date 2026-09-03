<header class="h-16 shrink-0 bg-topbar text-topbar-content flex items-center justify-between px-4 sm:px-6">
    <button
        @click="sidebarOpen = !sidebarOpen"
        class="p-2 -ml-2 rounded-md hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/50"
        aria-label="{{ __('Navigation ein-/ausblenden') }}"
    >
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div x-data="{ open: false }" @click.outside="open = false" class="relative">
        <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium hover:text-white/80 focus:outline-none">
            {{ Auth::user()->name }}
            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            x-show="open"
            x-transition
            class="absolute right-0 z-50 mt-2 w-48 rounded-md bg-white py-1 text-sm text-gray-700 shadow-lg"
            style="display: none;"
        >
            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-gray-100">{{ __('Profil') }}</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-gray-100">{{ __('Abmelden') }}</button>
            </form>
        </div>
    </div>
</header>
