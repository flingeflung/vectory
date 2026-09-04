<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\Market;
use App\Models\Project;
use App\Models\ProjectTypeMain;
use App\Models\User;
use App\Models\Workflow;

/**
 * Verfügbare Kriterien für den Projektfilter: feste Felder + variable
 * Attribute aus dem Katalog. Die *aktive Auswahl* (welche Kriterien der
 * Nutzer gerade eingeblendet hat) wird automatisch im selben Filterset
 * gespeichert wie der Anzeigefilter (config-Key "filter_fields") – kein
 * manuelles Speichern nötig, merkt sich einfach den letzten Stand.
 */
class ProjectFilterCatalog
{
    /**
     * @var list<string>
     */
    public const DEFAULT_ACTIVE = ['title', 'status', 'initiator'];

    /**
     * @return list<array{key: string, label: string, type: string, options?: array}>
     */
    public static function available(int $tenantId): array
    {
        $statusOptions = [
            ['value' => 0, 'label' => __('Geplant')],
            ['value' => 1, 'label' => __('In Bearbeitung')],
            ['value' => 2, 'label' => __('Beendet')],
            ['value' => 3, 'label' => __('Verworfen')],
        ];
        $boolOptions = ['1' => __('Ja'), '0' => __('Nein')];
        $localizationOptions = ['1' => __('Ja'), '0' => __('Nein'), 'null' => __('nicht zugewiesen')];

        $fixed = [
            ['key' => 'source_pn', 'label' => __('PN'), 'type' => 'text'],
            ['key' => 'title', 'label' => __('Bezeichnung'), 'type' => 'text'],
            ['key' => 'status', 'label' => __('Status'), 'type' => 'multiselect', 'options' => $statusOptions],
            ['key' => 'project_year', 'label' => __('Projektjahr'), 'type' => 'multiselect', 'options' => self::projectYearOptions($tenantId)],
            ['key' => 'project_type', 'label' => __('Projekttyp/-art'), 'type' => 'grouped_multiselect', 'groups' => self::projectTypeGroups($tenantId)],
            ['key' => 'version', 'label' => __('Version'), 'type' => 'select', 'options' => self::versionOptions($tenantId)],
            ['key' => 'workflow_id', 'label' => __('Workflow'), 'type' => 'select', 'options' => self::workflowOptions($tenantId)],
            ['key' => 'construction_year', 'label' => __('Baujahr'), 'type' => 'text'],
            ['key' => 'initiator', 'label' => __('Initiator'), 'type' => 'text'],
            ['key' => 'system_model', 'label' => __('Modell/System'), 'type' => 'text'],
            ['key' => 'remarks', 'label' => __('Bemerkungen'), 'type' => 'text'],
            ['key' => 'markets', 'label' => __('Märkte/Subsprachen'), 'type' => 'multiselect', 'columns' => 2, 'options' => self::marketOptions($tenantId)],
            ['key' => 'graphic_orders', 'label' => __('Grafikaufträge'), 'type' => 'select', 'options' => [
                'ohne' => __('nur ohne'),
                'mit' => __('nur mit'),
                'offene' => __('noch offene vorhanden'),
            ]],
            ['key' => 'favorite', 'label' => __('Favorit'), 'type' => 'select', 'options' => $boolOptions, 'no_placeholder' => true],
            ['key' => 'archived', 'label' => __('Archiviert'), 'type' => 'select', 'options' => $boolOptions, 'no_placeholder' => true],
            ['key' => 'localization', 'label' => __('Übersetzung/Lokalisierung notwendig?'), 'type' => 'select', 'options' => $localizationOptions],
            ['key' => 'start_date', 'label' => __('Start'), 'type' => 'date_range'],
            ['key' => 'end_date', 'label' => __('Ende'), 'type' => 'date_range'],
            ['key' => 'publication_date', 'label' => __('Publikationsdatum'), 'type' => 'date_range'],
        ];

        $attributes = Attribute::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => 'attribute:'.$attribute->key,
                'label' => $attribute->label,
                'type' => 'text',
            ])
            ->all();

        return [...$fixed, ...$attributes];
    }

    /**
     * Operator+Zahl-Kombinationen (wie in Vietto), aber mit dynamischer
     * Obergrenze statt fest 30 – bis zur höchsten tatsächlich vorkommenden
     * Versionsnummer dieses Mandanten.
     *
     * @return array<string, string>
     */
    private static function versionOptions(int $tenantId): array
    {
        $maxVersion = (int) (Project::query()->where('tenant_id', $tenantId)->max('version') ?? 1);

        $options = [];
        foreach (range(1, $maxVersion) as $number) {
            foreach (['<=', '<', '=', '>=', '>'] as $operator) {
                $options["{$operator} {$number}"] = "{$operator} {$number}";
            }
        }

        return $options;
    }

    /**
     * Alle Workflows (auch inaktive/ersetzte, wie in Vietto) - inaktive
     * werden mit " [i]" markiert und im Filterformular ausgegraut
     * (Vietto: class="selwfinactive").
     *
     * @return array<int, array{label: string, inactive: bool}>
     */
    private static function workflowOptions(int $tenantId): array
    {
        return Workflow::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Workflow $workflow) => [
                $workflow->id => [
                    'label' => $workflow->name.(! $workflow->active ? ' [i]' : ''),
                    'inactive' => ! $workflow->active,
                ],
            ])
            ->all();
    }

    /**
     * Projektjahr steckt bei Vietto nicht in einer eigenen Spalte, sondern in
     * den ersten beiden Ziffern der PN (z.B. "260100" -> 2026). Die Liste der
     * wählbaren Jahre wird daher aus den tatsächlich vorhandenen PN-Präfixen
     * abgeleitet statt fest verdrahtet.
     *
     * @return list<array{value: int, label: string}>
     */
    private static function projectYearOptions(int $tenantId): array
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('DISTINCT SUBSTRING(source_pn, 1, 2) as yy')
            ->pluck('yy')
            ->map(fn (string $yy) => 2000 + (int) $yy)
            ->sortDesc()
            ->values()
            ->map(fn (int $year) => ['value' => $year, 'label' => (string) $year])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private static function marketOptions(int $tenantId): array
    {
        return Market::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')
            ->get()
            ->map(fn (Market $market) => ['value' => $market->id, 'label' => $market->label()])
            ->all();
    }

    /**
     * @return list<array{label: string, options: list<array{value: int, label: string}>}>
     */
    private static function projectTypeGroups(int $tenantId): array
    {
        return ProjectTypeMain::query()
            ->where('tenant_id', $tenantId)
            ->with('subs')
            ->orderBy('sort')
            ->get()
            ->map(fn (ProjectTypeMain $main) => [
                'label' => $main->name,
                'options' => $main->subs->map(fn ($sub) => [
                    'value' => $sub->legacy_id,
                    'label' => $sub->name,
                ])->all(),
            ])
            ->all();
    }

    /**
     * Aktive Filter als lesbare "Feld = Wert"-Häppchen für die Trefferzeile
     * über der Tabelle (Klartext statt Rohwerte/IDs).
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public static function describeFilters(array $filters, int $tenantId): array
    {
        $fields = collect(self::available($tenantId))->keyBy('key');
        $chips = [];

        foreach ($filters as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            $chips[] = ['key' => $key, 'label' => $field['label'], 'value' => self::describeValue($field, $value)];
        }

        return $chips;
    }

    private static function describeValue(array $field, mixed $value): string
    {
        return match ($field['type']) {
            'select' => is_array($field['options'][$value] ?? null)
                ? $field['options'][$value]['label']
                : (string) ($field['options'][$value] ?? $value),
            'multiselect' => collect($field['options'])
                ->whereIn('value', (array) $value)
                ->pluck('label')
                ->implode(', '),
            'grouped_multiselect' => collect($field['groups'])
                ->flatMap(fn (array $group) => $group['options'])
                ->whereIn('value', (array) $value)
                ->pluck('label')
                ->implode(', '),
            'date_range' => collect([
                isset($value['from']) ? \Illuminate\Support\Carbon::parse($value['from'])->format('d.m.Y') : null,
                isset($value['to']) ? \Illuminate\Support\Carbon::parse($value['to'])->format('d.m.Y') : null,
            ])->filter()->implode(' – '),
            default => (string) $value,
        };
    }

    /**
     * @return list<string>
     */
    public static function activeFieldsFor(User $user): array
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);

        return $set->config['filter_fields'] ?? self::DEFAULT_ACTIVE;
    }

    public static function persistActiveFields(User $user, array $fieldKeys): void
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);
        $config = $set->config;
        $config['filter_fields'] = array_values(array_unique($fieldKeys));
        $set->update(['config' => $config]);
    }

    /**
     * Zuletzt angewandte Filterwerte (nicht nur die Feldauswahl) - damit ein
     * Wechsel weg von /projekte (z.B. über die Sidebar) und zurück den
     * Filter nicht kommentarlos verliert. Wird nur beim tatsächlichen
     * Abschicken des Projektfilter-Formulars aktualisiert (siehe
     * projektfilter_submitted-Marker im Controller), nicht bei jedem
     * Seitenaufruf.
     *
     * @return array<string, mixed>
     */
    public static function persistedFiltersFor(User $user): array
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);

        return $set->config['filter_values'] ?? [];
    }

    public static function persistFilterValues(User $user, array $filters): void
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);
        $config = $set->config;
        $config['filter_values'] = $filters;
        $set->update(['config' => $config]);
    }
}
