<x-admin-layout>
    <div class="max-w-2xl">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div
                x-data="{ dirty: false, show: false }"
                x-init="@if (session('status') === 'config-updated') show = true; setTimeout(() => show = false, 2000) @endif"
            >
                <form method="POST" action="{{ route('admin.config.update') }}" @input="dirty = true" class="space-y-5">
                    @csrf

                    @foreach ($settings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ $setting['label'] }}</label>
                            <input
                                type="text"
                                name="values[{{ $setting['key'] }}]"
                                value="{{ old('values.'.$setting['key'], $setting['value']) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <p class="mt-1 text-xs text-gray-400">{{ $setting['description'] }}</p>
                            <x-input-error :messages="$errors->get('values.'.$setting['key'])" class="mt-1" />
                        </div>
                    @endforeach

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            x-show="dirty"
                            x-cloak
                            class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                        >
                            {{ __('Speichern') }}
                        </button>
                        <p x-show="show" x-cloak x-transition class="text-sm text-green-600">{{ __('Gespeichert.') }}</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
