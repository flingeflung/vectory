{{--
    Titel + gerenderter Markdown-Body eines Hilfeartikels - eigener Partial,
    weil sowohl das echte Hilfe-Panel (help/_results.blade.php) als auch die
    Vorschau in der Hilfeseiten-Verwaltung (admin/help-articles) exakt
    dieselbe Darstellung brauchen. $translation kann auch ein nicht
    gespeichertes HelpArticleTranslation-Objekt sein (siehe
    HelpArticleController::preview()).
--}}
<div class="space-y-2">
    {{-- Bewusst gedeckter als eine echte Überschrift im Markdown-Text
         (text-gray-500 statt -900) - Ralf: von einer "echten" ÜS1 im
         Text kaum zu unterscheiden gewesen. --}}
    <h3 class="text-sm font-semibold text-gray-500">{{ $translation->title }}</h3>
    {{-- Kein @tailwindcss/typography installiert - Markdown-Ausgabe
         stattdessen mit ein paar gezielten Arbitrary-Variants lesbar
         machen (Preflight setzt sonst list-style:none, ohne sichtbare
         Aufzählungszeichen/Nummerierung). --}}
    <div class="max-w-none space-y-2 text-sm text-gray-700 [&_a]:text-indigo-600 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-gray-300 [&_blockquote]:pl-2 [&_blockquote]:text-gray-500 [&_code]:rounded [&_code]:bg-gray-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs [&_h1]:mt-3 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:text-gray-900 [&_h2]:mt-3 [&_h2]:text-sm [&_h2]:font-semibold [&_h2]:text-gray-900 [&_img]:my-1 [&_img]:max-h-32 [&_img]:cursor-zoom-in [&_img]:rounded-md [&_img]:border [&_img]:border-gray-200 [&_ol]:list-decimal [&_ol]:space-y-0.5 [&_ol]:pl-5 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:space-y-0.5 [&_ul]:pl-5">{!! $translation->bodyHtml() !!}</div>
</div>
