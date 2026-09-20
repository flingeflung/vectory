<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attribute;
use App\Models\CopyTemplate;
use App\Models\Project;
use App\Models\ProjectChecklist;
use App\Models\ProjectConnection;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\ProjectDirectoryLocator;
use App\Services\ProjectNumberAllocator;
use App\Support\CurrentTenant;
use App\Support\VersionLabel;
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

    /**
     * Feste Felder, deren Haken in der Vorlage aktuell KEINE Auswirkung
     * hat (siehe store()-Dispatch): Archiviert wird immer fest auf "nein"
     * gesetzt, "start_date" hatte im Dispatch nie einen eigenen Zweig
     * (Start/Ende kommen aus dem Workflow-Schritt, siehe system-fields/
     * start_date.blade.php - der Haken war von Anfang an wirkungslos,
     * gleicher Fehler wie bei date_progress/progress), die übrigen sind
     * reine Platzhalter ohne Datenquelle (siehe Attribute::SYSTEM_FIELDS-
     * Docblock). "publication_date" hätte technisch eine Datenquelle,
     * ist aber bewusst ausgenommen (Ralf, 2026-09-13): "Das ist aus
     * meiner Sicht eine Sache für Multichange. Ich möchte vermeiden,
     * dass man die Kopierfunktion nutzt, um Aktionen zu tätigen, die
     * eigentlich Multichange leisten soll." Alle bewusst aus der
     * Kopieren-Übersicht ausgeblendet, damit dort nur steht, was
     * wirklich zählt - gleiche Liste würde sich sonst mit Feldern füllen,
     * die so oder so nichts tun.
     */
    public const NO_EFFECT_KEYS = ['archived', 'date_progress', 'progress', 'project_connections', 'remarks_echo', 'change_log', 'publication_date', 'start_date'];

    public function form(Project $project): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless(request()->user()->can('project.create'), 403);

        $tenant = CurrentTenant::current();

        $templates = CopyTemplate::query()->where('tenant_id', $tenant->id)->orderBy('sort')->orderBy('name')->with('fields')->get();

        // Ralf: "erkennen können, welche Attribute wie kopiert werden und
        // welche nicht" - kompakte Übersicht im Formular selbst, reagiert
        // clientseitig auf die Vorlagen-Auswahl (siehe copy-project-body.blade.php).
        $copyableFields = Attribute::query()->where('tenant_id', $tenant->id)
            ->whereNotIn('key', self::NO_EFFECT_KEYS)
            ->orderBy('section')->orderBy('sort')
            ->pluck('label', 'key');

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
            'copyableFields' => $copyableFields,
            'inactivePeopleNames' => $inactivePeopleNames,
            'nextVersion' => VersionLabel::increment($project->version),
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
            // Ralf, 2026-09-20: Beim Kopieren MUSS entschieden werden - neues
            // Dokument (neue Stamm-ID) oder aufversionieren (Stamm-ID bleibt,
            // Version +1).
            'copy_mode' => ['required', Rule::in(['new', 'version'])],
        ]);

        // Aufversionieren nur mit genau einer Kopie: mehrere Nachfolger
        // derselben Version ergäben keine Kette.
        if ($validated['copy_mode'] === 'version' && (int) $validated['count'] !== 1) {
            abort(422, __('Aufversionieren ist nur mit einer Kopie möglich.'));
        }

        $template = CopyTemplate::query()->with('fields')->findOrFail($validated['template_id']);
        $checkedKeys = $template->fields->pluck('key')->all();
        // label_editable-System-Felder liegen NICHT im attributes-JSON wie
        // normale Zusatzfelder (Ausnahme aktuell: "Modell/System" - das ist
        // eine echte n:m-Verknüpfung, eigener switch-case unten, siehe
        // 'system_model') - deshalb hier explizit ausgeschlossen, damit der
        // generische JSON-Kopierpfad sie nicht fälschlich mitzunehmen
        // versucht (und dabei nichts täte, weil dort kein Wert liegt).
        $customFieldKeys = $template->fields
            ->filter(fn (Attribute $field) => (! $field->system || $field->label_editable) && $field->key !== 'system_model')
            ->pluck('key')->all();

        $sourceProject = $project;
        $tenantId = $sourceProject->tenant_id;
        $baseTitle = trim($validated['title']);
        $count = $validated['count'];
        $asNewVersion = $validated['copy_mode'] === 'version';
        $sourceAttributes = $sourceProject->attributes ?? [];
        $userId = $request->user()->id;

        $newProjectIds = DB::transaction(function () use (
            $sourceProject, $tenantId, $baseTitle, $count, $checkedKeys, $customFieldKeys, $asNewVersion, $sourceAttributes, $userId
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
                    // Kundenversion: beim Aufversionieren die letzte Zahl des Vorgängers + 1
                    // (ohne Zahl im Text bleibt sie leer, siehe VersionLabel); als
                    // neues Dokument Ausgangswert nur bei Haken übernommen
                    // (siehe Tooltipp), sonst wie bei einem frisch angelegten
                    // Projekt bei 1.
                    'version' => $asNewVersion
                        ? VersionLabel::increment($sourceProject->version)
                        : (in_array('version', $checkedKeys, true) ? $sourceProject->version : '1'),
                ];

                // Aufversionieren: Stamm-ID des Vorgängers behalten und ans Ende
                // der Kette hängen. Sonst vergibt der ProjectObserver beim
                // Anlegen eine neue Stamm-ID.
                if ($asNewVersion) {
                    $attrs['stamm_id'] = $sourceProject->stamm_id;
                    $attrs['stamm_position'] = $sourceProject->nextStammPosition();
                }

                if (in_array('project_type', $checkedKeys, true)) {
                    $attrs['project_type_main_id'] = $sourceProject->project_type_main_id;
                    $attrs['project_type_sub_id'] = $sourceProject->project_type_sub_id;
                }
                if (in_array('remarks', $checkedKeys, true)) {
                    $attrs['remarks'] = $sourceProject->remarks;
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

                Activity::log(
                    $newProject,
                    ActivityType::ProjectCopied,
                    $asNewVersion
                        ? __('Projekt neu angelegt (neue Version von :pn).', ['pn' => $sourceProject->source_pn])
                        : __('Projekt neu angelegt (Kopie von :pn).', ['pn' => $sourceProject->source_pn])
                );

                // Ralf: "wenn wir es schon hätten, dann könnte der
                // Mechanismus die Verknüpfung direkt anlegen" - anders als
                // Vietto (eigener Haken + Freitext im Kopier-Dialog) hier
                // immer automatisch mit festem Wortlaut, änderbar über die
                // normale Projektverknüpfungen-Verwaltung danach.
                ProjectConnection::query()->create([
                    'tenant_id' => $tenantId,
                    'project_id' => $sourceProject->id,
                    'related_project_id' => $newProject->id,
                    'label' => __('Kopiert nach'),
                    'label_reverse' => __('Kopiert von'),
                    'created_by_user_id' => $userId,
                ]);

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

                // Ralf, 2026-09-12 (nach Vietto-Analyse): "die Zuordnung mit
                // übernehmen, aber den Fortschritt natürlich nicht, das
                // ergibt keinen Sinn" - anders als Vietto, wo genau
                // umgekehrt nur der Abhak-Stand (checklist_items) kopiert
                // wurde, aber NICHT die Zuordnung selbst (checklist_
                // projekt_cx), was zu "unsichtbaren" Häkchen führte, die
                // erst bei erneutem manuellem Zuweisen wieder auftauchten.
                if (in_array('checklist', $checkedKeys, true)) {
                    foreach ($sourceProject->projectChecklists as $projectChecklist) {
                        ProjectChecklist::query()->create([
                            'tenant_id' => $tenantId,
                            'project_id' => $newProject->id,
                            'checklist_id' => $projectChecklist->checklist_id,
                            'activated_by_person_id' => $projectChecklist->activated_by_person_id,
                            'activated_at' => now(),
                        ]);
                    }
                }

                // Ralf, 2026-09-13: Produkt-Verknüpfung (Feld "Modell/
                // System") ist eine echte n:m-Relation, kein Zusatzfeld -
                // eigener Kopierpfad statt des generischen attributes-JSON-
                // Merges oben (der hier nichts täte).
                if (in_array('system_model', $checkedKeys, true)) {
                    $productIds = $sourceProject->products()->pluck('products.id');
                    $newProject->products()->attach($productIds);
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
