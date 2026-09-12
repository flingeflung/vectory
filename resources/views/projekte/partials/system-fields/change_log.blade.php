{{--
    Ralf, 2026-09-12 (Vietto-Vorbild "bemerkungen", intTyp=2; hieß in
    Vietto "Änderungen zur Vorversion"/ÄzV, Ralf wollte dafür einen
    besseren Namen): im Unterschied zu "Bemerkungen" (nur in Vectory
    sichtbar) können diese Einträge auch nach außen gehen (Hotline, QM,
    Vertrieb) - wohin genau, ist kundenspezifisch und bewusst noch nicht
    gebaut (Ralf, 2026-09-12: "erst mal bauen, mehr nicht"). Kein
    Freigabeprozess pro Eintrag.
--}}
@include('projekte.partials.project-notes', [
    'project' => $project,
    'type' => \App\Models\ProjectNote::TYPE_CHANGE,
    'boxKey' => 'change-log',
    'label' => __('Änderungsprotokoll'),
    'notes' => $project->notes->where('type', \App\Models\ProjectNote::TYPE_CHANGE),
    'editable' => true,
])
