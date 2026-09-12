{{--
    Ralf, 2026-09-12: vorher ein einzelnes Freitextfeld (Teil des großen
    Speichern-Formulars) - jetzt eine Liste einzelner Einträge mit Autor
    + Zeitpunkt (Vietto-Vorbild "bemerkungen", intTyp=1), sofort
    gespeichert wie die Projektverknüpfungen. Das alte Feld projects.remarks
    bleibt als Spalte bestehen (u. a. für die Bemerkungen-Filtersuche auf
    Altdaten), wird aber nicht mehr befüllt - neue Einträge landen in
    project_notes.
--}}
@include('projekte.partials.project-notes', [
    'project' => $project,
    'type' => \App\Models\ProjectNote::TYPE_REMARK,
    'boxKey' => 'remarks',
    'label' => __('Bemerkungen'),
    'notes' => $project->notes->where('type', \App\Models\ProjectNote::TYPE_REMARK),
    'editable' => true,
])
