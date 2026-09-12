@props(['title'])

@php
    // Gruppierte Seitenleiste statt einer einzeiligen Reiterleiste (Ralf,
    // 2026-09-12, per Screenshot: "So sieht meine Admin-Leiste mittlerweile
    // aus. Wir müssen Übersicht reinbringen!" - bei 13 Reitern in einer
    // Zeile). Skaliert besser als weitere Admin-Seiten dazukommen, im
    // Unterschied zur vorherigen flachen Liste.
    $adminNavGroups = [
        __('Personen & Rechte') => [
            ['route' => 'admin.personen', 'match' => 'admin.personen*', 'label' => __('Personen')],
            ['route' => 'admin.rechte', 'match' => 'admin.rechte', 'label' => __('Rechte')],
            ['route' => 'admin.function-groups', 'match' => 'admin.function-groups', 'label' => __('Funktionsgruppen')],
        ],
        __('Projekt-Konfiguration') => [
            ['route' => 'admin.projektkategorien', 'match' => 'admin.projektkategorien*', 'label' => __('Projektkategorien')],
            ['route' => 'admin.projektattribute', 'match' => 'admin.projektattribute*', 'label' => __('Projektattribute')],
            ['route' => 'admin.projektkopie-vorlagen', 'match' => 'admin.projektkopie-vorlagen*', 'label' => __('Projektkopie-Vorlagen')],
            ['route' => 'admin.maerkte', 'match' => 'admin.maerkte*', 'label' => __('Märkte')],
            ['route' => 'admin.workflows', 'match' => 'admin.workflows*', 'label' => __('Workflows')],
            ['route' => 'admin.checklisten', 'match' => 'admin.checklisten*', 'label' => __('Checklisten')],
        ],
        __('Kommunikation') => [
            ['route' => 'admin.mail-vorlagen', 'match' => 'admin.mail-vorlagen*', 'label' => __('Mail-Vorlagen')],
            ['route' => 'admin.hilfeseiten', 'match' => 'admin.hilfeseiten*', 'label' => __('Hilfeseiten'), 'gate' => 'access-superadmin'],
        ],
        __('Mandant') => [
            ['route' => 'admin.config', 'match' => 'admin.config', 'label' => __('Stammdaten')],
            ['route' => 'admin.kunden', 'match' => 'admin.kunden*', 'label' => __('Kunden'), 'if' => \App\Models\SystemSetting::multiTenantEnabled()],
            ['route' => 'admin.superadmin', 'match' => 'admin.superadmin', 'label' => __('Superadmin'), 'gate' => 'access-superadmin'],
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 gap-6">
            <nav class="w-52 shrink-0 space-y-4 overflow-y-auto text-sm">
                @foreach ($adminNavGroups as $groupLabel => $items)
                    @php
                        $visibleItems = collect($items)->filter(fn ($item) => (! isset($item['if']) || $item['if']) && (! isset($item['gate']) || auth()->user()->can($item['gate'])));
                    @endphp
                    @if ($visibleItems->isNotEmpty())
                        <div>
                            <div class="mb-1 px-2 text-xs font-semibold text-gray-400">{{ $groupLabel }}</div>
                            <div class="space-y-0.5">
                                @foreach ($visibleItems as $item)
                                    <a
                                        onclick="return window.navigateOrConfirm(event)"
                                        href="{{ route($item['route']) }}"
                                        class="block rounded-md px-2 py-1.5 {{ request()->routeIs($item['match']) ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

            <div class="flex flex-1 min-h-0 flex-col">
                {{-- Ralf: klein, aber sichtbar machen, für welchen Kunden die
                     Einstellungen auf dieser Seite gerade gelten - nicht bei
                     Personen (hat ihren eigenen Kunde-Filter/-Hinweis), Kunden
                     (die Seite IST die Kundenverwaltung), Superadmin und
                     Hilfeseiten (beide mandantenübergreifend, kein einzelner
                     Kunde). --}}
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
    </div>
</x-app-layout>
