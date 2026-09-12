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

    <div class="flex items-center gap-4">
        @php($availableTenants = \App\Support\CurrentTenant::availableTenants())
        @if ($availableTenants->count() > 1)
            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-1.5 rounded-md border border-white/20 px-2.5 py-1 text-sm font-medium hover:bg-white/10 focus:outline-none"
                    title="{{ __('Mandant wechseln') }}"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                    {{ \App\Support\CurrentTenant::current()?->name }}
                    <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="absolute right-0 z-50 mt-2 w-56 rounded-md bg-white py-1 text-sm text-gray-700 shadow-lg"
                    style="display: none;"
                >
                    <div class="border-b border-gray-100 px-4 py-1.5 text-xs font-semibold text-gray-400">{{ __('Mandant wechseln') }}</div>
                    @foreach ($availableTenants as $tenant)
                        <form method="POST" action="{{ route('mandant.wechseln') }}">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <button
                                type="submit"
                                class="flex w-full items-center justify-between px-4 py-2 text-left hover:bg-gray-100 {{ $tenant->id === \App\Support\CurrentTenant::id() ? 'font-semibold text-gray-900' : '' }}"
                            >
                                {{ $tenant->name }}
                                @if ($tenant->id === \App\Support\CurrentTenant::id())
                                    <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Hilfesystem (Ralf, 2026-09-12): auf jeder Seite erreichbar, fester
             Platz unabhängig davon, ob der Mandanten-Umschalter daneben
             angezeigt wird. --}}
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'help-panel' }))"
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-white/20 text-sm font-medium hover:bg-white/10 focus:outline-none"
            title="{{ __('Hilfe zu dieser Seite') }}"
        >
            ?
        </button>

        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium hover:text-white/80 focus:outline-none">
                {{ Auth::user()->person?->fullName() ?? Auth::user()->name }}
                @if (Auth::user()->person)
                    <x-absence-icon :person="Auth::user()->person" />
                @endif
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
                <a href="{{ route('settings') }}" class="block px-4 py-2 hover:bg-gray-100">{{ __('Einstellungen') }}</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-gray-100">{{ __('Abmelden') }}</button>
                </form>
            </div>
        </div>
    </div>
</header>
