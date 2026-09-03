<?php

namespace App\Console\Commands;

use App\Models\FunctionGroup;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Schritt-Vorlagen aus Vietto (workflowsteps2, rein lesend) für
 * die bereits importierten (neuen) Workflows.
 */
class ImportWorkflowStepsFromVietto extends Command
{
    protected $signature = 'workflow-steps:import-from-vietto';

    protected $description = 'Workflow-Schritte aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $workflowIds = Workflow::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $groupIds = FunctionGroup::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $rows = DB::connection('vietto')->table('workflowsteps2')->orderBy('valWorkflowID')->orderBy('intSort')->get();

        $count = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $workflowId = $workflowIds->get($row->valWorkflowID);

            if (! $workflowId) {
                $skipped++;

                continue;
            }

            $step = WorkflowStep::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                [
                    'workflow_id' => $workflowId,
                    'title' => $row->strWFSTitel,
                    'short_title' => $row->strWFSTitelkurz ?: null,
                    'milestone_title' => $row->strMSTitel ?: null,
                    'sort' => $row->intSort,
                    'duration_days' => $row->intDauer,
                    'is_active' => (bool) $row->blnIsActiveWFS,
                    'is_start' => (bool) $row->blnIsStart,
                    'is_end' => (bool) $row->blnIsEnde,
                    'lifecycle_status' => $row->intStatus ?: 2,
                    'is_market_launch' => (bool) $row->blnIsMarkteinfuehrung,
                    'has_due_date' => (bool) $row->blnTermin,
                    'send_email' => (bool) $row->blnEmailsenden,
                    'duration_editable' => (bool) $row->blnDauerAenderbar,
                    'show_in_translation' => (bool) $row->blnShowinTransl,
                    'js_function' => $row->strJSFunction ?: null,
                    'js_function_param' => $row->strJSFunctionParam ?: null,
                    'description' => $row->txtPStepDescr ?: null,
                    'email_text' => $row->txtEmailtext ?: null,
                    'msg_task_function_group_ids' => $row->strFktGrpIDMsgTsk ?: null,
                ]
            );

            $stepGroupIds = collect(explode(';', (string) $row->strFktGrpID))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->map(fn ($legacyGroupId) => $groupIds->get($legacyGroupId))
                ->filter()
                ->all();

            $step->functionGroups()->sync(collect($stepGroupIds)->mapWithKeys(fn ($id) => [
                $id => ['tenant_id' => $tenant->id],
            ])->all());

            $count++;
        }

        $this->info("{$count} Workflow-Schritte importiert".($skipped ? ", {$skipped} übersprungen." : '.'));

        return self::SUCCESS;
    }
}
