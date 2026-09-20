<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\Market;
use App\Models\ProductGroup;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectTypeMain;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    public const DEFAULT_ACTIVE = ['title', 'status', 'attribute:initiator'];

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

        $fixed = [
            ['key' => 'source_pn', 'label' => __('PN'), 'type' => 'text'],
            ['key' => 'title', 'label' => __('Bezeichnung'), 'type' => 'text'],
            ['key' => 'status', 'label' => __('Status'), 'type' => 'multiselect', 'options' => $statusOptions],
            ['key' => 'project_year', 'label' => __('Projektjahr'), 'type' => 'multiselect', 'options' => self::projectYearOptions($tenantId)],
            ['key' => 'project_type', 'label' => __('Projekttyp/-art'), 'type' => 'grouped_multiselect', 'groups' => self::projectTypeGroups($tenantId)],
            ['key' => 'version', 'label' => __('Version'), 'type' => 'select', 'options' => self::versionOptions($tenantId)],
            // Ralf, 2026-09-20: alle Versionen eines Dokuments per Stamm-ID
            // zeigen (Eingabe auch mit Bindestrichen/Kleinschreibung, siehe
            // ProjectController::applyFilters()).
            ['key' => 'stamm_id', 'label' => __('Stamm-ID'), 'type' => 'text'],
            // Ralf, 2026-09-20: auch danach filtern können, ob überhaupt ein
            // Erstellungsstatus gesetzt ist ("gesetzt"/"nicht gesetzt").
            ['key' => 'creation_type', 'label' => __('Erstellungsstatus'), 'type' => 'select', 'options' => [
                '1' => __('Neuerstellung'),
                '2' => __('Änderung'),
                'gesetzt' => __('irgendeiner gesetzt'),
                'leer' => __('nicht gesetzt'),
            ]],
            ['key' => 'workflow_id', 'label' => __('Workflow'), 'type' => 'select', 'options' => self::workflowOptions($tenantId)],
            // Ralf, 2026-09-13: label_editable-System-Feld, aber eine echte
            // n:m-Produktverknüpfung statt Freitext - eigener fester
            // Eintrag statt über die generische attribute:-Schleife unten,
            // siehe gleiche Begründung in ProjectColumnCatalog.
            ['key' => 'system_model', 'label' => Attribute::query()->where('tenant_id', $tenantId)->where('key', 'system_model')->value('label') ?? __('Modell/System'), 'type' => 'text'],
            // Ralf, 2026-09-13: "Produktgruppe als Dropdown, Text bleibt für
            // Produktname/-nummer" - eigenes Kriterium zusätzlich zum
            // Freitext oben, NICHT zu verwechseln mit project_group_id
            // ("Meine Projektgruppen", eine ganz andere Sache).
            ['key' => 'product_group_id', 'label' => __('Produktgruppe'), 'type' => 'select', 'options' => self::productGroupOptions($tenantId)],
            // Ralf, 2026-09-13: "Meine Projektgruppen" - hauptsächlich über
            // den "Nur diese Gruppe anzeigen"-Link gesetzt (ProjectGroup-
            // Controller::showInOverview()), aber auch normal manuell
            // wählbar wie jeder andere Filter.
            ['key' => 'project_group_id', 'label' => __('Projektgruppe'), 'type' => 'select', 'options' => self::projectGroupOptions()],
            ['key' => 'project_person', 'label' => __('Projektbeteiligte Person'), 'type' => 'person_group', 'options' => self::projectPersonOptions($tenantId)],
            ['key' => 'verbund', 'label' => __('Verbund'), 'type' => 'select', 'options' => [
                'ja' => __('nur Verbundprojekte'),
                'nein' => __('keine Verbundprojekte'),
                'haupt' => __('nur Hauptprojekte'),
            ]],
            ['key' => 'remarks', 'label' => __('Bemerkungen'), 'type' => 'text'],
            ['key' => 'markets', 'label' => __('Märkte/Subsprachen'), 'type' => 'multiselect', 'columns' => 2, 'options' => self::marketOptions($tenantId)],
            ['key' => 'graphic_orders', 'label' => __('Grafikaufträge'), 'type' => 'select', 'options' => [
                'ohne' => __('nur ohne'),
                'mit' => __('nur mit'),
                'offene' => __('noch offene vorhanden'),
            ]],
            ['key' => 'favorite', 'label' => __('Favorit'), 'type' => 'select', 'options' => $boolOptions, 'no_placeholder' => true],
            ['key' => 'archived', 'label' => __('Archiviert'), 'type' => 'select', 'options' => $boolOptions, 'no_placeholder' => true],
            ['key' => 'start_date', 'label' => __('Start'), 'type' => 'date_range'],
            ['key' => 'end_date', 'label' => __('Ende'), 'type' => 'date_range'],
            ['key' => 'publication_date', 'label' => __('Publikationsdatum'), 'type' => 'date_range'],
        ];

        $attributes = Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->where('system', false)->orWhere('label_editable', true))
            ->where('key', '!=', 'system_model')
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
    /**
     * @return array<int, string>
     */
    private static function projectGroupOptions(): array
    {
        $user = auth()->user();

        return $user ? ProjectGroup::visibleTo($user)->orderBy('name')->pluck('name', 'project_groups.id')->all() : [];
    }

    /**
     * @return array<int, string>
     */
    private static function productGroupOptions(int $tenantId): array
    {
        return ProductGroup::query()->where('tenant_id', $tenantId)->orderBy('sort')->pluck('name', 'id')->all();
    }

    private static function projectPersonOptions(int $tenantId): array
    {
        // Collator('de_DE') statt SORT_NATURAL - sortiert Ä/Ö/Ü wie A/O/U
        // ein statt hinter Z (Ralf-Bug-Report, siehe MultichangeController::sortedFields()).
        $collator = new \Collator('de_DE');

        return DB::table('project_people as pp')
            ->join('people as p', 'p.id', '=', 'pp.person_id')
            ->join('function_groups as fg', 'fg.id', '=', 'pp.function_group_id')
            ->where('pp.tenant_id', $tenantId)
            ->distinct()
            ->get(['p.id', 'p.first_name', 'p.last_name', 'p.active', 'fg.id as group_id', 'fg.name as group_name'])
            ->groupBy('id')
            ->map(function ($entries, $id) use ($collator) {
                $person = $entries->first();

                return [
                    'id' => (int) $id,
                    'label' => trim($person->last_name.', '.$person->first_name, ', ').($person->active ? '' : ' [i]'),
                    'groups' => $entries->map(fn ($entry) => [
                        'id' => (int) $entry->group_id,
                        'label' => $entry->group_name,
                    ])->sort(fn ($a, $b) => $collator->compare($a['label'], $b['label']))->values()->all(),
                ];
            })
            ->sort(fn ($a, $b) => $collator->compare($a['label'], $b['label']))
            ->values()
            ->all();
    }

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
                    'value' => $sub->id,
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
                isset($value['from']) ? Carbon::parse($value['from'])->format('d.m.Y') : null,
                isset($value['to']) ? Carbon::parse($value['to'])->format('d.m.Y') : null,
            ])->filter()->implode(' – '),
            'person_group' => self::describePersonGroup($field, $value),
            default => (string) $value,
        };
    }

    private static function describePersonGroup(array $field, mixed $value): string
    {
        $person = collect($field['options'])->firstWhere('id', (int) ($value['person_id'] ?? 0));
        $group = collect($person['groups'] ?? [])->firstWhere('id', (int) ($value['function_group_id'] ?? 0));

        return ($person['label'] ?? (string) ($value['person_id'] ?? '')).($group ? ' ('.$group['label'].')' : '');
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
