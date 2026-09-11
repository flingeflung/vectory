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

        $allAttributes = Attribute::query()->where('tenant_id', $tenantId)->with('options')->orderBy('sort')->get();

        // Ralf, 2026-09-11: "wenn > 0: komplett sperren, nur für Super-Admin
        // zu löschen" - ein Admin mit noch nicht gesperrtem Zugang könnte
        // sonst versehentlich (oder mutwillig, siehe Ralfs Kündigungs-
        // Szenario) Werte aus tausenden Projekten unwiderruflich löschen.
        // Zähler hier einmal pro Attribut vorberechnet, damit Anzeige (Sperre/
        // Warnhinweis) und die serverseitige Durchsetzung in destroy()
        // dieselbe Zahl verwenden.
        $valueCounts = $allAttributes->where('system', false)
            ->mapWithKeys(fn (Attribute $attribute) => [$attribute->id => $this->valueCount($attribute)]);

        $attributes = $allAttributes->groupBy('section');

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
            'valueCounts' => $valueCounts,
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

        // Ralf: bei vorhandenen Werten darf nur Super-Admin löschen (Szenario:
        // gekündigter Mitarbeiter mit Admin-Rechten, dessen Zugang noch nicht
        // gesperrt ist) - serverseitig durchgesetzt, nicht nur im UI versteckt.
        if ($this->valueCount($attribute) > 0) {
            abort_unless($request->user()->role === 'super_admin', 403);
        }

        // Kein DB::transaction() hier: $attribute->delete() löst über
        // AttributeObserver::deleted() ggf. ein ALTER TABLE (Spalten-
        // Aufräumung, siehe AttributeColumnManager) aus - DDL committet in
        // MySQL immer implizit, eine umschließende Transaktion wäre an der
        // Stelle schon beendet und Laravels eigener Commit am Ende würde mit
        // "There is no active transaction" scheitern.

        // Werte aus allen Projekten dieses Mandanten entfernen, statt eine
        // tote Karteileiche im JSON zu hinterlassen.
        DB::table('projects')
            ->where('tenant_id', $attribute->tenant_id)
            ->whereNotNull('attributes')
            ->update(['attributes' => DB::raw("JSON_REMOVE(attributes, '$.\"{$attribute->key}\"')")]);

        $attribute->delete();

        return $this->redirectToSection($attribute->section);
    }

    /**
     * Ralf, 2026-09-11: Pulldown-Bearbeitung (Name + Optionen) läuft über ein
     * eigenes Overlay und wird "ganzheitlich" in EINEM Request gespeichert,
     * statt Name/Optionen einzeln über getrennte Endpunkte - dieser Endpunkt
     * ersetzt die vorherigen storeOption()/updateOption()/destroyOption().
     * $options ist eine vollständige Momentaufnahme (id + label je Zeile,
     * id leer = neue Option) - alles, was hier fehlt, aber noch in der DB
     * steht, gilt als vom Nutzer gelöscht.
     */
    public function updatePulldown(Request $request, Attribute $attribute): RedirectResponse
    {
        abort_unless($attribute->tenant_id === CurrentTenant::id(), 404);
        abort_if($attribute->system, 403);
        abort_unless($attribute->data_type === Attribute::DATA_TYPE_SELECT, 422);

        $label = trim((string) $request->string('label'));
        abort_if($label === '', 422);

        $submittedOptions = collect($request->array('options'))
            ->map(fn ($row) => [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'label' => trim((string) ($row['label'] ?? '')),
            ])
            ->filter(fn ($row) => $row['label'] !== '')
            ->values();

        DB::transaction(function () use ($attribute, $label, $submittedOptions) {
            $attribute->update(['label' => $label]);

            $existingOptions = $attribute->options()->get()->keyBy('id');
            $keptIds = $submittedOptions->pluck('id')->filter()->all();

            foreach ($existingOptions as $option) {
                if (! in_array($option->id, $keptIds, true)) {
                    $this->removeOptionValueFromProjects($attribute, $option);
                    $option->delete();
                }
            }

            foreach ($submittedOptions as $index => $row) {
                if ($row['id'] && $existingOptions->has($row['id'])) {
                    $existingOptions[$row['id']]->update(['label' => $row['label'], 'sort' => $index]);
                } else {
                    AttributeOption::query()->create([
                        'attribute_id' => $attribute->id,
                        'value' => $this->uniqueOptionValue($row['label'], $attribute->id),
                        'label' => $row['label'],
                        'sort' => $index,
                    ]);
                }
            }
        });

        return $this->redirectToSection($attribute->section);
    }

    /**
     * Entfernt den Wert einer gelöschten Option aus allen Projekten dieses
     * Mandanten, statt eine tote Karteileiche im attributes-JSON zu
     * hinterlassen (Werte referenzieren AttributeOption::value, nicht die
     * ID - eine umbenannte Option braucht das nicht, nur eine gelöschte).
     */
    private function removeOptionValueFromProjects(Attribute $attribute, AttributeOption $option): void
    {
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

    /**
     * Anzahl Projekte dieses Mandanten, die aktuell einen Wert für dieses
     * Attribut gesetzt haben - Grundlage für die Lösch-Sperre oben.
     */
    private function valueCount(Attribute $attribute): int
    {
        return DB::table('projects')
            ->where('tenant_id', $attribute->tenant_id)
            ->whereNotNull('attributes')
            ->whereRaw("JSON_EXTRACT(attributes, '$.\"{$attribute->key}\"') is not null")
            ->count();
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
