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
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.personen') }}"
                    class="pb-2 {{ request()->routeIs('admin.personen*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Personen') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.rechte') }}"
                    class="pb-2 {{ request()->routeIs('admin.rechte') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Rechte') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.function-groups') }}"
                    class="pb-2 {{ request()->routeIs('admin.function-groups') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Funktionsgruppen') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.config') }}"
                    class="pb-2 {{ request()->routeIs('admin.config') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Stammdaten') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.projektkategorien') }}"
                    class="pb-2 {{ request()->routeIs('admin.projektkategorien*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Projektkategorien') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.projektattribute') }}"
                    class="pb-2 {{ request()->routeIs('admin.projektattribute*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Projektattribute') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.projektkopie-vorlagen') }}"
                    class="pb-2 {{ request()->routeIs('admin.projektkopie-vorlagen*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Projektkopie-Vorlagen') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.maerkte') }}"
                    class="pb-2 {{ request()->routeIs('admin.maerkte*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Märkte') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.workflows') }}"
                    class="pb-2 {{ request()->routeIs('admin.workflows*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Workflows') }}
                </a>
                <a
                    onclick="return window.navigateOrConfirm(event)"
                    href="{{ route('admin.mail-vorlagen') }}"
                    class="pb-2 {{ request()->routeIs('admin.mail-vorlagen*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ __('Mail-Vorlagen') }}
                </a>
                @if (\App\Models\SystemSetting::multiTenantEnabled())
                    <a
                        onclick="return window.navigateOrConfirm(event)"
                        href="{{ route('admin.kunden') }}"
                        class="pb-2 {{ request()->routeIs('admin.kunden*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >
                        {{ __('Kunden') }}
                    </a>
                @endif
                @can('access-superadmin')
                    <a
                        onclick="return window.navigateOrConfirm(event)"
                        href="{{ route('admin.hilfeseiten') }}"
                        class="pb-2 {{ request()->routeIs('admin.hilfeseiten*') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >
                        {{ __('Hilfeseiten') }}
                    </a>
                    <a
                        onclick="return window.navigateOrConfirm(event)"
                        href="{{ route('admin.superadmin') }}"
                        class="pb-2 {{ request()->routeIs('admin.superadmin') ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >
                        {{ __('Superadmin') }}
                    </a>
                @endcan
            </div>

            {{-- Ralf: klein, aber sichtbar machen, für welchen Kunden die
                 Einstellungen auf dieser Seite gerade gelten - nicht bei
                 Personen (hat ihren eigenen Kunde-Filter/-Hinweis), Kunden
                 (die Seite IST die Kundenverwaltung) und Superadmin
                 (mandantenübergreifend, kein einzelner Kunde). --}}
            @if (\App\Models\SystemSetting::multiTenantEnabled() && ! request()->routeIs('admin.personen*', 'admin.kunden*', 'admin.superadmin', 'admin.hilfeseiten*'))
                <div class="mb-2 shrink-0 text-xs text-gray-400">
                    {{ __('Gültig für Kunde: :tenant', ['tenant' => \App\Support\CurrentTenant::current()?->short_name ?? \App\Support\CurrentTenant::current()?->name ?? '?']) }}
                </div>
            @endif

            <div class="flex flex-1 min-h-0 flex-col">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
