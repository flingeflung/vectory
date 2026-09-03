<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\DisplayFilterSet;
use App\Models\User;

/**
 * Verfügbare Spalten für die Projektübersicht: feste Felder (Code-seitig,
 * über __() übersetzt) + variable Attribute aus dem mandantenscoped Katalog
 * (Label kommt dort aus attributes.label). "source_pn" ist bewusst nicht
 * enthalten – die Spalte ist immer sichtbar, immer zuerst, nicht abwählbar
 * (wie in Vietto).
 */
class ProjectColumnCatalog
{
    /**
     * Standard-Kurztextlänge (Zeichen), solange der Nutzer nichts Eigenes einträgt.
     */
    public const DEFAULT_SHORT_LENGTH = 50;

    /**
     * @return list<array{key: string, label: string, long_text: bool}>
     */
    public static function available(int $tenantId): array
    {
        $fixed = [
            ['key' => 'title', 'label' => __('Bezeichnung'), 'long_text' => true],
            ['key' => 'status', 'label' => __('Status'), 'long_text' => false],
            ['key' => 'project_type', 'label' => __('Projekttyp/-art'), 'long_text' => false, 'type_icon' => true],
            ['key' => 'workflow', 'label' => __('Workflow'), 'long_text' => false],
            ['key' => 'version', 'label' => __('Version'), 'long_text' => false],
            ['key' => 'start_date', 'label' => __('Start'), 'long_text' => false],
            ['key' => 'end_date', 'label' => __('Ende'), 'long_text' => false],
            ['key' => 'system_model', 'label' => __('Modell/System'), 'long_text' => true],
            ['key' => 'initiator', 'label' => __('Initiator'), 'long_text' => true],
            ['key' => 'remarks', 'label' => __('Bemerkungen'), 'long_text' => true],
            ['key' => 'markets', 'label' => __('Märkte/Subsprachen'), 'long_text' => false, 'icons' => true],
            ['key' => 'graphic_orders_summary', 'label' => __('Illustration'), 'long_text' => false, 'graphic_summary' => true],
        ];

        $attributes = Attribute::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => 'attribute:'.$attribute->key,
                'label' => $attribute->label,
                'long_text' => false,
            ])
            ->all();

        return [...$fixed, ...$attributes];
    }

    /**
     * Standardbelegung, solange der Nutzer noch kein eigenes Filterset hat
     * (entspricht der bisherigen festen Tabellenansicht).
     *
     * @return list<array{key: string, visible: bool, long_text: bool}>
     */
    public static function defaultConfig(): array
    {
        $visibleByDefault = ['title', 'attribute:format', 'version', 'status', 'start_date', 'end_date'];

        return array_map(
            fn (string $key) => ['key' => $key, 'visible' => in_array($key, $visibleByDefault, true), 'long_text' => false],
            ['title', 'attribute:format', 'version', 'status', 'project_type', 'start_date', 'end_date', 'system_model', 'initiator', 'remarks', 'attribute:material_number', 'attribute:farbe', 'attribute:heftung', 'attribute:erstauflage']
        );
    }

    /**
     * Aktives Filterset des Users (oder Standard), abgeglichen mit dem
     * tatsächlich verfügbaren Katalog (neue Attribute werden ergänzt,
     * gelöschte entfernt).
     *
     * @return list<array{key: string, label: string, long_text: bool, visible: bool}>
     */
    public static function effectiveFor(User $user): array
    {
        $activeSet = self::ensureDefaultSetFor($user);

        $columns = $activeSet->config['columns'] ?? self::defaultConfig();
        $configByKey = collect($columns)->keyBy('key');

        return collect(self::available($user->tenant_id))
            ->map(function (array $column) use ($configByKey) {
                $saved = $configByKey->get($column['key']);

                return [
                    'key' => $column['key'],
                    'label' => $column['label'],
                    'long_text' => $column['long_text'],
                    'icons' => $column['icons'] ?? false,
                    'type_icon' => $column['type_icon'] ?? false,
                    'graphic_summary' => $column['graphic_summary'] ?? false,
                    'visible' => $saved['visible'] ?? false,
                    'show_long_text' => $saved['long_text'] ?? false,
                    'short_length' => $saved['short_length'] ?? self::DEFAULT_SHORT_LENGTH,
                ];
            })
            ->sortBy(fn (array $column) => $configByKey->keys()->search($column['key']) ?? 999)
            ->values()
            ->all();
    }

    /**
     * Legt für neue Nutzer einmalig ein aktives "Standard"-Filterset an.
     */
    public static function ensureDefaultSetFor(User $user): DisplayFilterSet
    {
        $activeSet = DisplayFilterSet::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($activeSet) {
            return $activeSet;
        }

        return DisplayFilterSet::firstOrCreate(
            ['user_id' => $user->id, 'name' => __('Standard')],
            ['config' => ['columns' => self::defaultConfig()], 'is_active' => true]
        );
    }
}
