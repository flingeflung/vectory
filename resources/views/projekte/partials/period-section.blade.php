{{-- Inhalt des Reiters "Ablaufplan" (Diagramm mit Einsatzplan und Zeitraum-Knöpfen). Eigene Datei, damit er nach einer Terminänderung allein nachgeladen werden kann
     (ProjectController::planningPeriod). Erwartet $project, $isOverlay. --}}
@php
    $periodChart = app(\App\Services\ProjectPlanningCalculator::class)->periodChart($project);
    $timeNeed = app(\App\Services\ProjectPlanningCalculator::class)->timeNeed($project);
@endphp
    <div>
        @if ($periodChart)
            @include('projekte.partials.period-chart', ['chart' => $periodChart, 'hasOverrides' => (bool) ($timeNeed['has_overrides'] ?? false), 'canEditPeriod' => auth()->user()->can('workflow_step.due_date')])
        @else
            <p class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-500">{{ __('Für das Diagramm braucht das Projekt einen Workflow mit Arbeitsschritten sowie einen Projektstart und ein Projektende.') }}</p>
        @endif
    </div>
