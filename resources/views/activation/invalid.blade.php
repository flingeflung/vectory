<x-guest-layout>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
        {{ __('Dieser Aktivierungslink ist ungültig, abgelaufen oder wurde schon verwendet.') }}
    </div>
    <p class="text-sm text-gray-600">
        {{ __('Wenn Ihr Konto noch nicht aktiviert ist, können Sie hier einen neuen Link anfordern.') }}
    </p>
    <div class="mt-4 flex items-center justify-between">
        <a class="text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('login') }}">{{ __('Zur Anmeldung') }}</a>
        <a class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover" href="{{ route('activation.request') }}">{{ __('Neuen Link anfordern') }}</a>
    </div>
</x-guest-layout>
