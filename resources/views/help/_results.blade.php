{{--
    Austauschbares Ergebnis-Fragment im Hilfe-Panel (siehe
    components/help-panel.blade.php) - wird per fetch() sowohl beim Öffnen
    (Kontextartikel zur aktuellen Seite) als auch bei jeder Sucheingabe
    (window.liveFilterSearch) neu eingesetzt. id="help-results" ist der
    Container, den liveFilterSearch austauscht - MUSS bei jedem Zustand
    (Suche/Artikel/leer) erhalten bleiben.
--}}
<div id="help-results">
    @if ($mode === 'search')
        @if ($results->isEmpty())
            <div class="px-1 py-2 text-sm text-gray-400">{{ __('Keine Treffer für ":query".', ['query' => $query]) }}</div>
        @else
            <div class="space-y-0.5">
                @foreach ($results as $result)
                    @php($resultTranslation = $result->translation(app()->getLocale()))
                    <button
                        type="button"
                        @click="window.helpOpenArticle({{ \Illuminate\Support\Js::from($result->key) }})"
                        class="block w-full rounded px-2 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50"
                    >
                        {{ $resultTranslation?->title ?? $result->key }}
                    </button>
                @endforeach
            </div>
        @endif
    @elseif ($article && $translation)
        @if (($dialogId ?? '') !== '' && ! ($dialogHasArticle ?? false))
            <div class="mb-3 rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-500">
                {{ __('Zu diesem Dialog (:id) gibt es noch keine eigene Hilfeseite. Hier die Hilfe zur Seite dahinter:', ['id' => $dialogId]) }}
                @if ($canManageHelp ?? false)
                    <div class="mt-1 flex items-center gap-1 rounded bg-white px-1.5 py-1">
                        <code class="flex-1 select-all font-mono text-gray-800">{{ $helpKey ?? $dialogId }}</code>
                        <x-copy-button :text="$helpKey ?? $dialogId" />
                    </div>
                @endif
            </div>
        @endif
        @php($ownKey = ($dialogId ?? '') !== '' ? ($helpKey ?? $dialogId) : ($routeName ?? ''))
        @if (($canManageHelp ?? false) && $ownKey !== '')
            <div class="mb-2 flex items-center gap-1 rounded bg-gray-50 px-1.5 py-1 text-xs text-gray-500" title="{{ __('Schlüssel dieses Fensters bzw. Reiters oder Name dieser Seite, für "Seiten (Routennamen)" in der Hilfeverwaltung') }}">
                <span class="shrink-0">{{ ($dialogId ?? '') !== '' ? __('Schlüssel:') : __('Seite:') }}</span>
                <code class="flex-1 select-all font-mono text-gray-800">{{ $ownKey }}</code>
                <x-copy-button :text="$ownKey" />
            </div>
        @endif
        @php($crumbs = array_values(array_filter($article->ancestors(), fn ($crumb) => $crumb->isVisibleTo(auth()->user()))))
        @if ($crumbs)
            <nav class="mb-2 flex flex-wrap items-center gap-x-1 text-xs text-gray-500" aria-label="{{ __('Pfad') }}">
                @foreach ($crumbs as $crumb)
                    <a href="#" data-help-key="{{ $crumb->key }}" class="text-indigo-600 hover:underline">{{ $crumb->translation(app()->getLocale())?->title ?? $crumb->key }}</a>
                    <span aria-hidden="true">›</span>
                @endforeach
                <span class="text-gray-700">{{ $translation->title }}</span>
            </nav>
        @endif
        <span hidden data-help-current-key="{{ $article->key }}"></span>
        @include('help._article', ['translation' => $translation])
    @else
        <div class="space-y-2 px-1 py-2 text-sm text-gray-400">
            <div>{{ __('Für diese Seite gibt\'s noch keine Hilfeseite - oben suchen findet vielleicht trotzdem etwas Passendes.') }}</div>
            @if (($canManageHelp ?? false) && ($dialogId ?? '') !== '')
                <div class="rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-600">
                    {{ __('Schlüssel dieses Fensters bzw. des sichtbaren Reiters (bei "Seiten (Routennamen)" eintragen, um eine eigene Hilfeseite dafür anzulegen; nur die Dialog-ID vor dem # gilt für alle Reiter):') }}
                    <div class="mt-1 flex items-center gap-1 rounded bg-white px-1.5 py-1">
                        <code class="flex-1 select-all font-mono text-gray-800">{{ $helpKey ?? $dialogId }}</code>
                        <x-copy-button :text="$helpKey ?? $dialogId" />
                    </div>
                </div>
            @endif
            @if (($canManageHelp ?? false) && ($routeName ?? '') !== '')
                <div class="rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-600">
                    {{ __('Neue Hilfeseite dafür anlegen: bei "Seiten (Routennamen)" diesen Wert eintragen:') }}
                    <div class="mt-1 flex items-center gap-1 rounded bg-white px-1.5 py-1">
                        <code class="flex-1 select-all font-mono text-gray-800">{{ $routeName }}</code>
                        <x-copy-button :text="$routeName" />
                    </div>
                    <a href="{{ route('admin.hilfeseiten') }}" target="_blank" class="mt-1 inline-block text-indigo-600 hover:underline">{{ __('Zu den Hilfeseiten') }} &rarr;</a>
                </div>
            @endif
        </div>
    @endif
</div>
