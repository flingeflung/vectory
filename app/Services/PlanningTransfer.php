<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowStep;

/**
 * Planung von einem Projekt auf ein anderes übertragen (Ralf, 2026-10-08): Workflow, Dauern und Sperren der Schritte, Aufwandsprofil bzw.
 * eigene Planstunden, Projektbeteiligte und - nur auf ausdrücklichen Wunsch - die Termine der Schritte (Meilensteine). Dieselben Bausteine
 * nutzt die Projektkopie-Vorlage. Termine werden bewusst nicht aus den Dauern neu gerechnet; das bleibt "Termine berechnen" im Ziel.
 */
final class PlanningTransfer
{
    public const WORKFLOW = 'workflow';

    public const DURATIONS = 'durations';

    public const PLANNED_HOURS = 'planned_hours';

    public const PEOPLE = 'people';

    public const MILESTONES = 'milestones';

    /** @return array<string, string> Schlüssel => Bezeichnung */
    public static function parts(): array
    {
        return [
            self::WORKFLOW => __('Workflow'),
            self::DURATIONS => __('Dauern und Sperren der Schritte'),
            self::PLANNED_HOURS => __('Aufwandsprofil bzw. eigene Planstunden'),
            self::PEOPLE => __('Projektbeteiligte (mit ihren Stunden)'),
            self::MILESTONES => __('Termine und Meilensteine'),
        ];
    }

    /**
     * Welche Bereiche das Projekt überhaupt besitzt (für das Formular und die Prüfung vor dem Übertragen).
     *
     * @return array{workflow: bool, durations: bool, planned_hours: bool, people: bool, milestones: bool, workflow_id: int|null}
     */
    public static function available(Project $project): array
    {
        $rows = fn () => ProjectWorkflowStep::query()->where('project_id', $project->id);

        return [
            self::WORKFLOW => (bool) $project->workflow_id,
            self::DURATIONS => $rows()->where(fn ($query) => $query->whereNotNull('duration_days')->orWhereNotNull('duration_locked'))->exists(),
            self::PLANNED_HOURS => (bool) $project->project_template_id || $project->functionGroupHours()->exists(),
            self::PEOPLE => $project->projectPeople()->exists(),
            self::MILESTONES => (int) $project->schedule_model === 2
                ? $project->projectMilestones()->exists()
                : $rows()->whereNotNull('due_date')->exists(),
            'workflow_id' => $project->workflow_id ? (int) $project->workflow_id : null,
        ];
    }

    /**
     * @param  list<string>  $parts
     * @return list<array{part: string, status: string, note: string}> status: done | unchanged | skipped
     */
    public function transfer(Project $source, Project $target, array $parts, bool $keepExisting): array
    {
        $result = [];
        $labels = self::parts();

        foreach (array_keys($labels) as $part) {
            if (! in_array($part, $parts, true)) {
                continue;
            }
            [$status, $note] = match ($part) {
                self::WORKFLOW => $this->workflow($source, $target, $keepExisting),
                self::DURATIONS => $this->durations($source, $target, $keepExisting),
                self::PLANNED_HOURS => $this->plannedHours($source, $target, $keepExisting),
                self::PEOPLE => $this->people($source, $target, $keepExisting),
                self::MILESTONES => $this->milestones($source, $target, $keepExisting),
            };
            $result[] = ['part' => $labels[$part], 'status' => $status, 'note' => $note];
        }

        $done = collect($result)->where('status', 'done')->pluck('part')->all();
        if ($done !== []) {
            Activity::log($target, ActivityType::PlanningTransferred, __('Planung von Projekt :pn übernommen: :parts.', ['pn' => $source->source_pn, 'parts' => implode(', ', $done)]));
        }

        return $result;
    }

    /** @return array{0: string, 1: string} */
    private function workflow(Project $source, Project $target, bool $keepExisting): array
    {
        if (! $source->workflow_id) {
            return ['skipped', __('Das Quellprojekt hat keinen Workflow.')];
        }
        if ((int) $target->workflow_id === (int) $source->workflow_id) {
            return ['unchanged', __('Gleicher Workflow.')];
        }
        if ($keepExisting && $target->workflow_id) {
            return ['skipped', __('Das Ziel hat schon einen Workflow.')];
        }

        $target->update(['workflow_id' => $source->workflow_id]);
        WorkflowStep::query()->where('workflow_id', $target->workflow_id)->get()->each(
            fn (WorkflowStep $step) => ProjectWorkflowStep::query()->firstOrCreate(
                ['project_id' => $target->id, 'workflow_step_id' => $step->id],
                ['tenant_id' => $target->tenant_id, 'sort' => $step->sort]
            )
        );
        // wie bei jeder Workflow-Zuweisung: der Schritt "Planung" wird aktiv
        $planned = $target->projectWorkflowSteps()
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_id', $target->workflow_id)->where('lifecycle_status', 1))
            ->first();
        if ($planned) {
            $planned->update(['is_current' => true, 'started_at' => now()]);
            $target->update(['status' => 0]);
        }
        Activity::log($target, ActivityType::WorkflowAssigned, __('Workflow ":name" zugewiesen.', ['name' => $target->fresh()->workflow->name]));

        return ['done', __('Workflow zugewiesen.')];
    }

    /** Dauern, Sperren und Termine lassen sich nur zwischen Projekten mit demselben Workflow übertragen. */
    private function sameWorkflow(Project $source, Project $target): bool
    {
        return $source->workflow_id && (int) $source->workflow_id === (int) $target->fresh()->workflow_id;
    }

    /** @return array{0: string, 1: string} */
    private function durations(Project $source, Project $target, bool $keepExisting): array
    {
        if (! $this->sameWorkflow($source, $target)) {
            return ['skipped', __('Nur zwischen Projekten mit demselben Workflow möglich.')];
        }

        $sourceRows = ProjectWorkflowStep::query()->where('project_id', $source->id)->get()->keyBy('workflow_step_id');
        if ($sourceRows->every(fn (ProjectWorkflowStep $row) => $row->duration_days === null && $row->duration_locked === null)) {
            return ['skipped', __('Das Quellprojekt hat keine eigenen Dauern oder Sperren.')];
        }

        $changed = 0;
        foreach (WorkflowStep::query()->where('workflow_id', $target->workflow_id)->get() as $step) {
            $from = $sourceRows->get($step->id);
            if ($from === null) {
                continue;
            }
            $row = ProjectWorkflowStep::query()->firstOrCreate(
                ['project_id' => $target->id, 'workflow_step_id' => $step->id],
                ['tenant_id' => $target->tenant_id, 'sort' => $step->sort]
            );
            if ($keepExisting && ($row->duration_days !== null || $row->duration_locked !== null)) {
                continue;
            }
            $row->duration_days = $from->duration_days;
            $row->duration_locked = $from->duration_locked;
            if ($row->isDirty()) {
                $row->save();
                $changed++;
            }
        }

        return $changed > 0 ? ['done', __(':n Schritte angepasst.', ['n' => $changed])] : ['unchanged', __('Nichts zu ändern.')];
    }

    /** @return array{0: string, 1: string} */
    private function plannedHours(Project $source, Project $target, bool $keepExisting): array
    {
        if (! $source->project_template_id && ! $source->functionGroupHours()->exists()) {
            return ['skipped', __('Das Quellprojekt hat weder ein Aufwandsprofil noch eigene Planstunden.')];
        }
        $targetHasOwn = $target->functionGroupHours()->exists();
        if ($keepExisting && ($targetHasOwn || $target->project_template_id)) {
            return ['skipped', __('Das Ziel hat schon ein Aufwandsprofil oder eigene Planstunden.')];
        }

        $target->update(['project_template_id' => $source->project_template_id]);
        $syncData = $source->functionGroupHours->mapWithKeys(fn ($fg) => [
            $fg->id => ['tenant_id' => $target->tenant_id, 'planned_hours' => $fg->pivot->planned_hours],
        ]);
        $target->functionGroupHours()->sync($syncData);
        Activity::log($target, ActivityType::PlannedHoursChanged, $syncData->isNotEmpty()
            ? __('Eigene Planstunden von Projekt :pn übernommen.', ['pn' => $source->source_pn])
            : __('Planstunden folgen wieder dem Aufwandsprofil (von Projekt :pn übernommen).', ['pn' => $source->source_pn]));

        return ['done', $syncData->isNotEmpty() ? __('Eigene Planstunden übernommen.') : __('Aufwandsprofil übernommen.')];
    }

    /** @return array{0: string, 1: string} */
    private function people(Project $source, Project $target, bool $keepExisting): array
    {
        $key = fn (ProjectPerson $entry) => $entry->function_group_id.'-'.$entry->person_id;
        $sourceEntries = $source->projectPeople()->get();
        if ($sourceEntries->isEmpty()) {
            return ['skipped', __('Das Quellprojekt hat keine Projektbeteiligten.')];
        }
        $targetEntries = $target->projectPeople()->get()->keyBy($key);
        $changed = 0;

        foreach ($sourceEntries as $entry) {
            $existing = $targetEntries->get($key($entry));
            if ($existing === null) {
                ProjectPerson::query()->create([
                    'tenant_id' => $target->tenant_id, 'project_id' => $target->id, 'function_group_id' => $entry->function_group_id,
                    'person_id' => $entry->person_id, 'is_primary' => $entry->is_primary, 'planned_hours' => $entry->planned_hours,
                ]);
                $changed++;
            } elseif (! $keepExisting) {
                $existing->fill(['is_primary' => $entry->is_primary, 'planned_hours' => $entry->planned_hours]);
                if ($existing->isDirty()) {
                    $existing->save();
                    $changed++;
                }
            }
        }
        if (! $keepExisting) {
            $sourceKeys = $sourceEntries->map($key)->all();
            foreach ($targetEntries as $targetKey => $entry) {
                if (! in_array($targetKey, $sourceKeys, true)) {
                    $entry->delete();
                    $changed++;
                }
            }
        }

        return $changed > 0 ? ['done', __(':n Änderungen bei den Projektbeteiligten.', ['n' => $changed])] : ['unchanged', __('Nichts zu ändern.')];
    }

    /** @return array{0: string, 1: string} */
    private function milestones(Project $source, Project $target, bool $keepExisting): array
    {
        if (! $this->sameWorkflow($source, $target)) {
            return ['skipped', __('Nur zwischen Projekten mit demselben Workflow möglich.')];
        }

        // Neues Terminmodell: Meilenstein-Definitionen übertragen (die Termine rechnet das Ziel selbst); zwischen den Modellen nicht möglich
        if ((int) $source->schedule_model !== (int) $target->schedule_model) {
            return ['skipped', __('Nur zwischen Projekten im gleichen Terminmodell möglich.')];
        }
        if ((int) $source->schedule_model === 2) {
            return $this->projectMilestones($source, $target, $keepExisting);
        }

        $changed = 0;
        $sourceRows = ProjectWorkflowStep::query()->where('project_id', $source->id)->whereNotNull('due_date')->get();
        if ($sourceRows->isEmpty()) {
            return ['skipped', __('Das Quellprojekt hat keine Termine an den Schritten.')];
        }
        foreach ($sourceRows as $from) {
            $row = ProjectWorkflowStep::query()->firstOrCreate(
                ['project_id' => $target->id, 'workflow_step_id' => $from->workflow_step_id],
                ['tenant_id' => $target->tenant_id, 'sort' => $from->sort]
            );
            if ($keepExisting && $row->due_date !== null) {
                continue;
            }
            if ($row->due_date?->toDateString() !== $from->due_date->toDateString()) {
                $row->update(['due_date' => $from->due_date]);
                $changed++;
            }
        }

        return $changed > 0 ? ['done', __(':n Termine übernommen.', ['n' => $changed])] : ['unchanged', __('Nichts zu ändern.')];
    }

    /** @return array{0: string, 1: string} */
    private function projectMilestones(Project $source, Project $target, bool $keepExisting): array
    {
        $sourceRows = $source->projectMilestones()->get();
        if ($sourceRows->isEmpty()) {
            return ['skipped', __('Das Quellprojekt hat keine Meilensteine.')];
        }

        $changed = 0;
        $existing = $target->projectMilestones()->get()->keyBy('name');
        foreach ($sourceRows as $from) {
            $values = $from->only(['name', 'sort', 'anchor_type', 'anchor_workflow_step_id', 'offset_days', 'fixed_date', 'is_market_launch', 'check_direction', 'workflow_milestone_id']);
            $row = $existing->get($from->name);
            if ($row === null) {
                \App\Models\ProjectMilestone::query()->withoutGlobalScopes()->create([...$values, 'tenant_id' => $target->tenant_id, 'project_id' => $target->id]);
                $changed++;
            } elseif (! $keepExisting) {
                $row->update($values);
                $changed += $row->wasChanged() ? 1 : 0;
            }
        }

        return $changed > 0 ? ['done', __(':n Meilensteine übernommen.', ['n' => $changed])] : ['unchanged', __('Nichts zu ändern.')];
    }
}
