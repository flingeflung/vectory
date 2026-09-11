<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\CopyTemplate;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\ProjectDirectoryLocator;
use App\Services\ProjectNumberAllocator;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Projekte kopieren" (Ralf, 2026-09-11, Phase 2) - welche Felder
 * übernommen werden, bestimmt die gewählte Vorlage (siehe CopyTemplate,
 * Admin > Projektkopie-Vorlagen). Im Unterschied zu Vietto (kopiert fast
 * alles blind, siehe ajax_copyprojekte.php) ist das hier explizit
 * konfigurierbar.
 */
class ProjectCopyController extends Controller
{
    public function __construct(
        private readonly ProjectDirectoryLocator $directoryLocator,
        private readonly ProjectNumberAllocator $numberAllocator,
    ) {}

    public function form(Project $project): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless(request()->user()->can('project.create'), 403);

        $tenant = CurrentTenant::current();

        $templates = CopyTemplate::query()->where('tenant_id', $tenant->id)->orderBy('sort')->orderBy('name')->with('fields')->get();

        // Ralf: "wenn 'kopieren' angehakt ist, dann Prüfung, ob eine der
        // Personen inaktiv ist, und ggf. Hinweis geben, bevor der
        // Kopiervorgang gestartet ist" - unabhängig von der gewählten
        // Vorlage einmal vorberechnet, im Formular nur sichtbar, wenn die
        // gewählte Vorlage "Projektbeteiligte Personen" überhaupt anhakt.
        $inactivePeopleNames = $project->projectPeople()->with('person')->get()
            ->pluck('person')->filter()->unique('id')
            ->reject(fn ($person) => $person->active)
            ->map(fn ($person) => $person->fullName())
            ->values();

        return view('projekte.partials.copy-project-body', [
            'project' => $project,
            'tenant' => $tenant,
            'templates' => $templates,
            'inactivePeopleNames' => $inactivePeopleNames,
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($request->user()->can('project.create'), 403);

        $tenant = CurrentTenant::current();

        $validated = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('copy_templates', 'id')->where('tenant_id', $tenant->id)],
            'count' => ['required', 'integer', 'min:1', 'max:'.$tenant->max_project_copies],
            'title' => ['required', 'string', 'max:255'],
            'increment_version' => ['boolean'],
        ]);

        $template = CopyTemplate::query()->with('fields')->findOrFail($validated['template_id']);
        $checkedKeys = $template->fields->pluck('key')->all();
        $customFieldKeys = $template->fields->where('system', false)->pluck('key')->all();

        $sourceProject = $project;
        $tenantId = $sourceProject->tenant_id;
        $baseTitle = trim($validated['title']);
        $count = $validated['count'];
        $incrementVersion = $request->boolean('increment_version');
        $sourceAttributes = $sourceProject->attributes ?? [];

        $newProjectIds = DB::transaction(function () use (
            $sourceProject, $tenantId, $baseTitle, $count, $checkedKeys, $customFieldKeys, $incrementVersion, $sourceAttributes
        ) {
            // Zeilen-Lock auf den Mandanten als Mutex - gleiches Prinzip wie
            // ProjectController::store(), damit mehrere gleichzeitige
            // Kopiervorgänge nie dieselbe PN vorgeschlagen bekommen.
            Tenant::query()->where('id', $tenantId)->lockForUpdate()->first();

            $year = (int) now()->format('y');
            $newProjectIds = [];

            for ($i = 1; $i <= $count; $i++) {
                $title = $count > 1 ? "{$baseTitle} Kopie {$i}" : $baseTitle;

                $attrs = [
                    'tenant_id' => $tenantId,
                    'source_pn' => $this->numberAllocator->nextFreePn($year, $tenantId),
                    'title' => $title,
                    // Version: Ausgangswert nur bei Haken übernommen (siehe
                    // Tooltipp), sonst wie bei einem frisch angelegten
                    // Projekt bei 1. Ohne Haken ergibt "+1?" ohnehin keinen
                    // Sinn (siehe Formular, Checkbox dann ausgeblendet).
                    'version' => in_array('version', $checkedKeys, true) ? $sourceProject->version : 1,
                ];

                if (in_array('project_type', $checkedKeys, true)) {
                    $attrs['project_type_main_id'] = $sourceProject->project_type_main_id;
                    $attrs['project_type_sub_id'] = $sourceProject->project_type_sub_id;
                }
                if (in_array('remarks', $checkedKeys, true)) {
                    $attrs['remarks'] = $sourceProject->remarks;
                }
                if (in_array('publication_date', $checkedKeys, true)) {
                    $attrs['publication_date'] = $sourceProject->publication_date;
                }
                // Status/Erstellungsstatus ist ein gemeinsames Feld für zwei
                // Spalten mit unterschiedlicher Kopierlogik (siehe Tooltipp):
                // "status" selbst wird NIE 1:1 übernommen (Kopie startet
                // immer "Geplant" bzw. folgt gleich unten dem mitkopierten
                // Workflow) - der Haken steuert hier nur "creation_type".
                if (in_array('status', $checkedKeys, true)) {
                    $attrs['creation_type'] = $sourceProject->creation_type;
                }

                // Zusatzfelder (system=false) leben im attributes-JSON,
                // Wert 1:1 übernehmen, wenn im Ausgangsprojekt gesetzt.
                $copiedAttributeValues = [];
                foreach ($customFieldKeys as $key) {
                    if (array_key_exists($key, $sourceAttributes)) {
                        $copiedAttributeValues[$key] = $sourceAttributes[$key];
                    }
                }
                if ($copiedAttributeValues !== []) {
                    $attrs['attributes'] = $copiedAttributeValues;
                }

                $newProject = Project::query()->create($attrs);

                Activity::log($newProject, ActivityType::ProjectCopied, __('Projekt neu angelegt (Kopie von :pn).', ['pn' => $sourceProject->source_pn]));

                if (in_array('markets', $checkedKeys, true)) {
                    $newProject->markets()->sync(
                        $sourceProject->markets->mapWithKeys(fn ($market) => [$market->id => ['tenant_id' => $tenantId]])
                    );
                }

                if (in_array('project_people', $checkedKeys, true)) {
                    foreach ($sourceProject->projectPeople as $projectPerson) {
                        ProjectPerson::query()->create([
                            'tenant_id' => $tenantId,
                            'project_id' => $newProject->id,
                            'function_group_id' => $projectPerson->function_group_id,
                            'person_id' => $projectPerson->person_id,
                            'is_primary' => $projectPerson->is_primary,
                        ]);
                    }
                }

                if (in_array('workflow_id', $checkedKeys, true) && $sourceProject->workflow_id) {
                    $currentWorkflow = $this->resolveCurrentWorkflow($sourceProject->workflow);
                    $newProject->update(['workflow_id' => $currentWorkflow->id]);

                    WorkflowStep::query()->where('workflow_id', $currentWorkflow->id)->get()
                        ->each(fn (WorkflowStep $step) => ProjectWorkflowStep::query()->firstOrCreate(
                            ['project_id' => $newProject->id, 'workflow_step_id' => $step->id],
                            ['tenant_id' => $tenantId, 'sort' => $step->sort]
                        ));

                    // Status automatisch aus dem "Geplant"-Schritt ableiten
                    // (lifecycle_status 1), analog
                    // ProjectWorkflowStepController::activate() - findet der
                    // Workflow keinen (siehe Backlog "WFS: alle 4
                    // Lifecycle-Status abgedeckt?"), bleibt die Kopie
                    // einfach beim DB-Default "Geplant" stehen.
                    $plannedStep = $newProject->projectWorkflowSteps()
                        ->whereHas('workflowStep', fn ($query) => $query->where('lifecycle_status', 1))
                        ->first();

                    if ($plannedStep) {
                        $plannedStep->update(['is_current' => true, 'started_at' => now()]);
                        $newProject->update(['status' => 0]);
                    }
                }

                if (in_array('version', $checkedKeys, true) && $incrementVersion) {
                    $newProject->increment('version');
                }

                $basePath = $this->directoryLocator->basePath($tenantId);
                if ($basePath !== null && is_dir($basePath)) {
                    $this->directoryLocator->create($basePath, $this->directoryLocator->suggestedFolderName($newProject));
                }

                $newProjectIds[] = $newProject->id;
            }

            return $newProjectIds;
        });

        return response()->json(['ids' => $newProjectIds]);
    }

    /**
     * "Hochhangeln" von einer alten Workflow-Version zur aktuellen (Ralf:
     * Szenario WF Print V1 im Quell-Projekt, aktuell ist aber V4) - läuft
     * die superseded_by_id-Kette komplett bis zum Ende durch, statt nur
     * einen Schritt zu prüfen. WorkflowController::newVersion() setzt
     * superseded_by_id+active=false konsequent beim alten Workflow, das
     * Kettenende ist damit immer die aktuelle Version.
     */
    private function resolveCurrentWorkflow(Workflow $workflow): Workflow
    {
        while ($workflow->supersededBy) {
            $workflow = $workflow->supersededBy;
        }

        return $workflow;
    }
}
