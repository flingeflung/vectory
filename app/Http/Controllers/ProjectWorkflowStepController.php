<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectWorkflowStepController extends Controller
{
    /**
     * Termin (due_date) einer einzelnen WFS-Instanz ändern - eigenständiges
     * Inline-Feld im Workflow-Schritte-Tab, unabhängig vom großen
     * Projekt-Detail-Formular speicherbar.
     */
    public function updateDueDate(Request $request, Project $project, ProjectWorkflowStep $projectWorkflowStep): JsonResponse
    {
        abort_unless($projectWorkflowStep->project_id === $project->id, 404);

        $validated = $request->validate(['due_date' => ['nullable', 'date']]);

        $projectWorkflowStep->update(['due_date' => $validated['due_date'] ?? null]);

        return response()->json(['due_date' => $projectWorkflowStep->due_date?->format('Y-m-d')]);
    }
}
