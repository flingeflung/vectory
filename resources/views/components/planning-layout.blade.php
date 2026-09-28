@props(['title'])

{{--
    Gleiches Design wie der Admin-Bereich (Ralf, 2026-09-28: "wir nehmen den
    Admin-Bereich für das Design als Vorlage. Oben 1-n Reiter, darunter der
    Inhalt."), siehe admin-layout.blade.php - hier nur eine flache Reiter-
    Ebene statt Themen-Gruppen (siehe App\Support\PlanningNav).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Planung') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <div class="mb-4 flex shrink-0 gap-4 border-b border-gray-200 text-sm">
                @foreach (\App\Support\PlanningNav::tabs() as $item)
                    <a
                        onclick="return window.navigateOrConfirm(event)"
                        href="{{ route($item['route']) }}"
                        class="pb-2 {{ request()->routeIs($item['match']) ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="flex flex-1 min-h-0 flex-col">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
