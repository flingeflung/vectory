{{--
    Ralf, 2026-09-11: fest ans Projekt gebunden (analog Vietto-Footer),
    bewusst NICHT über die Projektattribute-Verwaltung konfigurierbar -
    gehört immer und unveränderlich dazu. Archivstatus bewusst
    ausgelassen ("dann lass den Archivstatus erst mal raus").
    Befüllung von created_by_user_id/updated_by_user_id läuft automatisch
    über ProjectObserver, nicht hier.
--}}
@php
    // Wie in der Topbar (layouts/topbar.blade.php): "Nachname, Vorname" aus
    // der verknüpften Person, User.name nur als Fallback ohne Person-Link.
    $creatorName = $project->createdByUser?->person?->fullName() ?? $project->createdByUser?->name;
    $editorName = $project->updatedByUser?->person?->fullName() ?? $project->updatedByUser?->name;
@endphp
<div class="!mt-4 space-y-0.5 text-xs text-gray-400">
    <div>
        {{ __('Angelegt') }}:
        {{ $creatorName ?? __('unbekannt') }}
        @if ($project->createdByUser?->person)<x-absence-icon :person="$project->createdByUser->person" />@endif
        {{ __('am') }} {{ $project->created_at->format('d.m.Y, H:i:s') }} {{ __('Uhr') }}
    </div>
    <div>
        {{ __('Zuletzt geändert') }}:
        @if ($editorName)
            {{ $editorName }}
            @if ($project->updatedByUser?->person)<x-absence-icon :person="$project->updatedByUser->person" />@endif
            {{ __('am') }} {{ $project->updated_at->format('d.m.Y, H:i:s') }} {{ __('Uhr') }}
        @else
            &ndash;
        @endif
    </div>
</div>
