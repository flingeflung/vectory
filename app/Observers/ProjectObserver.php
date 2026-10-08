<?php

namespace App\Observers;

use App\Models\CriticalProjectFinding;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectScheduler;
use App\Support\StammId;
use Illuminate\Support\Facades\Auth;

class ProjectObserver
{
    /**
     * Footer "angelegt durch"/"zuletzt geändert" (Ralf, 2026-09-11, nach
     * Vietto-Vorbild: dort aktualisiert ajax_writeaend.php ModUserID/
     * dtgModDate generisch bei JEDER Feldänderung, unabhängig davon, über
     * welchen Screen/Controller gespeichert wurde). Als Model-Hook statt
     * pro Controller gesetzt, damit auch Seiteneffekte wie der WFS-Start/
     * Ende-Sync (ProjectWorkflowStepObserver) korrekt dem gerade
     * handelnden Nutzer zugeschrieben werden, nicht nur direkte
     * Formular-Speicherungen. Kein Auth-Kontext (z.B. Konsolen-Import) ->
     * bleibt unangetastet, kein falscher Nutzer wird zugeschrieben.
     */
    public function creating(Project $project): void
    {
        // Stamm-ID (Ralf, 2026-09-20): jedes neue Dokument startet eine
        // eigene Versionskette. Wer aufversioniert (Kopieren), gibt die ID
        // des Vorgängers ausdrücklich mit - dann bleibt sie unangetastet.
        if (! $project->stamm_id) {
            $project->stamm_id = StammId::generate();
            $project->stamm_position = 1;
        }

        if (Auth::check()) {
            $project->created_by_user_id = Auth::id();
        }
    }

    public function updating(Project $project): void
    {
        if (Auth::check() && $project->isDirty()) {
            $project->updated_by_user_id = Auth::id();
        }
    }

    public function updated(Project $project): void
    {
        if ($project->wasChanged('status') && in_array((int) $project->status, [2, 3], true)) {
            CriticalProjectFinding::query()->where('project_id', $project->id)->delete();
        }

        if ($project->wasChanged('workflow_id')) {
            Task::syncWorkflowTasksForProject($project);
        }

        // Neues Terminmodell (Ralf, 2026-10-09): Start oder Workflow geändert -> Termine neu rechnen
        if ((int) $project->schedule_model === 2 && $project->wasChanged(['start_date', 'workflow_id', 'schedule_model'])) {
            app(ProjectScheduler::class)->recalculate($project);
        }
    }

    public function created(Project $project): void
    {
        if ((int) $project->schedule_model === 2) {
            app(ProjectScheduler::class)->recalculate($project);
        }
    }
}
