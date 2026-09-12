<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
     * @return array{workflows: \Illuminate\Support\Collection, selectedWorkflow: ?Workflow, steps: \Illuminate\Support\Collection, isPublished: bool, functionGroups: \Illuminate\Support\Collection, specialButtons: array, lifecycleColors: array, lifecycleStatusLabels: array, missingLifecycleStatuses: \Illuminate\Support\Collection}
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
        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->where('active', true)->orderBy('name')->get(['id', 'name']);

        $otherTenants = SystemSetting::multiTenantEnabled()
            ? Tenant::query()->where('id', '!=', $tenantId)->orderBy('name')->get(['id', 'name'])
            : collect();

        return [
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
            'steps' => $steps,
            'isPublished' => $isPublished,
            'functionGroups' => $functionGroups,
            'specialButtons' => WorkflowStep::SPECIAL_BUTTONS,
            'lifecycleColors' => WorkflowStep::LIFECYCLE_COLORS,
            'lifecycleStatusLabels' => WorkflowStep::LIFECYCLE_STATUS_LABELS,
            'missingLifecycleStatuses' => $missingLifecycleStatuses,
            'otherTenants' => $otherTenants,
        ];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
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

            $workflow->steps->each(function (WorkflowStep $step) use ($newWorkflow) {
                $newStep = WorkflowStep::query()->create([
                    'tenant_id' => $newWorkflow->tenant_id,
                    'workflow_id' => $newWorkflow->id,
                    'title' => $step->title,
                    'short_title' => $step->short_title,
                    'milestone_title' => $step->milestone_title,
                    'sort' => $step->sort,
                    'duration_days' => $step->duration_days,
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

                $functionGroupIds = $step->functionGroups()->pluck('function_groups.id');
                if ($functionGroupIds->isNotEmpty()) {
                    $newStep->functionGroups()->sync(
                        $functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $newWorkflow->tenant_id]])
                    );
                }
            });

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

            $workflow->steps->each(function (WorkflowStep $step) use ($newWorkflow) {
                $newStep = WorkflowStep::query()->create([
                    'tenant_id' => $newWorkflow->tenant_id,
                    'workflow_id' => $newWorkflow->id,
                    'title' => $step->title,
                    'short_title' => $step->short_title,
                    'milestone_title' => $step->milestone_title,
                    'sort' => $step->sort,
                    'duration_days' => $step->duration_days,
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

                $functionGroupIds = $step->functionGroups()->pluck('function_groups.id');
                if ($functionGroupIds->isNotEmpty()) {
                    $newStep->functionGroups()->sync(
                        $functionGroupIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $newWorkflow->tenant_id]])
                    );
                }
            });

            return $newWorkflow;
        });

        return redirect()->route('admin.workflows', ['workflow' => $newWorkflow->id])->with('status', 'workflows-updated');
    }

    /**
     * "Zu anderem Kunden kopieren" (Ralf, 2026-09-10, ausgelöst durch den
     * manuellen Umzug eines aus Vietto importierten WF nach _Standardkunde
     * per Tinker - "die brauchen wir"). Bewusst OHNE Funktionsgruppen-
     * Zuordnung an den Schritten: Namen/Sets unterscheiden sich je Kunde
     * (unterschiedliche Kürzel, teils fehlende Gruppen), ein Namens-Mapping
     * würde stillschweigend falsche Gruppen zuordnen können - der Admin
     * weist sie im Zielkunden bewusst selbst neu zu, UI erklärt das vorher.
     * Kein "(Kopie)"-Namenszusatz, da im Zielkunden kein Namenskonflikt
     * droht. Bleibt nach dem Kopieren im aktuell aktiven (Quell-)Kunden -
     * ein Wechsel des aktiven Mandanten wäre hier ein zu großer Nebeneffekt
     * für eine reine Kopier-Aktion.
     */
    public function copyToTenant(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->tenant_id === CurrentTenant::id(), 404);
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $targetTenant = Tenant::query()
            ->where('id', '!=', $workflow->tenant_id)
            ->findOrFail($request->integer('target_tenant_id'));

        DB::transaction(function () use ($workflow, $targetTenant) {
            // withoutGlobalScope('tenant') nötig: der automatische Scope
            // filtert sonst zusätzlich auf CurrentTenant::id() (den QUELL-
            // Kunden) - "tenant_id = Ziel AND tenant_id = Quelle" ist nie
            // wahr, max()/exists() liefern dann immer null/false, egal was
            // beim Zielkunden schon existiert (siehe uniqueWorkflowName()).
            $nextSort = 1 + (int) Workflow::withoutGlobalScope('tenant')->where('tenant_id', $targetTenant->id)->max('sort');
            $newWorkflow = Workflow::query()->create([
                'tenant_id' => $targetTenant->id,
                'short_name' => $workflow->short_name,
                'name' => $this->uniqueWorkflowName($workflow->name, $targetTenant->id),
                'description' => $workflow->description,
                'active' => false,
                'sort' => $nextSort,
            ]);

            $workflow->steps->each(fn (WorkflowStep $step) => WorkflowStep::query()->create([
                'tenant_id' => $targetTenant->id,
                'workflow_id' => $newWorkflow->id,
                'title' => $step->title,
                'short_title' => $step->short_title,
                'milestone_title' => $step->milestone_title,
                'sort' => $step->sort,
                'duration_days' => $step->duration_days,
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
            ]));
        });

        return redirect()->route('admin.workflows', ['workflow' => $workflow->id])->with('status', 'workflow-copied-to-tenant');
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
                    'errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', $validator->errors()),
                ])
                ->setStatusCode(422);
        }

        $validated = $validator->validated();

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
                        'lifecycle_status' => $data['lifecycle_status'] ?? $step->lifecycle_status,
                        'js_function' => ($data['js_function'] ?? '') ?: null,
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
                $validIds = FunctionGroup::query()->where('tenant_id', $tenantId)->whereIn('id', $functionGroupIds)->pluck('id');
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
