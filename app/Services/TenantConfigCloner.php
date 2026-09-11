<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\FunctionGroup;
use App\Models\LegacyRole;
use App\Models\Market;
use App\Models\MarketSet;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Kopiert die STRUKTUR/Konfiguration eines bestehenden Kunden auf einen
 * neu angelegten - Ralf: "für den TR-DL brauchen wir eine Funktion, um
 * Dinge von einem Kunden zum anderen kopieren zu können" (ehemaliger
 * Arbeitgeber hat über 100 Kunden, jedes Mal alles neu anlegen wäre
 * unzumutbar). Bewusst NUR Struktur/Konfiguration, KEINE echten Daten -
 * keine Personen, Projekte, Firmen/Subunternehmer-Einträge, die sind
 * wirklich kundenspezifisch (Ralfs eigene Abgrenzung, von ihm bestätigt).
 *
 * Reihenfolge ist wichtig: erst die "blattständigen" Kataloge (keine
 * Abhängigkeiten untereinander), danach alles, was per Fremdschlüssel auf
 * diese verweist (Workflow-Schritte -> Workflow, Projektart-Unterarten ->
 * Projektart-Hauptarten, ...) - dafür wird je Kategorie eine Alt-ID ->
 * Neu-ID-Zuordnung gemerkt.
 */
class TenantConfigCloner
{
    public function __construct(private readonly AttributeColumnManager $columns) {}

    /**
     * Legt für einen Mandanten die festen "system"-Felder an (Bezeichnung,
     * Start, Workflow, ...), falls sie noch fehlen - unabhängig davon, ob
     * dabei von einem Quell-Mandanten geklont wird (Ralf, 2026-09-10: "frei
     * mischbar mit Zusatzfeldern"). Läuft für JEDEN neuen Mandanten (siehe
     * TenantController::store()), auch ohne Quell-Mandanten - anders als
     * die Zusatzfelder unten (copyAttributes()) sind system-Felder kein
     * kopierbarer Bestand, sondern Grundausstattung.
     */
    public function seedSystemAttributes(Tenant $tenant): void
    {
        foreach (Attribute::SYSTEM_FIELDS as $section => $fields) {
            $sort = 0;
            foreach ($fields as $key => $label) {
                $exists = Attribute::query()->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)->where('key', $key)->exists();

                if (! $exists) {
                    Attribute::query()->create([
                        'tenant_id' => $tenant->id,
                        'section' => $section,
                        'system' => true,
                        'key' => $key,
                        'label' => $label,
                        'data_type' => Attribute::DATA_TYPE_TEXT,
                        'sort' => $sort,
                    ]);
                }
                $sort++;
            }
        }
    }

    /**
     * Nur in einen wirklich leeren Kunden kopierbar - Ralf: "beachte, dass
     * man schon angefangen haben könnte, Dinge zu erstellen. Wenn dann
     * jemand auf die Idee kommt, alles von Modellkunde XY rüberkopieren zu
     * wollen, kann es knallen" (Unique-Constraints kollidieren, Duplikate,
     * nicht mehr nachvollziehbare Vermischung). Aktuell nur über
     * TenantController::store() direkt nach dem Anlegen erreichbar (also
     * immer leer) - der Check bleibt trotzdem als Absicherung bestehen,
     * falls das Kopieren später auch für bestehende Kunden angeboten wird.
     */
    public function clone(Tenant $source, Tenant $target): void
    {
        abort_if($this->hasAnyConfigData($target), 422, __('Dieser Kunde hat bereits eigene Konfiguration - Kopieren ist nur in einen leeren Kunden möglich.'));

        DB::transaction(function () use ($source, $target) {
            $departmentMap = $this->copySimple(Department::class, $source->id, $target->id, ['legacy_id', 'name', 'short_name', 'sort', 'active']);
            $this->copySimple(LegacyRole::class, $source->id, $target->id, ['legacy_id', 'name', 'sort']);
            $this->copySimple(BusinessUnit::class, $source->id, $target->id, ['name', 'sort', 'active']);
            $functionGroupMap = $this->copySimple(FunctionGroup::class, $source->id, $target->id, ['legacy_id', 'name', 'short_name', 'sort', 'active']);
            $marketMap = $this->copySimple(Market::class, $source->id, $target->id, [
                'legacy_id', 'country_iso', 'country_name', 'country_short_name', 'language_code', 'language_name', 'no_translation', 'sort',
            ]);
            $attributeMap = $this->copyAttributes($source->id, $target->id);
            $projectTypeMainMap = $this->copySimple(ProjectTypeMain::class, $source->id, $target->id, ['legacy_id', 'name', 'sort']);

            $projectTypeSubMap = $this->copyProjectTypeSubs($source->id, $target->id, $projectTypeMainMap);
            $this->copyMarketSets($source->id, $target->id, $marketMap);
            $this->copyAttributeProjectTypeLinks($attributeMap, $projectTypeSubMap);
            $workflowMap = $this->copyWorkflows($source->id, $target->id);
            $workflowStepMap = $this->copyWorkflowSteps($source->id, $target->id, $workflowMap);
            $this->copyWorkflowStepFunctionGroups($target->id, $workflowStepMap, $functionGroupMap);

            unset($departmentMap);
        });
    }

    /**
     * Rohe DB-Abfragen statt Eloquent - $target ist i.d.R. NICHT der
     * gerade aktive Mandant, eine normale Query würde sonst zusätzlich auf
     * CurrentTenant::id() einschränken und immer "leer" melden (gleiche
     * Falle wie bei Tenant::hasData()).
     */
    private function hasAnyConfigData(Tenant $target): bool
    {
        $tables = [
            'departments', 'legacy_roles', 'business_units', 'function_groups',
            'markets', 'attributes', 'project_type_mains', 'project_type_subs',
            'market_sets', 'workflows', 'workflow_steps',
        ];

        foreach ($tables as $table) {
            if (DB::table($table)->where('tenant_id', $target->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Für "blattständige" Kataloge ohne Fremdschlüssel auf andere
     * tenant-gescopte Modelle - einfache 1:1-Kopie mit neuer tenant_id.
     *
     * @return array<int, int> Alte ID => neue ID
     */
    private function copySimple(string $modelClass, int $sourceTenantId, int $targetTenantId, array $columns): array
    {
        $map = [];

        /** @var Model $modelClass */
        $modelClass::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->get()
            ->each(function (Model $row) use ($modelClass, $targetTenantId, $columns, &$map) {
                $data = collect($row->getAttributes())->only($columns)->all();
                $data['tenant_id'] = $targetTenantId;
                $new = $modelClass::query()->create($data);
                $map[$row->id] = $new->id;
            });

        return $map;
    }

    /**
     * @return array<int, int> Alte Unterart-ID => neue Unterart-ID
     */
    private function copyProjectTypeSubs(int $sourceTenantId, int $targetTenantId, array $projectTypeMainMap): array
    {
        $map = [];

        ProjectTypeSub::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->get()
            ->each(function (ProjectTypeSub $row) use ($targetTenantId, $projectTypeMainMap, &$map) {
                $new = ProjectTypeSub::query()->create([
                    'tenant_id' => $targetTenantId,
                    'project_type_main_id' => $projectTypeMainMap[$row->project_type_main_id] ?? null,
                    'legacy_id' => $row->legacy_id,
                    'name' => $row->name,
                    'color' => $row->color,
                    'symbol' => $row->symbol,
                    'sort' => $row->sort,
                ]);
                $map[$row->id] = $new->id;
            });

        return $map;
    }

    /**
     * Zusatzfelder (system=false) - die festen system-Felder werden separat
     * über seedSystemAttributes() angelegt, nicht hier kopiert (sonst
     * gäbe es sie doppelt, siehe TenantController::store()). Jedes kopierte
     * Feld bekommt sofort seine generierte Schnell-Filter-Spalte
     * (AttributeColumnManager), genau wie beim manuellen Anlegen.
     *
     * @return array<int, int> Alte Attribut-ID => neue Attribut-ID
     */
    private function copyAttributes(int $sourceTenantId, int $targetTenantId): array
    {
        $map = [];

        Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->where('system', false)
            ->with('options')->get()
            ->each(function (Attribute $row) use ($targetTenantId, &$map) {
                $new = Attribute::query()->create([
                    'tenant_id' => $targetTenantId,
                    'section' => $row->section,
                    'key' => $row->key,
                    'label' => $row->label,
                    'data_type' => $row->data_type,
                    'multiple' => $row->multiple,
                    'available_in_mail_templates' => $row->available_in_mail_templates,
                    'sort' => $row->sort,
                ]);
                $map[$row->id] = $new->id;

                foreach ($row->options as $option) {
                    AttributeOption::query()->create([
                        'attribute_id' => $new->id,
                        'value' => $option->value,
                        'label' => $option->label,
                        'sort' => $option->sort,
                    ]);
                }

                $this->columns->ensureColumn($new);
            });

        return $map;
    }

    private function copyMarketSets(int $sourceTenantId, int $targetTenantId, array $marketMap): void
    {
        MarketSet::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->get()
            ->each(function (MarketSet $row) use ($targetTenantId, $marketMap) {
                $new = MarketSet::query()->create([
                    'tenant_id' => $targetTenantId,
                    'legacy_id' => $row->legacy_id,
                    'name' => $row->name,
                    'sort' => $row->sort,
                ]);

                $newMarketIds = $row->markets()->withoutGlobalScope('tenant')->pluck('markets.id')
                    ->map(fn ($id) => $marketMap[$id] ?? null)
                    ->filter()
                    ->values();

                $new->markets()->sync($newMarketIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $targetTenantId]]));
            });
    }

    /**
     * attribute_project_type hat bewusst KEIN eigenes tenant_id (siehe
     * Modell-Docblock) - Scoping läuft nur indirekt über attribute_id.
     * project_type_sub_id ist der echte Fremdschlüssel (siehe Migration
     * 2026_09_10_100003_fix_attribute_project_type...) - beide Seiten der
     * Zuordnung müssen auf die neu kopierten IDs des Zielmandanten
     * umgemappt werden, nicht nur attribute_id.
     */
    private function copyAttributeProjectTypeLinks(array $attributeMap, array $projectTypeSubMap): void
    {
        $sourceAttributeIds = array_keys($attributeMap);
        if ($sourceAttributeIds === []) {
            return;
        }

        DB::table('attribute_project_type')->whereIn('attribute_id', $sourceAttributeIds)->get()
            ->each(function ($row) use ($attributeMap, $projectTypeSubMap) {
                if (! isset($projectTypeSubMap[$row->project_type_sub_id])) {
                    return;
                }

                DB::table('attribute_project_type')->insert([
                    'attribute_id' => $attributeMap[$row->attribute_id],
                    'project_type_sub_id' => $projectTypeSubMap[$row->project_type_sub_id],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Zwei Durchgänge: erst alle Workflows ohne superseded_by_id anlegen
     * (die Zuordnung steht ja erst danach fest, weil es ein
     * Selbstverweis ist), dann in einem zweiten Durchgang nachtragen.
     *
     * @return array<int, int>
     */
    private function copyWorkflows(int $sourceTenantId, int $targetTenantId): array
    {
        $map = [];
        $sourceWorkflows = Workflow::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->get();

        $sourceWorkflows->each(function (Workflow $row) use ($targetTenantId, &$map) {
            $new = Workflow::query()->create([
                'tenant_id' => $targetTenantId,
                'legacy_id' => $row->legacy_id,
                'short_name' => $row->short_name,
                'name' => $row->name,
                'description' => $row->description,
                'active' => $row->active,
                'sort' => $row->sort,
            ]);
            $map[$row->id] = $new->id;
        });

        $sourceWorkflows->whereNotNull('superseded_by_id')->each(function (Workflow $row) use ($map) {
            if (isset($map[$row->id], $map[$row->superseded_by_id])) {
                Workflow::query()->withoutGlobalScope('tenant')->whereKey($map[$row->id])->update(['superseded_by_id' => $map[$row->superseded_by_id]]);
            }
        });

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function copyWorkflowSteps(int $sourceTenantId, int $targetTenantId, array $workflowMap): array
    {
        $map = [];

        WorkflowStep::query()->withoutGlobalScope('tenant')->where('tenant_id', $sourceTenantId)->get()
            ->each(function (WorkflowStep $row) use ($targetTenantId, $workflowMap, &$map) {
                if (! isset($workflowMap[$row->workflow_id])) {
                    return;
                }

                $new = WorkflowStep::query()->create([
                    'tenant_id' => $targetTenantId,
                    'workflow_id' => $workflowMap[$row->workflow_id],
                    'legacy_id' => $row->legacy_id,
                    'title' => $row->title,
                    'short_title' => $row->short_title,
                    'milestone_title' => $row->milestone_title,
                    'sort' => $row->sort,
                    'duration_days' => $row->duration_days,
                    'is_active' => $row->is_active,
                    'is_start' => $row->is_start,
                    'is_end' => $row->is_end,
                    'is_market_launch' => $row->is_market_launch,
                    'has_due_date' => $row->has_due_date,
                    'send_email' => $row->send_email,
                    'show_in_translation' => $row->show_in_translation,
                    'js_function' => $row->js_function,
                    'js_function_param' => $row->js_function_param,
                    'description' => $row->description,
                    'email_text' => $row->email_text,
                    'msg_task_function_group_ids' => $row->msg_task_function_group_ids,
                    'lifecycle_status' => $row->lifecycle_status,
                ]);
                $map[$row->id] = $new->id;
            });

        return $map;
    }

    private function copyWorkflowStepFunctionGroups(int $targetTenantId, array $workflowStepMap, array $functionGroupMap): void
    {
        if ($workflowStepMap === []) {
            return;
        }

        DB::table('workflow_step_function_group')->whereIn('workflow_step_id', array_keys($workflowStepMap))->get()
            ->each(function ($row) use ($targetTenantId, $workflowStepMap, $functionGroupMap) {
                if (! isset($functionGroupMap[$row->function_group_id])) {
                    return;
                }

                DB::table('workflow_step_function_group')->insert([
                    'tenant_id' => $targetTenantId,
                    'workflow_step_id' => $workflowStepMap[$row->workflow_step_id],
                    'function_group_id' => $functionGroupMap[$row->function_group_id],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
}
