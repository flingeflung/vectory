<?php

namespace App\Services\PresetCopy;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProjectTypeSub;
use App\Services\AttributeColumnManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Projektattribute: Zusatzfelder (Identität = technischer Schlüssel) samt Auswahloptionen und Zuordnung
 * zu Projektarten, außerdem die Geltung der einschränkbaren Systemfelder (z.B. "Modelle gelten nicht für
 * Konzeption"). Projektarten werden über Hauptart + Name ins Ziel übersetzt; fehlt eine dort, wird die
 * Zuordnung ausgelassen und im Bericht genannt (vorher die Projektarten übernehmen).
 *
 * Überschreiben ändert nur Eigenschaften: Werte in Projekten bleiben, vorhandene Optionen bleiben (nur
 * ergänzt/umbenannt), und bei abweichendem Feldtyp wird nichts angefasst. Die technische Spalte in der
 * Projekte-Tabelle entsteht erst nach dem Commit (Spalten-Änderungen beenden Transaktionen).
 */
class AttributesArea implements PresetArea
{
    /** Eigenschaften, die beim Anlegen/Überschreiben übernommen werden. */
    private const COPIED = [
        'section', 'label', 'data_type', 'multiple', 'available_in_mail_templates', 'applies_to_all_types',
        'increments_on_new_version', 'default_value', 'required', 'log_changes', 'unit', 'help_text',
        'number_min', 'number_max', 'number_decimals', 'max_length',
    ];

    public function __construct(private readonly ?AttributeColumnManager $columns = null) {}

    public function key(): string
    {
        return 'attributes';
    }

    public function label(): string
    {
        return __('Projektattribute');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->attributes($targetTenantId)->keyBy('key');
        $items = [];

        foreach ($this->attributes($sourceTenantId)->where('system', false) as $attribute) {
            $items[] = [
                'key' => 'a:'.$attribute->key,
                'label' => $attribute->label,
                'parent' => null,
                'conflict' => $existing->has($attribute->key),
                'renamable' => true,
                'note' => $this->sectionLabel($attribute->section).' · '.$this->typeLabel($attribute)
                    .($attribute->applies_to_all_types ? '' : ' · '.__('nur bestimmte Projektarten')),
            ];
        }

        foreach ($this->attributes($sourceTenantId)->where('system', true)->whereIn('key', Attribute::RESTRICTABLE_SYSTEM_FIELDS) as $attribute) {
            $items[] = [
                'key' => 's:'.$attribute->key,
                'label' => __('Systemfeld „:name“: Geltung für Projektarten', ['name' => $attribute->label]),
                'parent' => null,
                'conflict' => true,
                'renamable' => false,
                'note' => $attribute->applies_to_all_types ? __('gilt für alle') : __('nur bestimmte Projektarten'),
            ];
        }

        return $items;
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $typeMap = $this->projectTypeMap($sourceTenantId, $targetTenantId);
        $targetAttributes = $this->attributes($targetTenantId);

        foreach ($this->attributes($sourceTenantId) as $source) {
            $system = $source->system;
            $choice = $choices[($system ? 's:' : 'a:').$source->key] ?? null;
            if ($choice === null || ($system && ! in_array($source->key, Attribute::RESTRICTABLE_SYSTEM_FIELDS, true))) {
                continue;
            }
            $existing = $targetAttributes->firstWhere('key', $source->key);

            if ($system) {
                $this->applySystem($source, $existing, $choice, $typeMap, $report);

                continue;
            }

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->label, __('übersprungen (gibt es schon)'));
            } elseif ($existing && $choice === 'overwrite') {
                $this->overwrite($source, $existing, $typeMap, $report);
            } else {
                $this->create($source, $targetTenantId, $existing !== null, $targetAttributes, $typeMap, $report);
                $targetAttributes = $this->attributes($targetTenantId);
            }
        }
    }

    private function create(Attribute $source, int $targetTenantId, bool $rename, Collection $targetAttributes, array $typeMap, PresetReport $report): void
    {
        $key = $source->key;
        $label = $source->label;
        if ($rename) {
            $taken = $targetAttributes->pluck('key')->all();
            $labels = $targetAttributes->pluck('label')->all();
            for ($i = 1; in_array($key = $source->key.'_kopie'.($i > 1 ? $i : ''), $taken, true); $i++);
            for ($i = 1; in_array($label = $source->label.' ('.__('Kopie').($i > 1 ? ' '.$i : '').')', $labels, true); $i++);
        }

        $new = Attribute::query()->withoutGlobalScope('tenant')->create($source->only(self::COPIED) + [
            'tenant_id' => $targetTenantId,
            'key' => $key,
            'system' => false,
            'sort' => 1 + (int) $targetAttributes->where('section', $source->section)->max('sort'),
        ]);
        $new->update(['label' => $label]);

        $this->syncOptions($source, $new);
        $missing = $this->syncTypes($source, $new, $typeMap);
        $report->defer(fn () => $this->columnManager()->ensureColumn($new));
        $report->add($this->label(), $source->label, ($rename ? __('angelegt als „:name“', ['name' => $label]) : __('angelegt')).$this->missingNote($missing));
    }

    private function overwrite(Attribute $source, Attribute $target, array $typeMap, PresetReport $report): void
    {
        if ($source->data_type !== $target->data_type || (bool) $source->multiple !== (bool) $target->multiple) {
            $report->add($this->label(), $source->label, __('übersprungen (anderer Feldtyp im Ziel - Werte in Projekten wären gefährdet)'));

            return;
        }

        $target->update($source->only(self::COPIED));
        $this->syncOptions($source, $target);
        $missing = $this->syncTypes($source, $target, $typeMap);
        $report->defer(fn () => $this->columnManager()->ensureColumn($target));
        $report->add($this->label(), $source->label, __('überschrieben').$this->missingNote($missing));
    }

    private function applySystem(Attribute $source, ?Attribute $target, string $choice, array $typeMap, PresetReport $report): void
    {
        $name = __('Systemfeld „:name“: Geltung', ['name' => $source->label]);
        if (! $target || $choice !== 'overwrite') {
            $report->add($this->label(), $name, __('übersprungen'));

            return;
        }

        $target->update(['applies_to_all_types' => $source->applies_to_all_types]);
        $missing = $this->syncTypes($source, $target, $typeMap);
        $report->add($this->label(), $name, __('überschrieben').$this->missingNote($missing));
    }

    /** Optionen: vorhandene bekommen Beschriftung/Reihenfolge der Quelle, fehlende werden ergänzt, zusätzliche bleiben. */
    private function syncOptions(Attribute $source, Attribute $target): void
    {
        $existing = AttributeOption::query()->where('attribute_id', $target->id)->get()->keyBy('value');
        foreach ($source->options as $option) {
            if ($row = $existing->get($option->value)) {
                $row->update(['label' => $option->label, 'sort' => $option->sort]);
            } else {
                AttributeOption::query()->create(['attribute_id' => $target->id, 'value' => $option->value, 'label' => $option->label, 'sort' => $option->sort]);
            }
        }
    }

    /**
     * @param  array<int, int>  $typeMap  Quell-Unterart-ID => Ziel-Unterart-ID
     * @return list<string> Namen der Projektarten, die im Ziel fehlen
     */
    private function syncTypes(Attribute $source, Attribute $target, array $typeMap): array
    {
        DB::table('attribute_project_type')->where('attribute_id', $target->id)->delete();
        if ($source->applies_to_all_types) {
            return [];
        }

        $missing = [];
        foreach (DB::table('attribute_project_type')->where('attribute_id', $source->id)->pluck('project_type_sub_id') as $subId) {
            if (isset($typeMap[$subId])) {
                DB::table('attribute_project_type')->insert([
                    'attribute_id' => $target->id, 'project_type_sub_id' => $typeMap[$subId], 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $missing[] = $typeMap['names'][$subId] ?? (string) $subId;
            }
        }

        return $missing;
    }

    private function missingNote(array $missing): string
    {
        return $missing === [] ? '' : ' – '.__('Zuordnung zu fehlenden Projektarten ausgelassen: :names (zuerst die Projektarten übernehmen)', ['names' => implode(', ', $missing)]);
    }

    /**
     * Quell-Unterart-ID => Ziel-Unterart-ID (Hauptart + Name); unter 'names' stehen die Namen der Quell-Unterarten,
     * damit fehlende im Bericht genannt werden können.
     *
     * @return array<int|string, mixed>
     */
    private function projectTypeMap(int $sourceTenantId, int $targetTenantId): array
    {
        $load = fn (int $tenantId) => ProjectTypeSub::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->with(['main' => fn ($q) => $q->withoutGlobalScope('tenant')])->get();
        $target = $load($targetTenantId)->keyBy(fn ($sub) => ($sub->main?->name).'|'.$sub->name);
        $map = ['names' => []];

        foreach ($load($sourceTenantId) as $sub) {
            $identity = ($sub->main?->name).'|'.$sub->name;
            $map['names'][$sub->id] = $sub->main?->name.' › '.$sub->name;
            if ($found = $target->get($identity)) {
                $map[$sub->id] = $found->id;
            }
        }

        return $map;
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            Attribute::SECTION_STAMMDATEN => __('Stammdaten'),
            Attribute::SECTION_ABLAUFDATEN => __('Ablaufdaten'),
            default => __('Typspezifisch'),
        };
    }

    private function typeLabel(Attribute $attribute): string
    {
        return match ($attribute->data_type) {
            Attribute::DATA_TYPE_TEXT => __('Text'),
            Attribute::DATA_TYPE_TEXTAREA => __('Mehrzeiliger Text'),
            Attribute::DATA_TYPE_NUMBER => __('Zahl'),
            Attribute::DATA_TYPE_DATE => __('Datum'),
            Attribute::DATA_TYPE_BOOLEAN => __('Ja/Nein'),
            default => $attribute->multiple ? __('Mehrfachauswahl') : __('Auswahl'),
        };
    }

    private function columnManager(): AttributeColumnManager
    {
        return $this->columns ?? app(AttributeColumnManager::class);
    }

    /** @return Collection<int, Attribute> */
    private function attributes(int $tenantId): Collection
    {
        return Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->with('options')->orderBy('section')->orderBy('sort')->orderBy('id')->get();
    }
}
