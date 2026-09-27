{{--
    Kopfzeile über den 3 Reitern des Zeiterfassung-Overlays (Ralf, 2026-09-27:
    "gib mir oben im Overlay, über den 3 Reitern, einen Hinweis, in welchem
    Projekt ich unterwegs bin"). In allen drei Reiter-Partials eingebunden,
    damit sie beim Reiterwechsel (eigener fetch() je Reiter, siehe
    switchProjectTimeTrackingTab in layouts/app.blade.php) immer mitkommt.
--}}
<div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
    <span class="shrink-0 text-xs font-semibold text-gray-700">{{ $project->source_pn }}</span>
    <span class="truncate text-xs text-gray-500">{{ $project->title }}</span>
    @if ($project->verbund_rolle === 1)
        <span class="ml-auto shrink-0 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">{{ __('Hauptprojekt') }}</span>
    @endif
</div>
