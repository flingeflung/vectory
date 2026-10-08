<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Guten Tag :name, wählen Sie bitte Ihren Benutzernamen und Ihr Passwort, um Ihr Konto zu aktivieren.', ['name' => $user->person?->first_name ?: $user->name]) }}
    </div>

    <form method="POST" action="{{ route('activation.complete', $token) }}">
        @csrf

        <div>
            <x-input-label for="username" :value="__('Benutzername')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Mindestens 4 Zeichen: Buchstaben, Zahlen, Punkt, Bindestrich oder Unterstrich.') }}</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PasswordPolicy::hint() }}</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Passwort wiederholen')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>{{ __('Konto aktivieren') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
