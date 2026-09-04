<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projekt') }} {{ $project->source_pn }}
        </h2>
    </x-slot>

    <div class="h-full overflow-y-auto p-4 sm:p-6">
        <div id="project-detail-container" class="w-full max-w-3xl mx-auto bg-white shadow-sm sm:rounded-lg">
            @include('projekte.partials.detail', ['overlay' => false])
        </div>
    </div>
</x-app-layout>
