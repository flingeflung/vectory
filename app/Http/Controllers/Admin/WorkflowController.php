<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\UserPreference;
use App\Models\Workflow;
use App\Models\WorkflowGroupWindow;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung von Workflows und ihren Schritten (Vietto: workflows2/
 * workflowsteps2 - bewusst nur das "neue" System ab 2018, siehe
 * workflows-Migration). Zwei Besonderheiten gegenüber Projektkategorien:
 *
 * 1. Projekt-Zuweisung bleibt ein Verweis, keine Kopie (project_workflow_
 *    steps referenziert weiterhin live workflow_steps für Titel/Text/
 *    Funktionsgruppe) - siehe Ralf-Diskussion, Redaktionssysteme-Vergleich.
 * 2. Ein Workflow ist inhaltlich EINGEFROREN, sobald er bewusst über
 *    publish() veröffentlicht wurde (Workflow::isPublished(), explizites
 *    published_at-Flag) - keine Bearbeitung mehr, nur noch "Neue Version
 *    erstellen" (Kopie + supersededBy) oder Aktiv/Inaktiv.
 *
 *    WICHTIG, Ralfs konkreter Bug-Report: das Sperren war ZUERST an die
 *    reine Nutzung durch ein Projekt gekoppelt (isPublished() = "irgendein
 *    Projekt hat schon zugewiesen") - das sperrte einen Workflow aber
 *    schon beim ersten TEST-Zuweisen, genau während man ihn noch
 *    ausprobiert/korrigiert. Jetzt bleibt ein Entwurf beliebig oft
 *    testweise einem Projekt zuweisbar UND frei bearbeitbar (auch die
 *    Test-Zuweisung bleibt live verknüpft, siehe Punkt 1 - Änderungen
 *    wirken sich sofort auf das Test-Projekt aus, genau das will man beim
 *    Testen), bis man ihn bewusst veröffentlicht.
 */
class WorkflowController extends Controller
{
    public function index(Request $request): View|Response
    {
        $data = $this->buildIndexData($request, $request->filled('workflow') ? (int) $request->query('workflow') : null);

        // Gleiches Muster wie Projektkategorien: Umbenennen/Schritt-Speichern
        // laufen per fetch() + reloadManageListPreservingEdits() statt
        // vollem Seiten-Reload, der Reload holt sich hierüber nur das
        // Inhalts-Partial (X-Overlay-Header).
        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.workflows.partials.content', $data);
        }

        return view('admin.workflows.index', $data);
    }

    /**
     * @return array{workflows: Collection, selectedWorkflow: ?Workflow, steps: Collection, isPublished: bool, functionGroups: Collection, specialButtons: array, lifecycleColors: array, lifecycleStatusLabels: array, missingLifecycleStatuses: Collection}
     */
    private function buildIndexData(Request $request, ?int $workflowId): array
    {
        $tenantId = CurrentTenant::id();

        $workflows = Workflow::query()->where('tenant_id', $tenantId)->orderBy('sort')->withCount('steps')->with('supersededBy')->get();

        $selectedWorkflow = $workflowId ? $workflows->firstWhere('id', $workflowId) : null;

        $steps = collect();
        $isPublished = false;
        $missingLifecycleStatuses = collect();
        if ($selectedWorkflow) {
            $steps = WorkflowStep::query()->where('workflow_id', $selectedWorkflow->id)->orderBy('sort')->with('functionGroups')->get();
            $isPublished = $selectedWorkflow->isPublished();
            // Ralf, 2026-09-12 ("WFS: alle 4 Lifecycle-Status abgedeckt?"):
            // ohne mindestens einen Schritt je lifecycle_status kann ein
            // Projekt auf diesem Workflow den betroffenen Status nie
            // automatisch erreichen (siehe WorkflowStep::
            // LIFECYCLE_STATUS_LABELS) - und "Projekt kopieren" findet
            // ohne lifecycle_status=1 keinen Startschritt.
            $missingLifecycleStatuses = collect(array_keys(WorkflowStep::LIFECYCLE_STATUS_LABELS))
                ->diff($steps->pluck('lifecycle_status')->unique())
                ->values();
        }

        // Alphabetisch statt nach sort - Funktionsgruppen werden überall
        // alphabetisch gelistet (siehe FunctionGroupController), sort ist
        // dort bewusst kein Sortierkriterium (kein D&D für diese Liste).
        $functionGroups = FunctionGroup::query()->availableForTenant($tenantId, false)->orderBy('name')->get(['id', 'name']);

        // "Zu anderem Kunden kopieren" bewusst nur für Zentral-Admin/
        // Super-Admin (Ralf, 2026-09-18: "das darf ja wieder nur vom
        // H-Admin aus möglich sein", beim Projektschablonen-Import-Feature
        // entdeckte Mandanten-Grenzen-Lücke, hier nachgezogen - vorher
        // konnte JEDER Admin, auch der eines einzelnen Kundekunden-
        // Mandanten, in JEDEN anderen Mandanten hineinkopieren).

        // Einsatzplan: nur "In Bearbeitung"-Schritte haben einen Zeitanteil; Zeilen = Funktionsgruppen, die dort zuständig sind.
        $workSteps = $steps->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->values();
        $windows = $selectedWorkflow
            ? WorkflowGroupWindow::query()->where('workflow_id', $selectedWorkflow->id)->get()->keyBy('function_group_id')
            : collect();
        $stepIndex = $workSteps->pluck('id')->flip();
        $deploymentRows = $workSteps->flatMap->functionGroups->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ->map(function ($group) use ($windows, $stepIndex, $workSteps) {
                $window = $windows->get($group->id);
                $from = $window?->from_step_id !== null && $stepIndex->has($window->from_step_id) ? $stepIndex[$window->from_step_id] + 1 : 1;
                $to = $window?->to_step_id !== null && $stepIndex->has($window->to_step_id) ? $stepIndex[$window->to_step_id] + 1 : $workSteps->count();

                return ['id' => $group->id, 'name' => $group->name, 'from' => min($from, $to), 'to' => max($from, $to)];
            });
        $viewState = UserPreference::configFor((int) auth()->id(), UserPreference::WORKFLOW_VIEW) + ['einsatzplan' => true, 'schritte' => true, 'meilensteine' => true, 'erinnerungen' => true];
        $milestones = $selectedWorkflow
            ? WorkflowMilestone::query()->where('workflow_id', $selectedWorkflow->id)->orderBy('sort')->orderBy('id')->get()
            : collect();

        return [
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
            'workSteps' => $workSteps,
            'deploymentRows' => $deploymentRows,
            'milestones' => $milestones,
            'mailTimers' => $selectedWorkflow ? \App\Models\WorkflowMailTimer::query()->where('workflow_id', $selectedWorkflow->id)->orderBy('id')->get() : collect(),
            'mailTemplates' => \App\Models\MailTemplate::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'description']),
            'viewState' => $viewState,
            'steps' => $steps,
            'isPublished' => $isPublished,
            'functionGroups' => $functionGroups,
            'specialButtons' => WorkflowStep::SPECIAL_BUTTONS,
            'lifecycleColors' => WorkflowStep::LIFECYCLE_COLORS,
            'lifecycleStatusLabels' => WorkflowStep::LIFECYCLE_STATUS_LABELS,
            'missingLifecycleStatuses' => $missingLifecycleStatuses,
        ];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    /**
     * Einsatzplan speichern (Ralf, 2026-10-04): je Funktionsgruppe von/bis-Schritt. Die ganze Breite ist der
     * Standard und wird nicht gespeichert (Eintrag gelöscht). Eingefroren wie der Rest des Workflows, sobald er
     * veröffentlicht ist.
     */
    public function saveDeploymentPlan(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, __('Dieser Workflow ist veröffentlicht - der Einsatzplan lässt sich nur über eine neue Version ändern.'));

        $workSteps = WorkflowStep::query()->where('workflow_id', $workflow->id)
            ->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->orderBy('sort')->with('functionGroups')->get();
        $position = $workSteps->pluck('id')->flip();
        $allowedGroups = $workSteps->flatMap->functionGroups->pluck('id')->unique();
        $input = (array) $request->input('windows', []);

        DB::transaction(function () use ($workflow, $workSteps, $position, $allowedGroups, $input) {
            WorkflowGroupWindow::query()->where('workflow_id', $workflow->id)->whereNotIn('function_group_id', $allowedGroups)->delete();

            foreach ($allowedGroups as $groupId) {
                $from = (int) data_get($input, "$groupId.from");
                $to = (int) data_get($input, "$groupId.to");
                if (! $position->has($from) || ! $position->has($to) || $position[$from] > $position[$to]) {
                    continue; // unvollständige oder ungültige Zeile: bisherigen Stand beibehalten
                }

                $isFullWidth = $from === $workSteps->first()->id && $to === $workSteps->last()->id;
                if ($isFullWidth) {
                    WorkflowGroupWindow::query()->where('workflow_id', $workflow->id)->where('function_group_id', $groupId)->delete();

                    continue;
                }
                WorkflowGroupWindow::query()->updateOrCreate(
                    ['workflow_id' => $workflow->id, 'function_group_id' => $groupId],
                    ['tenant_id' => $workflow->tenant_id, 'from_step_id' => $from, 'to_step_id' => $to],
                );
            }
        });

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /** Meilenstein in der Workflow-Vorlage anlegen (nur solange der Workflow nicht veröffentlicht ist). */
    public function milestoneStore(Request $request, Workflow $workflow): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        $data = $this->validatedMilestone($request, $workflow);
        $sort = (int) WorkflowMilestone::query()->where('workflow_id', $workflow->id)->max('sort') + 1;
        WorkflowMilestone::query()->create([...$data, 'tenant_id' => $workflow->tenant_id, 'workflow_id' => $workflow->id, 'sort' => $sort]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function milestoneUpdate(Request $request, Workflow $workflow, WorkflowMilestone $milestone): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        abort_unless($milestone->workflow_id === $workflow->id, 404);
        $milestone->update($this->validatedMilestone($request, $workflow));

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function milestoneDestroy(Workflow $workflow, WorkflowMilestone $milestone): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        abort_unless($milestone->workflow_id === $workflow->id, 404);
        $milestone->delete();

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /** Standard-Erinnerung (Mail-Timer-Vorlage) am Workflow-Schritt anlegen; nur solange der Workflow nicht veröffentlicht ist. */
    public function mailTimerStore(Request $request, Workflow $workflow): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        \App\Models\WorkflowMailTimer::query()->create([...$this->validatedMailTimer($request, $workflow), 'tenant_id' => $workflow->tenant_id, 'workflow_id' => $workflow->id]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function mailTimerUpdate(Request $request, Workflow $workflow, \App\Models\WorkflowMailTimer $mailTimer): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        abort_unless($mailTimer->workflow_id === $workflow->id, 404);
        $mailTimer->update($this->validatedMailTimer($request, $workflow));

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function mailTimerDestroy(Workflow $workflow, \App\Models\WorkflowMailTimer $mailTimer): RedirectResponse
    {
        $this->abortUnlessEditableMilestones($workflow);
        abort_unless($mailTimer->workflow_id === $workflow->id, 404);
        $mailTimer->delete();

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /** @return array<string, mixed> */
    private function validatedMailTimer(Request $request, Workflow $workflow): array
    {
        $validated = $request->validate([
            'workflow_step_id' => ['required', 'integer'],
            'mail_template_id' => ['required', 'integer'],
            'reference' => ['required', 'string', 'regex:/\A(milestone|phase_end):\d+\z/'],
            'offset_days' => ['nullable', 'integer', 'between:-3650,3650'],
            'function_group_ids' => ['required', 'array', 'min:1'],
            'function_group_ids.*' => ['integer'],
            'only_if_in_step' => ['nullable', 'boolean'],
        ], ['function_group_ids.required' => __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.'), 'function_group_ids.min' => __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.')]);

        abort_unless(WorkflowStep::query()->where('workflow_id', $workflow->id)->whereKey($validated['workflow_step_id'])->exists(), 422, __('Bitte wählen Sie einen Schritt dieses Workflows.'));
        $template = \App\Models\MailTemplate::query()->where('tenant_id', $workflow->tenant_id)->find($validated['mail_template_id']);
        abort_unless($template, 422, __('Bitte wählen Sie eine Mail-Vorlage.'));
        $missing = app(\App\Services\MailTemplateRenderer::class)->unavailable($template, (int) $workflow->tenant_id, true);
        abort_if($missing !== [], 422, __('Diese Vorlage enthält Felder, die hier nicht zur Verfügung stehen: :fields.', ['fields' => implode(', ', $missing)]));

        [$type, $referenceId] = explode(':', $validated['reference'], 2);
        if ($type === 'milestone') {
            abort_unless(WorkflowMilestone::query()->where('workflow_id', $workflow->id)->whereKey($referenceId)->exists(), 422, __('Bitte wählen Sie einen Meilenstein dieses Workflows.'));
        } else {
            abort_unless(WorkflowStep::query()->where('workflow_id', $workflow->id)->whereKey($referenceId)->exists(), 422, __('Bitte wählen Sie ein Phasenende dieses Workflows.'));
        }
        $groupIds = FunctionGroup::query()->availableForTenant((int) $workflow->tenant_id, false)->whereIn('id', $validated['function_group_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_if($groupIds === [], 422, __('Bitte wählen Sie mindestens eine Funktionsgruppe als Empfänger.'));

        return [
            'workflow_step_id' => (int) $validated['workflow_step_id'],
            'mail_template_id' => (int) $validated['mail_template_id'],
            'reference_type' => $type,
            'reference_milestone_id' => $type === 'milestone' ? (int) $referenceId : null,
            'reference_step_id' => $type === 'phase_end' ? (int) $referenceId : null,
            'offset_days' => (int) ($validated['offset_days'] ?? 0),
            'only_if_in_step' => $request->boolean('only_if_in_step', true),
            'function_group_ids' => $groupIds,
        ];
    }

    private function abortUnlessEditableMilestones(Workflow $workflow): void
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, __('Dieser Workflow ist veröffentlicht - die Meilensteine lassen sich nur über eine neue Version ändern.'));
    }

    /** @return array<string, mixed> */
    private function validatedMilestone(Request $request, Workflow $workflow): array
    {
        $types = [WorkflowMilestone::ANCHOR_WORKFLOW_START, WorkflowMilestone::ANCHOR_WORKFLOW_END, WorkflowMilestone::ANCHOR_STEP_START, WorkflowMilestone::ANCHOR_STEP_END];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'anchor_type' => ['required', Rule::in($types)],
            'anchor_workflow_step_id' => ['nullable', 'integer'],
            'offset_days' => ['nullable', 'integer', 'between:-3650,3650'],
            'check_direction' => ['nullable', Rule::in(['target', 'prerequisite'])],
        ], ['name.required' => __('Bitte geben Sie dem Meilenstein einen Namen.')]);

        $isStep = in_array($validated['anchor_type'], [WorkflowMilestone::ANCHOR_STEP_START, WorkflowMilestone::ANCHOR_STEP_END], true);
        $stepId = $isStep ? (int) ($validated['anchor_workflow_step_id'] ?? 0) : null;
        if ($isStep) {
            $allowed = WorkflowStep::query()->where('workflow_id', $workflow->id)
                ->where('lifecycle_status', WorkflowGroupWindow::WORK_LIFECYCLE_STATUS)->whereKey($stepId)->exists();
            abort_unless($allowed, 422, __('Bitte wählen Sie eine Phase dieses Workflows.'));
        }

        return [
            'name' => trim($validated['name']),
            'anchor_type' => $validated['anchor_type'],
            'anchor_workflow_step_id' => $stepId,
            'offset_days' => (int) ($validated['offset_days'] ?? 0),
            'check_direction' => ($validated['check_direction'] ?? null) ?: null,
        ];
    }

    /** Auf-/Zuklappzustand der Bereiche (Einsatzplan, Meilensteine, Schritte) je Benutzer merken. */
    public function saveViewState(Request $request): Response
    {
        $data = $request->validate([
            'section' => ['required', Rule::in(['einsatzplan', 'schritte', 'meilensteine', 'erinnerungen'])],
            'open' => ['required', 'boolean'],
        ]);
        $userId = $request->user()->id;
        $state = UserPreference::configFor($userId, UserPreference::WORKFLOW_VIEW);
        $state[$data['section']] = (bool) $data['open'];
        UserPreference::persist($userId, UserPreference::WORKFLOW_VIEW, $state);

        return response()->noContent();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('workflows'))->values()->each(function (string $id, int $index) use ($tenantId) {
            Workflow::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.workflows');
    }

    public function stepReorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $workflowId = $request->integer('workflow_id');

        $workflow = Workflow::query()->where('tenant_id', $tenantId)->findOrFail($workflowId);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        collect($request->array('steps'))->values()->each(function (string $id, int $index) use ($tenantId) {
            WorkflowStep::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.workflows', ['workflow' => $workflowId]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validatedWorkflow($request);

        $nextSort = 1 + (int) Workflow::query()->where('tenant_id', $tenantId)->max('sort');
        $workflow = Workflow::query()->create([...$validated, 'tenant_id' => $tenantId, 'active' => true, 'sort' => $nextSort]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);

        // Ein "nur Aktiv/Inaktiv umschalten"-Aufruf (kein anderes Feld im
        // Request) bleibt auch bei publizierten Workflows erlaubt - das
        // ändert nichts am Inhalt, nur an der Sichtbarkeit für neue
        // Projekte. Inhaltliche Änderungen (Name etc.) sind ab Publikation
        // gesperrt, siehe Klassen-Docblock.
        if ($request->has('name')) {
            abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');
            $workflow->update($this->validatedWorkflow($request));
        }

        $workflow->update(['active' => $request->boolean('active')]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /**
     * Löschen nur für Entwürfe (noch nie einem Projekt zugewiesen) - dann
     * gefahrlos, da nirgends referenziert. Schritte + Funktionsgruppen-
     * Zuordnung hängen per cascadeOnDelete direkt dran, kein manuelles
     * Aufräumen nötig.
     */
    public function destroy(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr gelöscht werden.');

        $workflow->delete();

        return redirect()->route('admin.workflows')->with('status', 'workflows-updated');
    }

    /**
     * Bewusster "Freigabe"-Schritt (Ralfs Redaktionsprinzip: nicht
     * automatisch beim ersten Benutzen sperren, siehe Klassen-Docblock) -
     * ab hier ist der Workflow eingefroren, nur noch "Neue Version
     * erstellen" oder Aktiv/Inaktiv möglich.
     */
    public function publish(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht.');

        $workflow->update(['published_at' => now()]);

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /**
     * "Neue Version erstellen": Kopie des Workflows + aller Schritte + der
     * Funktionsgruppen-Zuordnung je Schritt unter neuem Namen. Alte Version
     * bleibt unverändert (bestehende Projekte zeigen weiter darauf),
     * bekommt aber superseded_by_id gesetzt und wird deaktiviert, damit sie
     * für NEUE Projekte nicht mehr wählbar ist (ProjectController::
     * availableWorkflows() filtert schon auf active=true OR aktuell
     * zugewiesen).
     */
    public function newVersion(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);

        $newWorkflow = DB::transaction(function () use ($workflow) {
            $nextSort = 1 + (int) Workflow::query()->where('tenant_id', $workflow->tenant_id)->max('sort');
            $newWorkflow = Workflow::query()->create([
                'tenant_id' => $workflow->tenant_id,
                'short_name' => $workflow->short_name,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'active' => true,
                'sort' => $nextSort,
            ]);

            $stepIdMap = [];
            $workflow->steps->each(function (WorkflowStep $step) use ($newWorkflow, &$stepIdMap) {
                $newStep = WorkflowStep::query()->create([
                    'tenant_id' => $newWorkflow->tenant_id,
                    'workflow_id' => $newWorkflow->id,
                    'title' => $step->title,
                    'short_title' => $step->short_title,
                    'milestone_title' => $step->milestone_title,
                    'sort' => $step->sort,
                    'duration_days' => $step->duration_days,
                    'duration_locked' => $step->duration_locked,
                    'is_active' => $step->is_active,
                    'is_start' => $step->is_start,
                    'is_end' => $step->is_end,
                    'is_market_launch' => $step->is_market_launch,
                    'has_due_date' => $step->has_due_date,
                    'send_email' => $step->send_email,
                    'show_in_translation' => $step->show_in_translation,
                    'js_function' => $step->js_function,
                    'description' => $step->description,
                    'email_text' => $step->email_text,
                    'lifecycle_status' => $step->lifecycle_status,
                ]);
                $stepIdMap[$step->id] = $newStep->id;

                $functionGroupIds = $step->functionGroups()->pluck('function_groups.id');
                if ($functionGroupIds->isNotEmpty()) {
                    $newStep->functionGroups()->sync(
                        $functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $newWorkflow->tenant_id]])
                    );
                }
            });

            // after_freigabe_workflow_step_id zeigt auf einen ANDEREN Schritt
            // desselben Workflows - erst nachträglich auf die neu erzeugten
            // IDs ummappen, da beim Kopieren oben noch nicht alle Ziel-IDs
            // bekannt sind.
            $workflow->steps->whereNotNull('after_freigabe_workflow_step_id')->each(function (WorkflowStep $step) use ($stepIdMap) {
                WorkflowStep::query()->whereKey($stepIdMap[$step->id])
                    ->update(['after_freigabe_workflow_step_id' => $stepIdMap[$step->after_freigabe_workflow_step_id] ?? null]);
            });

            WorkflowGroupWindow::copyToWorkflow($workflow, $newWorkflow, $stepIdMap);
            $milestoneIdMap = WorkflowMilestone::copyToWorkflow($workflow, $newWorkflow, $stepIdMap);
            \App\Models\WorkflowMailTimer::copyToWorkflow($workflow, $newWorkflow, $stepIdMap, $milestoneIdMap);

            $workflow->update(['superseded_by_id' => $newWorkflow->id, 'active' => false]);

            return $newWorkflow;
        });

        return redirect()->route('admin.workflows', ['workflow' => $newWorkflow->id])->with('status', 'workflows-updated');
    }

    /**
     * "Kopieren": eigenständige Kopie ohne Versions-Verknüpfung - anders als
     * newVersion() bleibt das Original unangetastet (keine superseded_by_id,
     * keine Deaktivierung). Die Kopie startet inaktiv, damit sie erst nach
     * Durchsicht/Anpassung für neue Projekte wählbar wird (Ralf: "Kopie...,
     * die ich dann ändern kann").
     */
    public function duplicate(Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);

        $newWorkflow = DB::transaction(function () use ($workflow) {
            $nextSort = 1 + (int) Workflow::query()->where('tenant_id', $workflow->tenant_id)->max('sort');
            $newWorkflow = Workflow::query()->create([
                'tenant_id' => $workflow->tenant_id,
                'short_name' => $workflow->short_name,
                'name' => $workflow->name.' '.__('(Kopie)'),
                'description' => $workflow->description,
                'active' => false,
                'sort' => $nextSort,
            ]);

            $stepIdMap = [];
            $workflow->steps->each(function (WorkflowStep $step) use ($newWorkflow, &$stepIdMap) {
                $newStep = WorkflowStep::query()->create([
                    'tenant_id' => $newWorkflow->tenant_id,
                    'workflow_id' => $newWorkflow->id,
                    'title' => $step->title,
                    'short_title' => $step->short_title,
                    'milestone_title' => $step->milestone_title,
                    'sort' => $step->sort,
                    'duration_days' => $step->duration_days,
                    'duration_locked' => $step->duration_locked,
                    'is_active' => $step->is_active,
                    'is_start' => $step->is_start,
                    'is_end' => $step->is_end,
                    'is_market_launch' => $step->is_market_launch,
                    'has_due_date' => $step->has_due_date,
                    'send_email' => $step->send_email,
                    'show_in_translation' => $step->show_in_translation,
                    'js_function' => $step->js_function,
                    'description' => $step->description,
                    'email_text' => $step->email_text,
                    'lifecycle_status' => $step->lifecycle_status,
                ]);
                $stepIdMap[$step->id] = $newStep->id;

                $functionGroupIds = $step->functionGroups()->pluck('function_groups.id');
                if ($functionGroupIds->isNotEmpty()) {
                    $newStep->functionGroups()->sync(
                        $functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $newWorkflow->tenant_id]])
                    );
                }
            });

            $workflow->steps->whereNotNull('after_freigabe_workflow_step_id')->each(function (WorkflowStep $step) use ($stepIdMap) {
                WorkflowStep::query()->whereKey($stepIdMap[$step->id])
                    ->update(['after_freigabe_workflow_step_id' => $stepIdMap[$step->after_freigabe_workflow_step_id] ?? null]);
            });

            WorkflowGroupWindow::copyToWorkflow($workflow, $newWorkflow, $stepIdMap);
            $milestoneIdMap = WorkflowMilestone::copyToWorkflow($workflow, $newWorkflow, $stepIdMap);
            \App\Models\WorkflowMailTimer::copyToWorkflow($workflow, $newWorkflow, $stepIdMap, $milestoneIdMap);

            return $newWorkflow;
        });

        return redirect()->route('admin.workflows', ['workflow' => $newWorkflow->id])->with('status', 'workflows-updated');
    }

    /**
     * Windows-übliches Namensschema bei Kollision: "Name" existiert schon
     * beim Zielkunden -> "Name (1)", ist das auch belegt -> "Name (2)" usw.
     * (Ralf, 2026-09-10, direkt nach dem Bau von copyToTenant()).
     */
    private function uniqueWorkflowName(string $name, int $tenantId): string
    {
        // withoutGlobalScope('tenant') aus demselben Grund wie bei $nextSort
        // oben - $tenantId ist hier fast immer NICHT CurrentTenant::id().
        if (! Workflow::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('name', $name)->exists()) {
            return $name;
        }

        $counter = 1;
        while (Workflow::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('name', "{$name} ({$counter})")->exists()) {
            $counter++;
        }

        return "{$name} ({$counter})";
    }

    public function stepStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $workflow = Workflow::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('workflow_id'));
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);

        $nextSort = 1 + (int) WorkflowStep::query()->where('workflow_id', $workflow->id)->max('sort');
        $step = WorkflowStep::query()->create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'title' => $title,
            'sort' => $nextSort,
            'is_active' => true,
        ]);

        return redirect()->route('admin.workflows', ['workflow' => $step->workflow_id])->with('status', 'workflows-updated');
    }

    /**
     * Speichert ALLE Schritte eines Workflows in einem Rutsch (Ralfs
     * Bug-Report: ein Speichern-Button je Zeile war beim Durchgehen vieler
     * Schritte "mühsam"). Ein Feld je Schritt validiert
     * (steps.<id>.<feld>), damit ein Fehler in einer Zeile direkt darunter
     * angezeigt werden kann, ohne die anderen Zeilen zu betreffen - alles
     * oder nichts wird gespeichert (Ralfs Vorgabe: "das Speichern ist
     * blockiert", kein Teilspeichern bei Fehlern).
     */
    public function stepsBulkUpdate(Request $request): RedirectResponse|Response
    {
        $tenantId = CurrentTenant::id();
        $workflow = Workflow::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('workflow_id'));
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $stepIds = WorkflowStep::query()->where('workflow_id', $workflow->id)->pluck('id');
        $submittedIds = collect(array_keys($request->array('steps', [])))->map(fn ($id) => (int) $id);
        abort_unless($submittedIds->every(fn ($id) => $stepIds->contains($id)), 404);

        $validator = Validator::make($request->all(), [
            'steps' => ['required', 'array'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.short_title' => ['nullable', 'string', 'max:255'],
            'steps.*.milestone_title' => ['nullable', 'string', 'max:255'],
            'steps.*.duration_days' => ['nullable', 'integer', 'min:0'],
            'steps.*.js_function' => ['nullable', 'string', Rule::in(array_keys(WorkflowStep::SPECIAL_BUTTONS))],
            'steps.*.after_freigabe_workflow_step_id' => ['nullable', 'integer', Rule::exists('workflow_steps', 'id')->where('workflow_id', $workflow->id)],
            'steps.*.lifecycle_status' => ['nullable', 'integer', 'between:1,4'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.email_text' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            // Flash statt Redirect-mit-withInput: wir rendern die Seite
            // direkt neu (kein Redirect, damit fetch() nicht unbemerkt der
            // Weiterleitung folgt und Erfolg vortäuscht, siehe
            // GraphicOrderController-Bugfix) - old() braucht die geflashten
            // Werte trotzdem, um die Eingaben nicht zu verlieren.
            $request->flash();

            return response()
                ->view('admin.workflows.partials.content', $this->buildIndexData($request, $workflow->id) + [
                    // $errors muss ein ViewErrorBag sein (nicht die rohe
                    // MessageBag von validator->errors()) - @error/$errors->has()
                    // im Blade erwarten intern getBag('default'), das eine
                    // reine MessageBag nicht hat.
                    'errors' => (new ViewErrorBag)->put('default', $validator->errors()),
                ])
                ->setStatusCode(422);
        }

        $validated = $validator->validated();

        // Ein Schritt, der das Start- oder Enddatum des Projekts liefert,
        // braucht zwingend selbst einen Termin. Sonst wäre er als Quelle
        // markiert, könnte aber kein Datum liefern (konkreter Altbestand:
        // "Projektende" im Workflow "Print-Dokument (2026)"). Nur geöffnete
        // Detailzeilen prüfen; eingeklappte Schritte fehlen bewusst im Request.
        $stepErrors = [];
        foreach ($validated['steps'] as $stepId => $data) {
            if (! $request->has("steps.$stepId.duration_days")) {
                continue;
            }

            $isStartOrEnd = $request->boolean("steps.$stepId.is_start") || $request->boolean("steps.$stepId.is_end");
            if ($isStartOrEnd && ! $request->boolean("steps.$stepId.has_due_date")) {
                $stepErrors["steps.$stepId.has_due_date"] = [__('Ein Schritt für Projektstart oder Projektende muss einen Termin haben. Checkbox „Hat Termin“ markieren!')];
            }
        }

        // Pro Workflow darf es genau höchstens eine Quelle für den
        // Projektstart und eine für das Projektende geben. Für geöffnete
        // Zeilen gilt der eingereichte Stand, für eingeklappte Zeilen der
        // gespeicherte Stand, weil deren Detailfelder nicht im Request liegen.
        $markerState = WorkflowStep::query()->where('workflow_id', $workflow->id)
            ->get(['id', 'is_start', 'is_end'])
            ->mapWithKeys(function (WorkflowStep $step) use ($request) {
                $isSubmitted = $request->has("steps.$step->id.duration_days");

                return [$step->id => [
                    'is_start' => $isSubmitted ? $request->boolean("steps.$step->id.is_start") : $step->is_start,
                    'is_end' => $isSubmitted ? $request->boolean("steps.$step->id.is_end") : $step->is_end,
                ]];
            });

        foreach (['is_start' => __('Projektstart'), 'is_end' => __('Projektende')] as $field => $label) {
            $markedIds = $markerState->filter(fn (array $state) => $state[$field])->keys();
            if ($markedIds->count() <= 1) {
                continue;
            }

            foreach ($markedIds as $stepId) {
                $stepErrors["steps.$stepId.$field"] = [__('Nur ein Workflow-Schritt darf als :label markiert sein. Markierung bei den anderen Schritten entfernen!', ['label' => $label])];
            }
        }

        // Freigabe-Sonderfunktion (js_function=wfs_freigabe) braucht einen
        // Folge-WFS, der wirklich SPÄTER in der Reihenfolge liegt - sonst
        // könnte die externe Freigabe-Mail einen bereits durchlaufenen
        // Schritt erneut auslösen. Eigene Prüfung statt Validator-Rule, da
        // sie mehrere Felder (js_function + after_freigabe_workflow_step_id)
        // gegen die sort-Reihenfolge ALLER Schritte des Workflows abgleicht.
        $sortById = WorkflowStep::query()->where('workflow_id', $workflow->id)->pluck('sort', 'id');
        foreach ($validated['steps'] as $stepId => $data) {
            if (($data['js_function'] ?? null) !== 'wfs_freigabe') {
                continue;
            }
            $afterId = $data['after_freigabe_workflow_step_id'] ?? null;
            if ($afterId === null) {
                $stepErrors["steps.$stepId.after_freigabe_workflow_step_id"] = [__('Bitte den WFS nach der Freigabe festlegen.')];
            } elseif (($sortById[$afterId] ?? -1) <= ($sortById[$stepId] ?? PHP_INT_MAX)) {
                $stepErrors["steps.$stepId.after_freigabe_workflow_step_id"] = [__('Der Folge-WFS muss später in der Reihenfolge liegen als dieser Schritt.')];
            }
        }

        if ($stepErrors !== []) {
            $request->flash();

            return response()
                ->view('admin.workflows.partials.content', $this->buildIndexData($request, $workflow->id) + [
                    'errors' => (new ViewErrorBag)->put('default', new MessageBag($stepErrors)),
                ])
                ->setStatusCode(422);
        }

        DB::transaction(function () use ($request, $validated, $tenantId) {
            foreach ($validated['steps'] as $stepId => $data) {
                $step = WorkflowStep::query()->where('tenant_id', $tenantId)->findOrFail((int) $stepId);

                $update = [
                    'title' => $data['title'],
                    'is_active' => $request->boolean("steps.$stepId.is_active"),
                ];

                // Die restlichen Felder stecken im einklappbaren
                // Details-Bereich (x-if="expanded") - x-if entfernt das
                // Element bei eingeklapptem Zustand KOMPLETT aus dem DOM,
                // die Felder fehlen dann ganz im Request. Ohne diese Prüfung
                // wurden sie beim Speichern eines anderen Schritts still auf
                // 0/false/null zurückgesetzt (Datenverlust-Bug, Ralfs
                // Bug-Report: "der komplette Workflow ist geschrottet" -
                // betraf 16 von 17 Schritten bei einem einzigen Testsubmit).
                // Erkennungsmerkmal "Details war offen": duration_days ist
                // ein normales number-Feld, das nur innerhalb des
                // Details-Bereichs existiert und IMMER mitgeschickt wird,
                // wenn der Bereich offen war (auch wenn leer).
                if ($request->has("steps.$stepId.duration_days")) {
                    $update = [
                        ...$update,
                        'short_title' => $data['short_title'] ?? null,
                        'milestone_title' => $data['milestone_title'] ?? null,
                        'duration_days' => $data['duration_days'] ?? 0,
                        'duration_locked' => $request->boolean("steps.$stepId.duration_locked"),
                        'lifecycle_status' => $data['lifecycle_status'] ?? $step->lifecycle_status,
                        'js_function' => ($data['js_function'] ?? '') ?: null,
                        'after_freigabe_workflow_step_id' => ($data['js_function'] ?? '') === 'wfs_freigabe' ? ($data['after_freigabe_workflow_step_id'] ?? null) : null,
                        'description' => $data['description'] ?? null,
                        'email_text' => $data['email_text'] ?? null,
                        'is_start' => $request->boolean("steps.$stepId.is_start"),
                        'is_end' => $request->boolean("steps.$stepId.is_end"),
                        'is_market_launch' => $request->boolean("steps.$stepId.is_market_launch"),
                        'has_due_date' => $request->boolean("steps.$stepId.has_due_date"),
                        'send_email' => $request->boolean("steps.$stepId.send_email"),
                        'show_in_translation' => $request->boolean("steps.$stepId.show_in_translation"),
                    ];
                }

                $step->update($update);

                // function_groups[<id>]=1 statt function_groups[]=<id> - jede
                // Checkbox braucht einen eigenen Feldnamen, sonst würde ein
                // ungespeichertes Wiederherstellen anderer Felder nur die
                // letzte Checkbox behalten (gleiches Muster wie zuvor).
                // Steht AUSSERHALB des Details-Bereichs, wird immer
                // mitgeschickt - hier ist "leer" also ein echtes "keine
                // Funktionsgruppe angehakt", kein fehlendes Feld.
                $functionGroupIds = collect(array_keys($request->array("steps.$stepId.function_groups", [])))->map(fn ($id) => (int) $id);
                $validIds = FunctionGroup::query()->availableForTenant($tenantId, false)->whereIn('id', $functionGroupIds)->pluck('id');
                $step->functionGroups()->sync($validIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId]]));
            }
        });

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflows-updated');
    }

    /**
     * Löschen ist immer gefahrlos möglich - ein Schritt kann nur dann
     * existieren, ohne dass sein Workflow "publiziert" ist (siehe
     * stepUpdate/stepStore-Sperre), das heißt er wurde nie einem Projekt
     * zugewiesen und ist nirgends referenziert.
     */
    public function stepDestroy(WorkflowStep $step): RedirectResponse
    {
        abort_unless($step->tenant_id === CurrentTenant::id(), 404);

        $workflow = $step->workflow;
        abort_if($workflow->isPublished(), 422, 'Dieser Workflow wurde bereits veröffentlicht und kann nicht mehr geändert werden. Bitte erst eine neue Version erstellen.');

        $workflowId = $step->workflow_id;
        $step->delete();

        return redirect()->route('admin.workflows', ['workflow' => $workflowId])->with('status', 'workflows-updated');
    }

    /**
     * @return array{name: string, short_name: ?string, description: ?string}
     */
    private function validatedWorkflow(Request $request): array
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $shortName = trim((string) $request->string('short_name'));

        return [
            'name' => $name,
            'short_name' => $shortName !== '' ? mb_substr($shortName, 0, 10) : mb_substr($name, 0, 10),
            'description' => $request->filled('description') ? (string) $request->string('description') : null,
        ];
    }
}
