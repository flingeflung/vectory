{{--
    Rekursive 3-Ebenen-Navigation im Hilfe-Panel (Ralf, 2026-09-15) -
    Gegenstück zu admin/help-articles/partials/tree.blade.php, hier aber
    nur zum Anzeigen/Anklicken (kein Sortieren). $nodes kommt aus
    HelpArticle::tree() bzw. dessen 'children'. Klick nutzt denselben
    data-help-key-Mechanismus wie "[[Titel]]"-Verweise im Fließtext (Event-
    Delegation sitzt in components/help-panel.blade.php).
--}}
@if ($nodes->isNotEmpty())
    <ul class="{{ $topLevel ?? false ? '' : 'pl-3' }} space-y-0.5">
        @foreach ($nodes as $node)
            @php($nodeTitle = $node->translation(app()->getLocale())?->title ?? $node->key)
            <li>
                <a
                    href="#"
                    data-help-key="{{ $node->key }}"
                    class="block truncate rounded px-1.5 py-1 text-gray-700 hover:bg-gray-50"
                    title="{{ $nodeTitle }}"
                >{{ $nodeTitle }}</a>
                @include('help._nav', ['nodes' => $node->children, 'topLevel' => false])
            </li>
        @endforeach
    </ul>
@endif
