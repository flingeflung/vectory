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
        <div class="space-y-2">
            <h3 class="text-sm font-semibold text-gray-900">{{ $translation->title }}</h3>
            {{-- Kein @tailwindcss/typography installiert - Markdown-Ausgabe
                 stattdessen mit ein paar gezielten Arbitrary-Variants
                 lesbar machen (Preflight setzt sonst list-style:none, ohne
                 sichtbare Aufzählungszeichen/Nummerierung). --}}
            <div class="max-w-none space-y-2 text-sm text-gray-700 [&_a]:text-indigo-600 [&_a]:underline [&_h1]:mt-3 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:text-gray-900 [&_h2]:mt-3 [&_h2]:text-sm [&_h2]:font-semibold [&_h2]:text-gray-900 [&_ol]:list-decimal [&_ol]:space-y-0.5 [&_ol]:pl-5 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:space-y-0.5 [&_ul]:pl-5">{!! $translation->bodyHtml() !!}</div>
        </div>
    @else
        <div class="px-1 py-2 text-sm text-gray-400">{{ __('Für diese Seite gibt\'s noch keine Hilfeseite - oben suchen findet vielleicht trotzdem etwas Passendes.') }}</div>
    @endif
</div>
