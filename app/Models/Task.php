<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'person_id', 'function_group_id', 'project_workflow_step_id', 'graphic_order_id', 'source'])]
class Task extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['source' => TaskSource::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function functionGroup(): BelongsTo
    {
        return $this->belongsTo(FunctionGroup::class);
    }

    public function projectWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflowStep::class);
    }

    public function graphicOrder(): BelongsTo
    {
        return $this->belongsTo(GraphicOrder::class);
    }

    /**
     * Aus dem WFS abgeleitete Aufgaben für ein Projekt komplett neu aufbauen
     * (manuell zugewiesene Aufgaben bleiben unangetastet). WICHTIG: wird
     * NICHT von hier aus irgendwo verstreut aufgerufen, sondern hängt als
     * Eloquent-Observer an den tatsächlichen Eingabedaten (siehe
     * app/Observers/*) - Projekt-Personen ändern, Workflow zuweisen/
     * entfernen, künftig WFS weiterschalten. Genau das hat in Vietto
     * gefehlt: dort wird der Aufgaben-Rebuild an ~20 Stellen im Code einzeln
     * aufgerufen, mehrere davon vergessen es nachweislich (Bulk-Personen
     * entfernen, Bulk-Workflow-Wechsel) - Bugs, die lange unbemerkt bleiben.
     * Neuen Aufrufer für dieses Sync brauchst du nur, wenn ein Datenpfad
     * entsteht, der NICHT über ein Eloquent-Model läuft (z.B. ein Import,
     * der bewusst DB::table()->upsert() nutzt - siehe SyncTasksFromWorkflow
     * als expliziter Nachlauf für genau diesen Fall).
     *
     * Empfänger pro benötigter Funktionsgruppe des aktuellen WFS
     * (workflow_steps.functionGroups, die Schritt-Vorlage): die manuelle
     * Schritt-Override-Zuweisung (project_workflow_step_people) hat
     * Vorrang, sonst Fallback auf die normale projektweite Zuweisung
     * (project_people) - entspricht Viettos workflow_pers_cx2, das dort
     * beim Bearbeiten von projpersonen live nachgezogen wird. Hier bewusst
     * anders gelöst: statt bei jeder Personen-Änderung in alle Schritte zu
     * schreiben (Viettos Fehlerquelle), wird nur beim Sync für den jeweils
     * AKTUELLEN Schritt gelesen - weniger Zustand, der auseinanderlaufen kann.
     */
    public static function syncWorkflowTasksForProject(Project $project): void
    {
        self::query()
            ->where('project_id', $project->id)
            ->where('source', TaskSource::WorkflowStep)
            ->delete();

        $currentStep = ProjectWorkflowStep::query()
            ->where('project_id', $project->id)
            ->where('is_current', true)
            ->with(['workflowStep.functionGroups', 'people'])
            ->first();

        if (! $currentStep || ! $currentStep->workflowStep) {
            return;
        }

        foreach ($currentStep->workflowStep->functionGroups as $functionGroup) {
            foreach (self::assignedPeopleFor($currentStep, $functionGroup) as $person) {
                self::create([
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'person_id' => $person->id,
                    'function_group_id' => $functionGroup->id,
                    'project_workflow_step_id' => $currentStep->id,
                    'source' => TaskSource::WorkflowStep,
                ]);
            }
        }
    }

    /**
     * Aufgabe aus einem Illustrationsauftrag ableiten: sobald ein Illustrator
     * zugewiesen ist UND der Auftrag noch offen ist (status->is_open), wird
     * er automatisch Projektbeteiligter (function_group_id = Illustration)
     * und bekommt eine Aufgabe. Bei Statuswechsel/Umzuweisung wird die alte
     * Aufgabe gelöscht und ggf. neu angelegt - analog
     * syncWorkflowTasksForProject(), nur je Auftrag statt je Projekt/WFS.
     * Wird vom GraphicOrderObserver aufgerufen, sobald sich
     * illustrator_person_id oder graphic_order_status_id ändert.
     */
    public static function syncIllustrationTaskForOrder(GraphicOrder $graphicOrder): void
    {
        self::query()
            ->where('graphic_order_id', $graphicOrder->id)
            ->where('source', TaskSource::GraphicOrder)
            ->delete();

        if (! $graphicOrder->illustrator_person_id || ! $graphicOrder->status?->is_open) {
            return;
        }

        $functionGroup = FunctionGroup::query()
            ->where('tenant_id', $graphicOrder->tenant_id)
            ->where('legacy_id', 5)
            ->first();

        if (! $functionGroup) {
            return;
        }

        ProjectPerson::query()->firstOrCreate([
            'tenant_id' => $graphicOrder->tenant_id,
            'project_id' => $graphicOrder->project_id,
            'person_id' => $graphicOrder->illustrator_person_id,
            'function_group_id' => $functionGroup->id,
        ]);

        self::create([
            'tenant_id' => $graphicOrder->tenant_id,
            'project_id' => $graphicOrder->project_id,
            'person_id' => $graphicOrder->illustrator_person_id,
            'function_group_id' => $functionGroup->id,
            'graphic_order_id' => $graphicOrder->id,
            'source' => TaskSource::GraphicOrder,
        ]);
    }

    /**
     * Wer für eine Funktionsgruppe am gegebenen WFS zuständig ist - Override
     * (project_workflow_step_people) hat Vorrang, sonst Fallback auf die
     * projektweite Zuweisung (project_people). Eigener Helfer statt Logik
     * inline in syncWorkflowTasksForProject(), weil die Aufgaben-Seite
     * (Option "Alle WFS-Personen zeigen") dieselbe Auflösung für die
     * Anzeige braucht, nicht nur für den Rebuild.
     *
     * @return \Illuminate\Support\Collection<int, Person>
     */
    public static function assignedPeopleFor(ProjectWorkflowStep $step, FunctionGroup $functionGroup): \Illuminate\Support\Collection
    {
        $override = $step->people->where('function_group_id', $functionGroup->id);

        if ($override->isNotEmpty()) {
            return Person::query()->whereIn('id', $override->pluck('person_id'))->get();
        }

        return ProjectPerson::query()
            ->where('project_id', $step->project_id)
            ->where('function_group_id', $functionGroup->id)
            ->with('person')
            ->get()
            ->pluck('person')
            ->filter()
            ->values();
    }
}
