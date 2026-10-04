<x-admin-layout>
    {{--
        Begriffsverzeichnis (Ralf, 2026-10-04): Zuordnung Begriff -> Seite, in Hilfetexten als ((Begriff)),
        in Oberflächentexten als <x-term>Begriff</x-term>. Links öffnen in einem neuen Browser-Tab.
    --}}
    @if (session('status') === 'saved')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('status') === 'deleted')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gelöscht.') }}</x-flash-message>
    @endif
    @if ($errors->any())
        <div class="mb-3 shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="flex min-h-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ adding: false }">
        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 p-3">
            <p class="text-xs text-gray-500">{{ __('Ein Begriff führt zur Seite, auf der man ihn ändert. In Hilfetexten schreiben Sie ((Begriff)) oder ((Begriff|Anzeigetext)).') }}</p>
            <button type="button" x-show="! adding" @click="adding = true; $nextTick(() => $refs.newTerm.focus())" class="inline-flex shrink-0 items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200">{{ __('+ Neuer Begriff') }}</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <datalist id="glossary-pages">@foreach ($pages as $page)<option value="{{ $page }}">@endforeach</datalist>
            <datalist id="glossary-abilities">@foreach ($abilities as $ability)<option value="{{ $ability }}">@endforeach</datalist>

            <form x-show="adding" x-cloak method="POST" action="{{ route('admin.begriffe.store') }}" class="grid grid-cols-12 items-end gap-2 border-b border-gray-100 bg-gray-50 p-3 text-sm">
                @csrf
                <div class="col-span-2"><label class="block text-xs text-gray-500">{{ __('Begriff') }}</label><input x-ref="newTerm" name="term" required class="w-full rounded border-gray-300 py-1 text-sm"></div>
                <div class="col-span-3"><label class="block text-xs text-gray-500">{{ __('Zielseite (Routenname)') }}</label><input name="route_name" list="glossary-pages" required class="w-full rounded border-gray-300 py-1 text-sm"></div>
                <div class="col-span-2"><label class="block text-xs text-gray-500">{{ __('Nötiges Recht') }}</label><input name="ability" list="glossary-abilities" class="w-full rounded border-gray-300 py-1 text-sm"></div>
                <div class="col-span-3"><label class="block text-xs text-gray-500">{{ __('Erklärung (ein Satz)') }}</label><input name="description" class="w-full rounded border-gray-300 py-1 text-sm"></div>
                <div class="col-span-2 flex justify-end gap-2">
                    <button type="button" @click="adding = false" class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                    <button type="submit" class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">{{ __('Speichern') }}</button>
                </div>
            </form>

            @foreach ($terms as $term)
                <div class="border-b border-gray-100 p-3 text-sm" x-data="{ dirty: false }">
                    <form method="POST" action="{{ route('admin.begriffe.update', $term) }}" @input="dirty = true" class="grid grid-cols-12 items-end gap-2">
                        @csrf
                        <div class="col-span-2"><label class="block text-xs text-gray-500">{{ __('Begriff') }}</label><input name="term" value="{{ $term->term }}" required class="w-full rounded border-gray-300 py-1 text-sm"></div>
                        <div class="col-span-3">
                            <label class="block text-xs text-gray-500">{{ __('Zielseite (Routenname)') }}</label>
                            <input name="route_name" value="{{ $term->route_name }}" list="glossary-pages" required class="w-full rounded border-gray-300 py-1 text-sm">
                            @if ($term->url() === null)
                                <span class="text-xs text-red-600">{{ __('Diese Seite gibt es nicht (mehr).') }}</span>
                            @endif
                        </div>
                        <div class="col-span-2"><label class="block text-xs text-gray-500">{{ __('Nötiges Recht') }}</label><input name="ability" value="{{ $term->ability }}" list="glossary-abilities" class="w-full rounded border-gray-300 py-1 text-sm"></div>
                        <div class="col-span-3"><label class="block text-xs text-gray-500">{{ __('Erklärung (ein Satz)') }}</label><input name="description" value="{{ $term->description }}" class="w-full rounded border-gray-300 py-1 text-sm"></div>
                        <div class="col-span-2 flex justify-end">
                            <button type="submit" x-show="dirty" x-cloak class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">{{ __('Speichern') }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.begriffe.destroy', $term) }}" class="mt-1" x-data="{ async go(e) { if (await window.confirmDialog({ title: @js(__('Begriff löschen')), message: @js(__('Der Begriff wird aus dem Verzeichnis entfernt. Texte, die ihn verwenden, zeigen ihn danach ohne Link.')), confirmLabel: @js(__('Löschen')), cancelLabel: @js(__('Abbrechen')) })) { e.target.submit(); } } }" @submit.prevent="go($event)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-600">{{ __('Löschen') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
