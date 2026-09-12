{{--
    Ralf, 2026-09-11/12: Duplikat des Stammdaten-Bemerkungen-Felds, hier nur
    zusätzlich lesend in Ablaufdaten sichtbar (bearbeitet wird weiterhin
    ausschließlich in Stammdaten) - Momentaufnahme beim Laden des Overlays,
    kein Live-Abgleich mit der editierbaren Box.
--}}
@include('projekte.partials.project-notes', [
    'project' => $project,
    'type' => \App\Models\ProjectNote::TYPE_REMARK,
    'label' => __('Bemerkungen'),
    'notes' => $project->notes->where('type', \App\Models\ProjectNote::TYPE_REMARK),
    'editable' => false,
])
