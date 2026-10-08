<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Benutzername -->
        @if (request('hinweis') === 'konto-inaktiv')
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                {{ __('Ihr Konto ist deaktiviert. Sie wurden daher abgemeldet. Bitte wenden Sie sich bei Fragen an Ihre zuständige Administration.') }}
            </div>
        @endif
        @if (request('hinweis') === 'organisation-inaktiv')
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                {{ __('Ihre Organisation ist in dieser Vectory-Installation nicht mehr aktiv. Sie wurden daher abgemeldet. Bitte wenden Sie sich bei Fragen an Ihre zuständige Administration.') }}
            </div>
        @endif

        <div>
            <x-input-label for="username" :value="__('Benutzername')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Passwort')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Angemeldet bleiben') }}</span>
            </label>
        </div>

        <div class="mt-4 text-right">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('activation.request') }}">{{ __('Aktivierungslink verloren?') }}</a>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Passwort vergessen?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Anmelden') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
