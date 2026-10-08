<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Workflow;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use App\Services\ProjectScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stellt Workflows und Projekte ausgewählter Organisationen auf das neue Terminmodell um (Ralf, 2026-10-09;
 * docs/ablaufplan-konzept.md, Anhang A.6). Wiederholbar ohne Doppelung. Erfundene Testdaten: das Ergebnis muss
 * grundsätzlich passen, nicht tagesgenau.
 */
class MigrateScheduleModel extends Command
{
    protected $signature = 'schedule:migrate-v2 {--tenant=* : Organisations-IDs} {--dry-run : nur zählen, nichts speichern}';

    protected $description = 'Stellt Workflows und Projekte auf das neue Terminmodell (Phasen, Phasenenden, Meilensteine) um';

    /** @var array<string, int> */
    private array $counts = ['workflows' => 0, 'milestone_templates' => 0, 'milestones' => 0, 'steps_activated' => 0, 'projects' => 0, 'projects_without_start' => 0];

    public function handle(ProjectScheduler $scheduler): int
    {
        $tenantIds = array_map('intval', (array) $this->option('tenant'));
        if ($tenantIds === []) {
            $this->error('Bitte mindestens eine Organisation angeben, z. B. --tenant=7 --tenant=10 --tenant=17.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            foreach ($tenantIds as $tenantId) {
                $this->migrateTenant($tenantId, $scheduler);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->warn('Probelauf: nichts gespeichert.');
        } else {
            DB::commit();
        }

        foreach ($this->counts as $label => $count) {
            $this->line(str_pad($label, 26).$count);
        }

        return self::SUCCESS;
    }

    private function migrateTenant(int $tenantId, ProjectScheduler $scheduler): void
    {
        $workflows = Workflow::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->get();

        foreach ($workflows as $workflow) {
            $this->counts['workflows']++;
            $this->migrateMarketLaunchSteps($workflow);

            $steps = WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $workflow->id)->orderBy('sort')->get();

            // Übrige inaktive Schritte (z. B. Druck) sind jetzt normale Phasen
            foreach ($steps->where('is_active', false) as $step) {
                $step->forceFill(['is_active' => true])->saveQuietly();
                $this->counts['steps_activated']++;
            }

            // Termin-Kennzeichen und Start/Ende aus Namen und Status ableiten
            $firstStart = $steps->firstWhere('lifecycle_status', 1)?->id;
            $firstEnd = $steps->firstWhere('lifecycle_status', 3)?->id;
            foreach ($steps as $step) {
                $named = trim((string) $step->milestone_title) !== '';
                $step->forceFill([
                    'has_due_date' => $named || in_array((int) $step->lifecycle_status, [1, 3], true),
                    'is_start' => $step->id === $firstStart,
                    'is_end' => $step->id === $firstEnd,
                ])->saveQuietly();
            }

            DB::table('project_workflow_steps')->whereIn('workflow_step_id', $steps->pluck('id'))->update(['is_start' => null, 'is_end' => null]);
        }

        $workflowIds = $workflows->pluck('id');
        Project::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('workflow_id', $workflowIds)->each(function (Project $project) use ($scheduler) {
            $startDate = $project->start_date ?? $this->guessStartDate($project);
            if (! $startDate) {
                $this->counts['projects_without_start']++;
            }

            DB::table('projects')->where('id', $project->id)->update(['schedule_model' => 2, 'start_date' => $startDate?->toDateString()]);
            $project->refresh();
            $scheduler->recalculate($project);
            $this->counts['projects']++;
        });
    }

    /** Aus „Markteinführung“-Schritten werden Meilensteine am Workflow-Ende (+1 AT); das alte Datum entfällt. */
    private function migrateMarketLaunchSteps(Workflow $workflow): void
    {
        $launchSteps = WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $workflow->id)->where('is_market_launch', true)->get();

        foreach ($launchSteps as $step) {
            $name = trim((string) $step->milestone_title) !== '' ? $step->milestone_title : $step->title;

            $template = WorkflowMilestone::query()->withoutGlobalScopes()->firstOrCreate(
                ['workflow_id' => $workflow->id, 'name' => $name, 'is_market_launch' => true],
                [
                    'tenant_id' => $workflow->tenant_id,
                    'sort' => (int) $step->sort,
                    'anchor_type' => WorkflowMilestone::ANCHOR_WORKFLOW_END,
                    'offset_days' => 1,
                    'check_direction' => 'target',
                ]
            );
            if ($template->wasRecentlyCreated) {
                $this->counts['milestone_templates']++;
            }

            // Projekte dieses Workflows bekommen den Meilenstein aus der Vorlage, bevor der Schritt verschwindet
            Project::query()->withoutGlobalScopes()->where('workflow_id', $workflow->id)->each(function (Project $project) use ($template) {
                if (ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_milestone_id', $template->id)->exists()) {
                    return;
                }
                ProjectMilestone::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'workflow_milestone_id' => $template->id,
                    'name' => $template->name,
                    'sort' => $template->sort,
                    // Bezug aus der Vorlage (Workflow-Ende +1 AT): ein altes Datum passt nach der Neuberechnung meist nicht mehr
                    'anchor_type' => $template->anchor_type,
                    'offset_days' => $template->offset_days,
                    'is_market_launch' => true,
                    'check_direction' => $template->check_direction,
                ]);
                $this->counts['milestones']++;
            });

            $step->delete();
        }
    }

    private function guessStartDate(Project $project): ?\Carbon\CarbonImmutable
    {
        $rows = DB::table('project_workflow_steps as p')->join('workflow_steps as s', 's.id', '=', 'p.workflow_step_id')
            ->where('p.project_id', $project->id)->where('s.workflow_id', $project->workflow_id)->whereNotNull('p.due_date');
        $planned = (clone $rows)->where('s.lifecycle_status', 1)->value('p.due_date');
        $date = $planned ?? $rows->min('p.due_date') ?? $project->created_at?->toDateString();

        return $date ? \Carbon\CarbonImmutable::parse($date) : null;
    }
}
