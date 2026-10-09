{{--
    Rekursive 3-Ebenen-Navigation im Hilfe-Panel (Ralf, 2026-09-15) -
    Gegenstück zu admin/help-articles/partials/tree.blade.php, hier aber
    nur zum Anzeigen/Anklicken (kein Sortieren). $nodes kommt aus
    HelpArticle::tree() bzw. dessen 'children'. Klick nutzt denselben
    data-help-key-Mechanismus wie "[[Titel]]"-Verweise im Fließtext (Event-
    Delegation sitzt in components/help-panel.blade.php).
--}}
@if ($nodes->isNotEmpty())
    {{-- Ebenen mit senkrechter Bezugslinie und waagerechter Abzweigung je Eintrag (Ralf, 2026-10-09); beim letzten Eintrag endet die Linie an der Abzweigung --}}
    <ul class="{{ $topLevel ?? false ? '' : 'ml-2.5' }} space-y-0.5">
        @foreach ($nodes as $node)
            @php($nodeTitle = $node->translation(app()->getLocale())?->title ?? $node->key)
            <li @class([
                'relative',
                'pl-3 before:absolute before:left-0 before:top-0 before:w-px before:bg-gray-300 after:absolute after:left-0 after:top-3.5 after:h-px after:w-2.5 after:bg-gray-300' => ! ($topLevel ?? false),
                'before:bottom-0' => ! ($topLevel ?? false) && ! $loop->last,
                'before:h-3.5' => ! ($topLevel ?? false) && $loop->last,
            ])>
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
