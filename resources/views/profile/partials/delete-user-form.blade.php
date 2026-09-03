<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Konto löschen') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Sobald dein Konto gelöscht ist, werden alle zugehörigen Ressourcen und Daten unwiderruflich gelöscht. Lade dir vor dem Löschen bitte alle Daten herunter, die du behalten möchtest.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Konto löschen') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable :dirty-check="'deleteAccountIsDirty'">
        <form id="delete-account-form" method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <div class="flex items-start justify-between">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ __('Möchtest du dein Konto wirklich löschen?') }}
                </h2>
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Abbrechen') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Sobald dein Konto gelöscht ist, werden alle zugehörigen Ressourcen und Daten unwiderruflich gelöscht. Bitte gib dein Passwort ein, um die Löschung zu bestätigen.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Passwort') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Passwort') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Abbrechen') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Konto löschen') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>

    <script>
        (function () {
            const form = () => document.getElementById('delete-account-form');
            let savedSnapshot = null;

            const serializeForm = (f) => f ? new URLSearchParams(new FormData(f)).toString() : null;
            const snapshot = () => { savedSnapshot = serializeForm(form()); };

            window.deleteAccountIsDirty = () => {
                const current = serializeForm(form());
                return current !== null && current !== savedSnapshot;
            };

            const snapshotWhenSettled = () => queueMicrotask(() => queueMicrotask(snapshot));

            window.addEventListener('open-modal', (event) => {
                if (event.detail === 'confirm-user-deletion') {
                    snapshotWhenSettled();
                }
            });

            if (document.readyState === 'complete') {
                snapshotWhenSettled();
            } else {
                window.addEventListener('load', snapshotWhenSettled);
            }
        })();
    </script>
</section>
