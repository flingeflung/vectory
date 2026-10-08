<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Ihr Aktivierungslink ist abgelaufen oder verloren gegangen? Geben Sie die E-Mail-Adresse an, für die Ihr Zugang vorbereitet wurde, und wir senden Ihnen einen neuen Link.') }}
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('activation.request.send') }}">
        @csrf
        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a class="text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('login') }}">{{ __('Zur Anmeldung') }}</a>
            <x-primary-button>{{ __('Link anfordern') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
