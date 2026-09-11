{{--
    Ralf, 2026-09-11: fest ans Projekt gebunden (analog Vietto-Footer),
    bewusst NICHT über die Projektattribute-Verwaltung konfigurierbar -
    gehört immer und unveränderlich dazu. Archivstatus bewusst
    ausgelassen ("dann lass den Archivstatus erst mal raus").
    Befüllung von created_by_user_id/updated_by_user_id läuft automatisch
    über ProjectObserver, nicht hier.
--}}
<div class="!mt-4 space-y-0.5 text-xs text-gray-400">
    <div>
        {{ __('Angelegt') }}:
        {{ $project->createdByUser?->name ?? __('unbekannt') }}
        {{ __('am') }} {{ $project->created_at->format('d.m.Y, H:i:s') }} {{ __('Uhr') }}
    </div>
    <div>
        {{ __('Zuletzt geändert') }}:
        @if ($project->updatedByUser)
            {{ $project->updatedByUser->name }}
            {{ __('am') }} {{ $project->updated_at->format('d.m.Y, H:i:s') }} {{ __('Uhr') }}
        @else
            &ndash;
        @endif
    </div>
</div>
