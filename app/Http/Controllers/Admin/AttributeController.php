<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProjectTypeMain;
use App\Services\AttributeColumnManager;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung der Projektattribute (Ralf, 2026-09-10) - drei Bereiche wie
 * in den Projektdetails (Stammdaten/Ablaufdaten/Typspezifisch), pro
 * Bereich ein Pool an schon vorhandenen festen Feldern (nur Anzeige) plus
 * frei anlegbare Zusatzfelder. Bei Typspezifisch zusätzlich die Zuordnung
 * zu Projektarten (analog Viettos adm_projattr.php, als Kästchen-Raster).
 *
 * "key" (technischer Bezeichner, bestimmt u.a. den JSON-Pfad und die
 * automatisch generierte Datenbankspalte) wird aus dem Namen abgeleitet
 * und bleibt danach unveränderlich - ein Umbenennen ändert nur "label"
 * (was der Kunde sieht). Ändern von Feldtyp/Mehrfachauswahl nach dem
 * Anlegen ist bewusst nicht vorgesehen (beides bestimmt die Datenablage) -
 * bei Bedarf neu anlegen statt nachträglich umbauen.
 */
class AttributeController extends Controller
{
    public function __construct(private readonly AttributeColumnManager $columns) {}

    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $attributes = Attribute::query()->where('tenant_id', $tenantId)->with('options')->orderBy('sort')->get()->groupBy('section');

        $categories = ProjectTypeMain::query()->where('tenant_id', $tenantId)->orderBy('sort')->with('subs')->get();

        $assignments = DB::table('attribute_project_type')
            ->whereIn('attribute_id', $attributes->get(Attribute::SECTION_TYPSPEZIFISCH, collect())->pluck('id'))
            ->get()
            ->groupBy('attribute_id')
            ->map(fn ($rows) => $rows->pluck('project_type_sub_id')->all());

        return view('admin.attributes.index', [
            'attributesBySection' => $attributes,
            'categories' => $categories,
            'assignments' => $assignments,
            'dataTypes' => $this->dataTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        $label = trim((string) $request->string('label'));
        $section = $request->string('section')->value();
        $dataType = $request->string('data_type')->value();
        abort_if($label === '', 422);
        abort_unless(in_array($section, Attribute::SECTIONS, true), 422);
        abort_unless(in_array($dataType, Attribute::DATA_TYPES, true), 422);

        $multiple = $dataType === Attribute::DATA_TYPE_SELECT && $request->boolean('multiple');

        $attribute = Attribute::query()->create([
            'tenant_id' => $tenantId,
            'section' => $section,
            'key' => $this->uniqueKey($label, $tenantId),
            'label' => $label,
            'data_type' => $dataType,
            'multiple' => $multiple,
            'sort' => 1 + (int) Attribute::query()->where('tenant_id', $tenantId)->where('section', $section)->max('sort'),
        ]);

        $this->columns->ensureColumn($attribute);

        return $this->redirectToSection($section);
    }

    /**
     * Ralf: "die Felder müssen sortierbar sein" - bestimmt die
     * Anzeigereihenfolge in den Projektdetails, gleiches Drag&Drop-Muster
     * wie bei Workflows/Projektkategorien.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('attributes'))->values()->each(function (string $id, int $index) use ($tenantId) {
            Attribute::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.projektattribute');
    }

    public function update(Request $request, Attribute $attribute): RedirectResponse
    {
        abort_unless($attribute->tenant_id === CurrentTenant::id(), 404);
        abort_if($attribute->system, 403);

        $label = trim((string) $request->string('label'));
        abort_if($label === '', 422);

        $attribute->update(['label' => $label]);

        return $this->redirectToSection($attribute->section);
    }

    public function destroy(Request $request, Attribute $attribute): RedirectResponse
    {
        abort_unless($attribute->tenant_id === CurrentTenant::id(), 404);
        abort_if($attribute->system, 403);

        DB::transaction(function () use ($attribute) {
            // Werte aus allen Projekten dieses Mandanten entfernen, statt
            // eine tote Karteileiche im JSON zu hinterlassen.
            DB::table('projects')
                ->where('tenant_id', $attribute->tenant_id)
                ->whereNotNull('attributes')
                ->update(['attributes' => DB::raw("JSON_REMOVE(attributes, '$.\"{$attribute->key}\"')")]);

            // Spalten-Aufräumung läuft jetzt über AttributeObserver::deleted(),
            // greift damit auch bei anderen Löschwegen als diesem Controller.
            $attribute->delete();
        });

        return $this->redirectToSection($attribute->section);
    }

    public function storeOption(Request $request, Attribute $attribute): RedirectResponse
    {
        abort_unless($attribute->tenant_id === CurrentTenant::id(), 404);
        abort_unless($attribute->data_type === Attribute::DATA_TYPE_SELECT, 422);

        $label = trim((string) $request->string('label'));
        abort_if($label === '', 422);

        AttributeOption::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $this->uniqueOptionValue($label, $attribute->id),
            'label' => $label,
            'sort' => 1 + (int) AttributeOption::query()->where('attribute_id', $attribute->id)->max('sort'),
        ]);

        return $this->redirectToSection($attribute->section);
    }

    public function updateOption(Request $request, AttributeOption $option): RedirectResponse
    {
        abort_unless($option->attribute->tenant_id === CurrentTenant::id(), 404);

        $label = trim((string) $request->string('label'));
        abort_if($label === '', 422);

        $option->update(['label' => $label]);

        return $this->redirectToSection($option->attribute->section);
    }

    public function destroyOption(Request $request, AttributeOption $option): RedirectResponse
    {
        abort_unless($option->attribute->tenant_id === CurrentTenant::id(), 404);

        $attribute = $option->attribute;

        DB::transaction(function () use ($option, $attribute) {
            if ($attribute->multiple) {
                // Mehrfachauswahl: Wert aus jeder Projekt-Werteliste entfernen, nicht nur die Option selbst löschen.
                $projects = DB::table('projects')->where('tenant_id', $attribute->tenant_id)
                    ->whereNotNull('attributes')
                    ->select('id', 'attributes')
                    ->get();
                foreach ($projects as $project) {
                    $values = json_decode($project->attributes, true) ?? [];
                    if (isset($values[$attribute->key]) && is_array($values[$attribute->key])) {
                        $values[$attribute->key] = array_values(array_diff($values[$attribute->key], [$option->value]));
                        if ($values[$attribute->key] === []) {
                            unset($values[$attribute->key]);
                        }
                        DB::table('projects')->where('id', $project->id)->update(['attributes' => json_encode($values)]);
                    }
                }
            } else {
                DB::table('projects')->where('tenant_id', $attribute->tenant_id)
                    ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.\"{$attribute->key}\"'))"), $option->value)
                    ->update(['attributes' => DB::raw("JSON_REMOVE(attributes, '$.\"{$attribute->key}\"')")]);
            }

            $option->delete();
        });

        return $this->redirectToSection($attribute->section);
    }

    /**
     * Kästchen-Raster-Toggle (analog Viettos ajax_projattr_set.php, aber
     * mit CSRF-Schutz/Auth/parametrisierten Queries statt der dortigen
     * rohen SQL-String-Verkettung).
     */
    public function toggleProjectType(Request $request, Attribute $attribute): RedirectResponse
    {
        abort_unless($attribute->tenant_id === CurrentTenant::id(), 404);
        abort_unless($attribute->section === Attribute::SECTION_TYPSPEZIFISCH, 422);

        $subId = $request->integer('project_type_sub_id');
        $exists = DB::table('attribute_project_type')->where('attribute_id', $attribute->id)->where('project_type_sub_id', $subId)->exists();

        if ($exists) {
            DB::table('attribute_project_type')->where('attribute_id', $attribute->id)->where('project_type_sub_id', $subId)->delete();
        } else {
            DB::table('attribute_project_type')->insert([
                'attribute_id' => $attribute->id,
                'project_type_sub_id' => $subId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->redirectToSection($attribute->section);
    }

    /**
     * Ralf-Bug-Report: nach dem Anlegen eines Attributs sprang der Reiter
     * immer auf Stammdaten zurück - der einfache redirect() zur Seite ohne
     * Query-String verlor, welcher Bereich gerade offen war (die Seite
     * liest das aus ?bereich=, siehe admin/attributes/index.blade.php).
     */
    private function redirectToSection(string $section): RedirectResponse
    {
        return redirect()->route('admin.projektattribute', ['bereich' => $section])->with('status', 'attributes-updated');
    }

    private function dataTypeOptions(): array
    {
        return [
            Attribute::DATA_TYPE_TEXT => __('Text (einzeilig)'),
            Attribute::DATA_TYPE_TEXTAREA => __('Text (mehrzeilig)'),
            Attribute::DATA_TYPE_NUMBER => __('Zahl'),
            Attribute::DATA_TYPE_DATE => __('Datum'),
            Attribute::DATA_TYPE_BOOLEAN => __('Ja/Nein'),
            Attribute::DATA_TYPE_SELECT => __('Pulldown'),
        ];
    }

    /**
     * Windows-übliches Namensschema bei Kollision, gleiches Muster wie
     * WorkflowController::uniqueWorkflowName() - hier für den technischen
     * key statt einem Anzeigenamen, daher Slug-Form.
     */
    private function uniqueKey(string $label, int $tenantId): string
    {
        $base = Str::slug($label, '_') ?: 'attribut';
        $key = $base;
        $counter = 1;
        while (Attribute::query()->where('tenant_id', $tenantId)->where('key', $key)->exists()) {
            $key = "{$base}_{$counter}";
            $counter++;
        }

        return $key;
    }

    private function uniqueOptionValue(string $label, int $attributeId): string
    {
        $base = Str::slug($label, '_') ?: 'option';
        $value = $base;
        $counter = 1;
        while (AttributeOption::query()->where('attribute_id', $attributeId)->where('value', $value)->exists()) {
            $value = "{$base}_{$counter}";
            $counter++;
        }

        return $value;
    }
}
