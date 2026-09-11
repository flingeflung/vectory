{{-- Eine Zeile "Verknüpfte Projekte". Eigene Partial, damit
     ProjectConnectionController::store() nach dem Anlegen NUR diese eine
     Zeile zurückgeben kann (statt die komplette Liste neu zu laden - bei
     ~6700 Projekten (Sanitär) war das spürbar langsam, siehe Ralf-Bug-
     Report). Braucht $p (das verknüpfte Projekt), $connectionId,
     $label - sowie $loading/removeConnection aus der Alpine-Umgebung des
     einbindenden Modals. --}}
<div class="border-b border-gray-100 py-1 last:border-0" id="connected-row-{{ $p->id }}">
    <div class="flex items-start gap-1.5 text-xs text-gray-700">
        <input type="checkbox" checked :disabled="loading" @click.prevent="removeConnection({{ $connectionId }}, {{ $p->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
        <span>{{ $p->source_pn }} &ndash; {{ $p->title }}</span>
    </div>
    <div class="ml-5 text-[11px] text-gray-400">{{ $label }}</div>
</div>
