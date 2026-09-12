<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Einstellungen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            {{-- Abwesenheits-Markierung (Ralf, 2026-09-12): zeigt sich als
                 kleines Symbol mit Tooltip überall im Tool, wo diese Person
                 auftaucht (Projektbeteiligte, Funktionsgruppen, WFS-
                 Zuständige, ...), plus oben rechts am eigenen Profilnamen. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" x-data="{ dirty: false }">
                <h3 class="text-sm font-medium text-gray-900">{{ __('Abwesenheit') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('Wird als kleines Symbol überall dort angezeigt, wo du im Tool namentlich auftauchst - z. B. bei Projektbeteiligten oder Funktionsgruppen.') }}</p>
                <form method="POST" action="{{ route('settings.absence.update') }}" class="mt-3 space-y-2" @input="dirty = window.formIsDirty($el)">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            name="is_absent"
                            value="1"
                            @checked(old('is_absent', auth()->user()->person?->is_absent))
                            class="mt-0.5 rounded border-gray-300"
                        >
                        {{ __('Ich bin aktuell abwesend.') }}
                    </label>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Abwesend bis (optional)') }}</label>
                        <input
                            type="date"
                            name="absent_until"
                            value="{{ old('absent_until', auth()->user()->person?->absent_until?->format('Y-m-d')) }}"
                            class="mt-0.5 w-full max-w-xs rounded-md border-gray-300 text-sm @error('absent_until', 'absence') border-red-300 @enderror"
                        >
                        @error('absent_until', 'absence')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                    @if (session('status') === 'absence-updated')
                        <p class="text-xs text-green-600">{{ __('Gespeichert.') }}</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
