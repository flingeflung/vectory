{{-- Bereich "Zeitraum" der Planung (Titel + Diagramm). Eigene Datei, damit er nach einer Terminänderung allein nachgeladen werden kann
     (ProjectController::planningPeriod). Erwartet $project, $isOverlay. --}}
@php
    $periodChart = app(\App\Services\ProjectPlanningCalculator::class)->periodChart($project);
    $timeNeed = app(\App\Services\ProjectPlanningCalculator::class)->timeNeed($project);
@endphp
    <button type="button" @click="toggleSection('zeitraum')" class="flex items-center gap-1.5 text-left" :aria-expanded="sections.zeitraum">
        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="sections.zeitraum ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        <h3 class="font-semibold text-gray-900">{{ __('Zeitraum') }}</h3>
        @if ($periodChart && $periodChart['period']['mode'] !== 'match')
            <span class="rounded px-1.5 py-0.5 text-[11px] font-normal {{ $periodChart['period']['mode'] === 'overflow' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                {{ $periodChart['period']['mode'] === 'overflow' ? __('Es fehlen :days AT', ['days' => $periodChart['period']['diff']]) : __('Puffer: :days AT', ['days' => $periodChart['period']['diff']]) }}
            </span>
        @endif
    </button>
    <div x-show="sections.zeitraum" class="mt-1">
        @if ($periodChart)
            @include('projekte.partials.period-chart', ['chart' => $periodChart, 'hasOverrides' => (bool) ($timeNeed['has_overrides'] ?? false), 'canEditPeriod' => auth()->user()->can('workflow_step.due_date')])
        @else
            <p class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-500">{{ __('Für das Diagramm braucht das Projekt einen Workflow mit Arbeitsschritten sowie einen Projektstart und ein Projektende.') }}</p>
        @endif
    </div>
