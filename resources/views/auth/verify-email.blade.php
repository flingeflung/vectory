<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Danke für deine Registrierung! Bevor es losgeht, bestätige bitte deine E-Mail-Adresse über den Link, den wir dir gerade geschickt haben. Falls du die E-Mail nicht erhalten hast, senden wir dir gerne eine neue.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('Ein neuer Bestätigungslink wurde an die bei der Registrierung angegebene E-Mail-Adresse gesendet.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Bestätigungs-E-Mail erneut senden') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Abmelden') }}
            </button>
        </form>
    </div>
</x-guest-layout>
