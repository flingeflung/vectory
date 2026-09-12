<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistPoint;
use App\Models\Project;
use App\Models\ProjectChecklist;
use App\Models\ProjectChecklistPoint;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Checklisten-Zuordnung + Abhaken am Projekt (Vietto-Vorbild: checklist_
 * projekt_cx/checklist_items, siehe Vietto-Analyse). Ralf: "die Zuordnung
 * bleibt manuell", eigener Reiter neben "Workflow" in den Projektdetails.
 */
class ProjectChecklistController extends Controller
{
    /**
     * "Checklisten auswählen" (Vietto: eb_activatechecklist) - speichert
     * IMMER den kompletten angehakten Zustand statt einzeln zu togglen
     * (gleiche Lektion wie beim WFS-Personen-Picker: sonst könnte ein
     * einzelner Klick versehentlich eine andere, bereits aktivierte
     * Checkliste unbeabsichtigt mit entfernen, wenn die Liste zwischen
     * Laden und Speichern woanders geändert wurde). Ent-Zuordnen löscht
     * NUR die Zuordnung, der Abhak-Stand (project_checklist_points) bleibt
     * bestehen und taucht bei erneutem Aktivieren wieder auf - bewusst wie
     * Vietto (kein Datenverlust bei versehentlichem Entfernen).
     */
    public function update(Request $request, Project $project): RedirectResponse|Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $checklistIds = collect($request->array('checklist_ids'))->map(fn ($id) => (int) $id);
        $validIds = Checklist::query()->where('tenant_id', $project->tenant_id)->whereIn('id', $checklistIds)->pluck('id');

        $currentIds = $project->projectChecklists->pluck('checklist_id');

        foreach ($validIds->diff($currentIds) as $checklistId) {
            ProjectChecklist::query()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'checklist_id' => $checklistId,
                'activated_by_person_id' => $request->user()->person_id,
                'activated_at' => now(),
            ]);
        }

        $project->projectChecklists()->whereIn('checklist_id', $currentIds->diff($validIds))->get()->each->delete();

        if ($this->isOverlayRequest($request)) {
            return response()->view('projekte.partials.checklisten', [
                'project' => $project->fresh(['projectChecklists.checklist.sections.points', 'projectChecklists.activatedBy', 'projectChecklistPoints.doneBy']),
                'allChecklists' => $this->allChecklistsFor($project),
            ]);
        }

        return redirect()->route('projekte.show', $project);
    }

    /**
     * Ein einzelner Punkt abhaken/zurücknehmen (Vietto: ajax_checklist_set_
     * listitem.php) - sofortiges Speichern pro Klick, kein Sammel-
     * Speichern-Button (reine An/Aus-Interaktion, wie eine Filter-Checkbox).
     * Speichert wie im Vorbild wer/wann zuletzt geändert hat, aber KEINE
     * Historie (Uncheck+Recheck durch eine andere Person überschreibt den
     * vorherigen Bearbeiter - bewusst wie Vietto übernommen).
     */
    public function togglePoint(Request $request, Project $project, ChecklistPoint $point): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($point->section->checklist->tenant_id === $project->tenant_id, 404);

        $done = $request->boolean('done');

        ProjectChecklistPoint::query()->updateOrCreate(
            ['project_id' => $project->id, 'checklist_point_id' => $point->id],
            [
                'tenant_id' => $project->tenant_id,
                'done' => $done,
                'done_by_person_id' => $request->user()->person_id,
                'done_at' => now(),
            ]
        );

        return response()->view('projekte.partials.checklisten', [
            'project' => $project->fresh(['projectChecklists.checklist.sections.points', 'projectChecklists.activatedBy', 'projectChecklistPoints.doneBy']),
            'allChecklists' => $this->allChecklistsFor($project),
        ]);
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    private function allChecklistsFor(Project $project): \Illuminate\Support\Collection
    {
        return Checklist::query()->where('tenant_id', $project->tenant_id)
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $project->projectChecklists->pluck('checklist_id')))
            ->orderBy('sort')->get();
    }
}
