{{--
    Ralf, 2026-09-12: reine Zusammenfassung (Vietto-Vorbild: dort auch nur
    ein Zähler in den Projektdetails, siehe Vietto-Analyse) - die
    eigentliche Zuordnung/das Abhaken läuft im eigenen "Checklisten"-Reiter
    neben "Workflow", nicht hier in den Stammdaten.
--}}
@php
    $checklistPointsTotal = $project->projectChecklists->sum(fn ($pc) => $pc->checklist->pointsCount());
    $checklistPointsDone = $project->projectChecklistPoints->where('done', true)->count();
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Checkliste') }}</label>
    <div class="mt-0.5 text-gray-700">
        @if ($project->projectChecklists->isEmpty())
            <span class="text-gray-400">&ndash; {{ __('Keine Checklisten aktiviert') }} &ndash;</span>
        @else
            {{ trans_choice(':count Checkliste|:count Checklisten', $project->projectChecklists->count(), ['count' => $project->projectChecklists->count()]) }},
            {{ $checklistPointsDone }}/{{ $checklistPointsTotal }} {{ __('Punkte erledigt') }}
        @endif
    </div>
</div>
