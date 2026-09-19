<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\Workflow;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung der Projektschablonen (Erfahrungswerte-Katalog für die
 * Redaktionsleitung, Nachfolger von Viettos GA-Kategorien - siehe
 * ProjectTemplate-Model-Docblock). Step 1 von Ralfs 4-Schritte-Plan zur
 * Kapa-Planung (2026-09-18, siehe Roadmap-Backlog): hier der reine Katalog.
 * Step 3 (Workflow-Kopplung) bestimmt jetzt die für Step 2 (Stunden je
 * Funktionsgruppe) zuweisbaren Fktgrp - Reihenfolge Ralf-korrigiert
 * (2026-09-18): "Zuerst muss ein WF gekoppelt werden, erst dadurch ergeben
 * sich die Fktgrps", siehe ProjectTemplate::relevantFunctionGroups().
 *
 * Master-Detail-Layout (Ralf, 2026-09-18: "nach dem bewährten Muster von
 * Projektkategorien, Workflows usw.: links die Schablonen, rechts die
 * Details") - links die (ggf. gefilterte) Liste, rechts entweder die
 * gewählte Schablone zum Bearbeiten (?schablone=<id>), ein Anlegen-Formular
 * (?neu=1) oder ein Platzhalter. Gleiches Grundmuster wie WorkflowController:
 * Umbenennen/Speichern läuft per fetch() + reloadManageListPreservingEdits()
 * (X-Overlay-Header) statt vollem Seiten-Reload.
 */
class ProjectTemplateController extends Controller
{
    public function index(Request $request): View|Response
    {
        $data = $this->buildIndexData($request, $request->filled('schablone') ? (int) $request->query('schablone') : null);

        if ($this->isOverlayRequest($request)) {
            return response()->view('admin.project-templates.partials.content', $data);
        }

        return view('admin.project-templates.index', $data);
    }

    /**
     * @return array{templates: \Illuminate\Support\Collection, selectedTemplate: ?ProjectTemplate, workflows: \Illuminate\Support\Collection, creating: bool}
     */
    private function buildIndexData(Request $request, ?int $templateId): array
    {
        $tenantId = CurrentTenant::id();

        $query = ProjectTemplate::query()->where('tenant_id', $tenantId);

        foreach (ProjectTemplate::filterableFields() as $field) {
            if ($request->filled($field)) {
                $query->where($field, (int) $request->query($field));
            }
        }

        // Ralf, 2026-09-19: frei per Drag & Drop sortierbar (Spalte sort). Die
        // Startreihenfolge kam aus der früheren festen Sortierung nach Dauer
        // (Wochen vor Monaten wie in Viettos gakat.php), siehe Migration.
        $templates = $query->orderBy('sort')->orderBy('name')->get();

        // Nur für die AUSGEWÄHLTE Schablone die schwereren Relationen laden
        // (gleiches Muster wie WorkflowController: steps() nur für
        // $selectedWorkflow) - die Liste links bleibt schlank.
        $selectedTemplate = $templateId
            ? ProjectTemplate::query()->where('tenant_id', $tenantId)
                ->with(['createdByUser.person', 'updatedByUser.person', 'functionGroups', 'workflow.steps.functionGroups'])
                ->find($templateId)
            : null;

        // Alle Workflows des Mandanten fürs Auswahlfeld, auch inaktive/
        // ersetzte (grau markiert in der View) - eine Schablone könnte
        // schon auf einen solchen zeigen, siehe Auswahllisten-Konvention
        // in CLAUDE.md statt der "aktiv ODER gerade zugewiesen"-Variante.
        $workflows = Workflow::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get();

        // "Von anderem Kunden importieren" (Ralf, 2026-09-18) - gleiches
        // Muster wie Papierformate: pull-basiert. Bewusst nur für Heimat-
        // Admin/Super-Admin (Ralf: "das darf ja wieder nur vom H-Admin aus
        // möglich sein", gleiche Mandanten-Grenze wie sonst im Rollenmodell) -
        // eigener Check statt sich allein auf availableTenants() zu
        // verlassen, das über person_tenant auch einzelnen ausgeliehenen
        // Personen Zugriff auf einen fremden Mandanten geben kann, was hier
        // NICHT reichen soll (Katalog-Import ist keine Personen-Ausleihe).
        $otherTenants = CurrentTenant::isHomeTenantAdmin($request->user()) || $request->user()->role === 'super_admin'
            ? CurrentTenant::availableTenants()->reject(fn ($t) => $t->id === $tenantId)->values()
            : collect();

        return [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'workflows' => $workflows,
            'otherTenants' => $otherTenants,
            'creating' => ! $selectedTemplate && $request->boolean('neu'),
        ];
    }

    private function isOverlayRequest(Request $request): bool
    {
        return $request->header('X-Overlay') === '1';
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $validated = $this->validated($request, $tenantId);

        $template = ProjectTemplate::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    /**
     * Drag & Drop in der Liste links. Mit aktivem Merkmal-Filter kommt nur die
     * gefilterte Teilmenge in neuer Reihenfolge an - sie tauscht dann nur die
     * Plätze untereinander, alle nicht sichtbaren Schablonen behalten ihre
     * Position (sonst würde ein Verschieben im Filter die übrige Reihenfolge
     * durcheinanderbringen).
     */
    public function reorder(Request $request): Response
    {
        $tenantId = CurrentTenant::id();
        $received = collect($request->array('templates'))->map(fn ($id) => (int) $id)->unique()->values();

        $all = ProjectTemplate::query()->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->pluck('id');
        $subset = $received->filter(fn ($id) => $all->contains($id))->values();
        abort_if($subset->isEmpty(), 422);

        // Plätze der Teilmenge in ihrer bisherigen Reihenfolge, neu belegt in der empfangenen Reihenfolge.
        $queue = $subset->all();
        // Normales Closure mit Referenz - eine Arrow-Funktion würde $queue nur kopieren
        // und immer wieder das erste Element liefern.
        $ordered = $all->map(function ($id) use ($subset, &$queue) {
            return $subset->contains($id) ? array_shift($queue) : $id;
        });

        DB::transaction(function () use ($ordered, $tenantId) {
            $ordered->each(fn ($id, $index) => ProjectTemplate::query()->where('tenant_id', $tenantId)->whereKey($id)->update(['sort' => $index + 1]));
        });

        return response()->noContent();
    }

    public function update(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $validated = $this->validated($request, $template->tenant_id);

        $template->update([
            ...$validated,
            'active' => $request->boolean('active'),
            'updated_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    /**
     * Geplante Stunden je Funktionsgruppe - eine leere/0-Eingabe entfernt
     * die Fktgrp aus der Schablone (kein 0-Stunden-Eintrag), statt sie als
     * "0 h geplant" stehen zu lassen.
     */
    public function updateFunctionGroups(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $request->validate(['hours' => ['nullable', 'array'], 'hours.*' => ['nullable', 'numeric', 'min:0', 'max:999']]);

        $hours = collect($request->array('hours'))
            ->mapWithKeys(fn ($value, $functionGroupId) => [(int) $functionGroupId => $value])
            ->filter(fn ($value) => $value !== null && $value !== '' && (float) $value > 0);

        // Nur Fktgrp, die der gekoppelte Workflow tatsächlich liefert (siehe
        // ProjectTemplate::relevantFunctionGroups()) - nicht mehr der volle
        // Mandanten-Katalog, sonst könnte hier eine fachlich nicht (mehr)
        // zutreffende Fktgrp durchrutschen (z.B. nach Workflow-Wechsel).
        $validIds = $template->load('workflow.steps.functionGroups')->relevantFunctionGroups()
            ->pluck('id')->intersect($hours->keys());

        $syncData = $validIds->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $template->tenant_id, 'planned_hours' => (float) $hours[$id]],
        ]);

        $template->functionGroups()->sync($syncData);

        return redirect()->route('admin.projektschablonen', ['schablone' => $template->id])->with('status', 'projektschablonen-updated');
    }

    public function destroy(Request $request, ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $template->delete();

        return redirect()->route('admin.projektschablonen')->with('status', 'projektschablonen-updated');
    }

    /**
     * "Klonen" (Ralf, 2026-09-18) - vollständige Kopie IM SELBEN Mandanten,
     * inklusive Workflow-Kopplung und Stunden je Funktionsgruppe (die bleiben
     * gültig, anders als beim Übernehmen von einem anderen Kunden unten).
     * Startet inaktiv, gleiches Prinzip wie Workflow::duplicate() - "Kopie,
     * die man erst noch durchsieht/anpasst", bevor sie in der Liste als
     * vollwertig auftaucht.
     */
    public function duplicate(ProjectTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $new = DB::transaction(function () use ($template) {
            $new = ProjectTemplate::query()->create([
                ...$this->characteristicsData($template),
                'tenant_id' => $template->tenant_id,
                'name' => $template->name.' '.__('(Kopie)'),
                'workflow_id' => $template->workflow_id,
                'active' => false,
                'created_by_user_id' => Auth::id(),
            ]);

            $template->functionGroups->each(fn ($fg) => $new->functionGroups()->attach($fg->id, [
                'tenant_id' => $template->tenant_id,
                'planned_hours' => $fg->pivot->planned_hours,
            ]));

            return $new;
        });

        return redirect()->route('admin.projektschablonen', ['schablone' => $new->id])->with('status', 'projektschablonen-updated');
    }

    /**
     * Liefert den Schablonen-Katalog EINES anderen (erreichbaren) Kunden für
     * den zweiten Auswahlschritt im "Von anderem Kunden holen"-Dialog (siehe
     * View) - withoutGlobalScope('tenant'), da $sourceTenantId absichtlich
     * NICHT der aktive Mandant ist.
     */
    public function catalogFromTenant(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(CurrentTenant::isHomeTenantAdmin($user) || $user->role === 'super_admin', 403);

        $source = CurrentTenant::availableTenants()->firstWhere('id', $request->integer('tenant_id'));
        abort_if($source === null || $source->id === CurrentTenant::id(), 422);

        $templates = ProjectTemplate::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $source->id)->orderBy('name')->get();

        return response()->json($templates->map(fn (ProjectTemplate $t) => [
            'id' => $t->id,
            'label' => $t->name.' ('.rtrim(rtrim((string) $t->duration_value, '0'), '.').' '.ProjectTemplate::durationUnitOptions()[$t->duration_unit].')',
        ]));
    }

    /**
     * "Von einem anderen Kunden holen" (Ralf, 2026-09-18) - pull-basiert wie
     * bei den Papierformaten abgestimmt ("Stand im Zielkunden, Quelle
     * wählen"), hier aber gezielt EINE einzelne Schablone statt des ganzen
     * Katalogs (Ralfs Entscheidung: Schablonen sind fachlich zu
     * unterschiedlich für einen Alles-oder-nichts-Import). Bewusst OHNE
     * Workflow-Kopplung/Fktgrp-Stunden - Workflows/Funktionsgruppen
     * unterscheiden sich je Kunde, gleiche Begründung wie bei
     * WorkflowController::copyToTenant(). Startet inaktiv (siehe duplicate()).
     */
    public function importFromTenant(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(CurrentTenant::isHomeTenantAdmin($user) || $user->role === 'super_admin', 403);

        $tenantId = CurrentTenant::id();
        $source = CurrentTenant::availableTenants()->firstWhere('id', $request->integer('source_tenant_id'));
        abort_if($source === null || $source->id === $tenantId, 422);

        $sourceTemplate = ProjectTemplate::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $source->id)->findOrFail($request->integer('source_template_id'));

        $new = ProjectTemplate::query()->create([
            ...$this->characteristicsData($sourceTemplate),
            'tenant_id' => $tenantId,
            'name' => $this->uniqueTemplateName($sourceTemplate->name, $tenantId),
            'active' => false,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.projektschablonen', ['schablone' => $new->id])->with('status', 'projektschablonen-updated');
    }

    /**
     * Windows-übliches Namensschema bei Kollision, gleiches Muster wie
     * WorkflowController::uniqueWorkflowName().
     */
    private function uniqueTemplateName(string $name, int $tenantId): string
    {
        if (! ProjectTemplate::query()->where('tenant_id', $tenantId)->where('name', $name)->exists()) {
            return $name;
        }

        $counter = 1;
        while (ProjectTemplate::query()->where('tenant_id', $tenantId)->where('name', "{$name} ({$counter})")->exists()) {
            $counter++;
        }

        return "{$name} ({$counter})";
    }

    /**
     * Gemeinsame Feldliste für duplicate()/importFromTenant() - alles außer
     * Name/Mandant/Workflow/Aktiv/Ersteller, die je nach Aufrufer variieren.
     *
     * @return array<string, mixed>
     */
    private function characteristicsData(ProjectTemplate $source): array
    {
        $data = ['format' => $source->format, 'duration_value' => $source->duration_value, 'duration_unit' => $source->duration_unit, 'remarks' => $source->remarks];

        foreach (array_keys(ProjectTemplate::characteristicFields()) as $field) {
            $data[$field] = $source->$field;
        }

        return $data;
    }

    private function validated(Request $request, int $tenantId): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'integer', 'in:1,2,3'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->where('tenant_id', $tenantId)],
            'duration_value' => ['required', 'numeric', 'min:0.5', 'max:999', 'multiple_of:0.5'],
            'duration_unit' => ['required', 'string', 'in:weeks,months'],
            'remarks' => ['nullable', 'string'],
        ];

        foreach (ProjectTemplate::characteristicFields() as $field => $meta) {
            $rules[$field] = ['required', 'integer', 'in:'.implode(',', array_keys($meta['options']))];
        }

        return $request->validate($rules);
    }
}
