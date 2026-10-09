<?php

namespace App\Http\Controllers;

use App\Models\FunctionGroup;
use App\Models\MailTemplate;
use App\Models\MailTimer;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\WorkflowGroupWindow;
use App\Models\WorkflowStep;
use App\Services\MailTimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Erinnerungsmails (Mail-Timer) eines Projekts (Ralf, 2026-10-09): Liste im Overlay, Anlegen und Löschen. Betreff und Text kommen
 * ausschließlich aus den Mail-Vorlagen; Empfänger sind Funktionsgruppen des Projekts.
 */
class ProjectMailTimerController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        abort_unless($project->mayBeOpenedBy($request->user()), 403);

        $service = app(MailTimerService::class);
        $steps = WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->where('is_active', true)->orderBy('sort')->get(['id', 'title', 'lifecycle_status', 'milestone_title']);
        $timers = MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->with(['mailTemplate', 'step'])->orderByRaw('send_date IS NULL')->orderBy('send_date')->get();
        $milestones = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->orderBy('sort')->get(['id', 'name']);

        return view('projekte.partials.mail-timers-body', [
            'project' => $project,
            'timers' => $timers->map(fn (MailTimer $timer) => [
                'id' => $timer->id,
                'template' => $timer->mailTemplate?->name ?? '–',
                'step' => $timer->step?->title,
                'send_date' => $timer->send_date,
                'rule' => $service->ruleText($timer, $steps->pluck('title', 'id')->all(), $milestones->pluck('name', 'id')->all()),
                'groups' => $service->groupNames($timer),
                'recipients' => count($service->recipients($timer)),
                'only_if_in_step' => $timer->only_if_in_step,
                'sent_at' => $timer->sent_at,
                'skipped_at' => $timer->skipped_at,
                'error' => $timer->last_error,
            ]),
            'canEdit' => $request->user()->can('project.edit'),
            'steps' => $steps,
            'milestones' => $milestones,
            'templates' => MailTemplate::query()->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)->orderBy('name')->get(['id', 'name', 'description']),
            'groups' => FunctionGroup::query()->availableForTenant((int) $project->tenant_id, false)->orderBy('name')->get(['id', 'name']),
            'preselectStep' => $request->integer('step') ?: null,
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->mayBeOpenedBy($request->user()) && $request->user()->can('project.edit'), 403);

        $validated = $request->validate([
            'workflow_step_id' => ['required', 'integer'],
            'mail_template_id' => ['required', 'integer'],
            'reference' => ['required', 'string', 'regex:/\A(fixed|milestone:\d+|phase_end:\d+)\z/'],
            'fixed_date' => ['nullable', 'date'],
            'offset_days' => ['nullable', 'integer', 'between:-3650,3650'],
            'function_group_ids' => ['required', 'array', 'min:1'],
            'function_group_ids.*' => ['integer'],
            'only_if_in_step' => ['nullable', 'boolean'],
        ], [
            'function_group_ids.required' => __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.'),
            'function_group_ids.min' => __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.'),
        ]);

        $stepOk = WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->whereKey($validated['workflow_step_id'])->exists();
        abort_unless($stepOk, 422, __('Bitte wählen Sie einen Schritt dieses Workflows.'));
        $template = MailTemplate::query()->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)->find($validated['mail_template_id']);
        abort_unless($template, 422, __('Bitte wählen Sie eine Mail-Vorlage.'));
        $missing = app(\App\Services\MailTemplateRenderer::class)->unavailable($template, (int) $project->tenant_id, true);
        abort_if($missing !== [], 422, __('Diese Vorlage enthält Felder, die hier nicht zur Verfügung stehen: :fields.', ['fields' => implode(', ', $missing)]));

        [$type, $referenceId] = array_pad(explode(':', $validated['reference'], 2), 2, null);
        $attributes = ['reference_type' => $type, 'reference_milestone_id' => null, 'reference_step_id' => null, 'fixed_date' => null, 'offset_days' => (int) ($validated['offset_days'] ?? 0)];
        if ($type === MailTimer::REFERENCE_FIXED) {
            abort_if(empty($validated['fixed_date']), 422, __('Bitte geben Sie ein Datum an.'));
            $attributes['fixed_date'] = $validated['fixed_date'];
            $attributes['offset_days'] = 0;
        } elseif ($type === MailTimer::REFERENCE_MILESTONE) {
            abort_unless(ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->whereKey($referenceId)->exists(), 422, __('Bitte wählen Sie einen Meilenstein dieses Projekts.'));
            $attributes['reference_milestone_id'] = (int) $referenceId;
        } else {
            $named = WorkflowStep::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->whereKey($referenceId)->exists();
            abort_unless($named, 422, __('Bitte wählen Sie ein Phasenende dieses Workflows.'));
            $attributes['reference_step_id'] = (int) $referenceId;
        }

        $groupIds = FunctionGroup::query()->availableForTenant((int) $project->tenant_id, false)->whereIn('id', $validated['function_group_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_if($groupIds === [], 422, __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.'));

        $timer = MailTimer::query()->withoutGlobalScopes()->create([
            ...$attributes,
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'workflow_step_id' => (int) $validated['workflow_step_id'],
            'mail_template_id' => (int) $validated['mail_template_id'],
            'function_group_ids' => $groupIds,
            'only_if_in_step' => $request->boolean('only_if_in_step', true),
            'created_by_user_id' => $request->user()->id,
        ]);
        app(MailTimerService::class)->refreshSendDates($project);

        return response()->json(['id' => $timer->id]);
    }

    public function destroy(Request $request, Project $project, MailTimer $mailTimer): JsonResponse
    {
        abort_unless($project->mayBeOpenedBy($request->user()) && $request->user()->can('project.edit'), 403);
        abort_unless($mailTimer->project_id === $project->id, 404);

        $mailTimer->delete();

        return response()->json(['deleted' => true]);
    }
}
