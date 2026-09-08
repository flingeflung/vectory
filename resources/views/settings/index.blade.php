<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Einstellungen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-sm font-medium text-gray-900">{{ __('Projektübersicht') }}</h3>
                <form method="POST" action="{{ route('settings.update') }}" class="mt-3">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            name="hide_discarded_projects_on_reset"
                            value="1"
                            @checked(auth()->user()->hide_discarded_projects_on_reset)
                            onchange="this.form.submit()"
                            class="mt-0.5 rounded border-gray-300"
                        >
                        {{ __('Beim Zurücksetzen des Projektfilters den Status immer anzeigen und dort verworfene Projekte immer ausblenden.') }}
                    </label>
                    @if (session('status') === 'settings-updated')
                        <p class="mt-2 text-xs text-green-600">{{ __('Gespeichert.') }}</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
