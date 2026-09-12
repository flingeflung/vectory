{{--
    Ralf, 2026-09-12: zusätzlich zur Stammdaten-Bemerkungen-Box auch hier in
    Ablaufdaten sichtbar UND gleichwertig bedienbar (nicht nur lesend) -
    beide Boxen gleichen sich über ein window-Event live ab, siehe
    project-notes.blade.php.
--}}
@include('projekte.partials.project-notes', [
    'project' => $project,
    'type' => \App\Models\ProjectNote::TYPE_REMARK,
    'boxKey' => 'remarks-echo',
    'label' => __('Bemerkungen'),
    'notes' => $project->notes->where('type', \App\Models\ProjectNote::TYPE_REMARK),
    'editable' => true,
])
