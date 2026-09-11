<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Task;
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
        if ($project->wasChanged('workflow_id')) {
            Task::syncWorkflowTasksForProject($project);
        }
    }
}
