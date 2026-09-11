{{--
    Ralf, 2026-09-11: Duplikat des Stammdaten-Bemerkungen-Felds, hier nur
    zusätzlich lesend in Ablaufdaten sichtbar (bearbeitet wird weiterhin
    ausschließlich in Stammdaten). Nicht editierbar.
--}}
<div>
    <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
    <div class="mt-0.5 whitespace-pre-wrap text-gray-700">{{ $project->remarks ?: '–' }}</div>
</div>
