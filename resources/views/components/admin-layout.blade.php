@props(['title'])

@php
    // Nav-Ebene 1 (Themen: Personen & Rechte, Projekt-Konfiguration, ...)
    // sitzt jetzt in der Haupt-Sidebar links (sidebar.blade.php, aufklappt
    // unter "Admin"), nicht mehr hier als eigene Leiste. Hier bleibt nur
    // noch Nav-Ebene 2: die Reiter der gerade aktiven Themen-Gruppe (Ralf,
    // 2026-09-12, zweiter Anlauf nach der ersten Restrukturierung - "an die
    // roten Felder die Themen packen, Unterthemen wieder als Reiter über
    // die Inhaltsbereiche"). Siehe App\Support\AdminNav für die gemeinsame
    // Gruppen-Definition.
    $currentGroupItems = \App\Support\AdminNav::visibleGroups()->get(\App\Support\AdminNav::currentGroupLabel(), collect());
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <div class="mb-4 flex shrink-0 gap-4 border-b border-gray-200 text-sm">
                @foreach ($currentGroupItems as $item)
                    <a
                        onclick="return window.navigateOrConfirm(event)"
                        href="{{ route($item['route']) }}"
                        class="pb-2 {{ request()->routeIs($item['match']) ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

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
