<?php

namespace App\Observers;

use App\Models\ProjectWorkflowStep;
use App\Models\Task;

/**
 * Greift, sobald es eine "nächster Schritt"-Aktion gibt (aktuell noch nicht
 * gebaut - is_current wird bisher nur per Import per DB::upsert() gesetzt,
 * das umgeht Eloquent-Events bewusst und braucht darum SyncTasksFromWorkflow
 * als expliziten Nachlauf).
 */
class ProjectWorkflowStepObserver
{
    public function saved(ProjectWorkflowStep $projectWorkflowStep): void
    {
        if ($projectWorkflowStep->wasChanged('is_current') && $projectWorkflowStep->project) {
            Task::syncWorkflowTasksForProject($projectWorkflowStep->project);
        }

        $this->syncProjectStartEndDate($projectWorkflowStep);
    }

    /**
     * Vietto-Vorbild (incl_functions.php, projekte.dtgStartDate/dtgEndDate):
     * das Datum des als Start/Ende markierten Workflow-Schritts wird
     * einseitig ins Projekt übernommen, der Schritt ist die Quelle. Läuft
     * unabhängig davon, ob due_date, is_start/is_end selbst geändert wurde
     * oder eine Vorlagen-Änderung die effektiven Werte verschiebt (deshalb
     * hier zentral im Observer statt an jeder einzelnen Speicherstelle -
     * Terminberechnung, Inline-Datumsfeld, Workflow-Wechsel).
     */
    private function syncProjectStartEndDate(ProjectWorkflowStep $projectWorkflowStep): void
    {
        if (! $projectWorkflowStep->wasChanged(['due_date', 'is_start', 'is_end'])) {
            return;
        }

        $project = $projectWorkflowStep->project;
        if (! $project) {
            return;
        }

        if ($projectWorkflowStep->effectiveIsStart() && $project->start_date?->format('Y-m-d') !== $projectWorkflowStep->due_date?->format('Y-m-d')) {
            $project->update(['start_date' => $projectWorkflowStep->due_date]);
        }

        if ($projectWorkflowStep->effectiveIsEnd() && $project->end_date?->format('Y-m-d') !== $projectWorkflowStep->due_date?->format('Y-m-d')) {
            $project->update(['end_date' => $projectWorkflowStep->due_date]);
        }
    }
}
