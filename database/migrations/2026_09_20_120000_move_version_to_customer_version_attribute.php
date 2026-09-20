<?php

use App\Models\Attribute as AttributeModel;
use App\Services\AttributeColumnManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenversion wird ein normales Zusatzfeld (Ralf, 2026-09-20): nicht jeder Kunde
 * braucht sie (manchem genügt die Stamm-Version), jeder Kunde benennt/gestaltet sie
 * selbst. Das feste Systemfeld "Version" wird zur schreibgeschützten "Stamm-Version"
 * (Position in der Versionskette).
 *
 * - attributes.increments_on_new_version: Markierung am Textfeld "beim Aufversionieren
 *   hochzählen" (letzte Zahl im Text +1).
 * - je Mandant ein Zusatzfeld "Kundenversion" (Stammdaten, Text, hochzählend), direkt
 *   hinter dem Systemfeld einsortiert; die Werte aus projects.version wandern in das
 *   attributes-JSON.
 * - gespeicherte Anzeigefilter/Filtersets/Spaltenbreiten, die noch "version" nennen,
 *   werden auf das neue Feld umgehängt; Kopiervorlagen mit dem alten Haken bekommen das
 *   neue Feld ebenfalls angehakt.
 * - projects.version und projects.version_sort entfallen.
 */
return new class extends Migration
{
    private const KEY = 'kundenversion';

    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('increments_on_new_version')->default(false)->after('applies_to_all_types');
        });

        $columns = app(AttributeColumnManager::class);
        $newAttributeByTenant = [];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $versionRow = DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'version')->where('system', true)->first();
            $sort = $versionRow ? (int) $versionRow->sort + 1 : 0;

            if ($versionRow) {
                DB::table('attributes')->where('tenant_id', $tenantId)->where('section', $versionRow->section)
                    ->where('sort', '>=', $sort)->increment('sort');
            }

            $id = DB::table('attributes')->insertGetId([
                'tenant_id' => $tenantId,
                'section' => 'stammdaten',
                'system' => false,
                'label_editable' => false,
                'applies_to_all_types' => true,
                'increments_on_new_version' => true,
                'key' => self::KEY,
                'label' => 'Kundenversion',
                'data_type' => 'text',
                'multiple' => false,
                'max_length' => 50,
                'sort' => $sort,
                'available_in_mail_templates' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $newAttributeByTenant[$tenantId] = $id;

            if ($versionRow) {
                DB::table('attributes')->where('id', $versionRow->id)->update(['label' => 'Stamm-Version']);
            }
        }

        $columns->ensureColumn(new AttributeModel(['key' => self::KEY, 'data_type' => 'text']));

        // Werte umziehen.
        DB::table('projects')->whereNotNull('version')->where('version', '<>', '')
            ->update(['attributes' => DB::raw("JSON_SET(COALESCE(attributes, JSON_OBJECT()), '$.".self::KEY."', version)")]);

        // Kopiervorlagen: war "Version" angehakt, ist es nun auch "Kundenversion".
        foreach (DB::table('copy_template_attribute')->join('attributes', 'attributes.id', '=', 'copy_template_attribute.attribute_id')
            ->where('attributes.key', 'version')->where('attributes.system', true)
            ->get(['copy_template_attribute.copy_template_id', 'attributes.tenant_id']) as $row) {
            if (isset($newAttributeByTenant[$row->tenant_id])) {
                DB::table('copy_template_attribute')->updateOrInsert([
                    'copy_template_id' => $row->copy_template_id,
                    'attribute_id' => $newAttributeByTenant[$row->tenant_id],
                ], []);
            }
        }

        // Gespeicherte Anzeigefilter/Filtersets.
        foreach (DB::table('display_filter_sets')->get(['id', 'config']) as $set) {
            $config = json_decode($set->config, true);
            if (! is_array($config)) {
                continue;
            }
            $changed = false;
            foreach ($config['columns'] ?? [] as $i => $column) {
                if (($column['key'] ?? null) === 'version') {
                    $config['columns'][$i]['key'] = 'attribute:'.self::KEY;
                    $changed = true;
                }
            }
            foreach ($config['filter_fields'] ?? [] as $i => $key) {
                if ($key === 'version') {
                    $config['filter_fields'][$i] = 'attribute:'.self::KEY;
                    $changed = true;
                }
            }
            if (isset($config['filter_values']['version'])) {
                unset($config['filter_values']['version']);
                $changed = true;
            }
            if ($changed) {
                DB::table('display_filter_sets')->where('id', $set->id)->update(['config' => json_encode($config)]);
            }
        }

        // Spaltenbreiten (persönlich je Tabelle).
        foreach (DB::table('user_table_preferences')->get(['id', 'column_widths']) as $pref) {
            $widths = json_decode($pref->column_widths, true);
            if (is_array($widths) && array_key_exists('version', $widths)) {
                $widths['attribute:'.self::KEY] = $widths['version'];
                unset($widths['version']);
                DB::table('user_table_preferences')->where('id', $pref->id)->update(['column_widths' => json_encode($widths)]);
            }
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['version_sort']);
            $table->dropColumn(['version', 'version_sort']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('version', 50)->nullable()->after('project_template_id');
            $table->unsignedBigInteger('version_sort')->nullable()->after('version');
            $table->index('version_sort');
        });

        DB::table('projects')->whereNotNull('attributes')
            ->update(['version' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.".self::KEY."'))")]);

        DB::table('attributes')->where('key', 'version')->where('system', true)->where('label', 'Stamm-Version')->update(['label' => 'Kundenversion']);
        DB::table('attributes')->where('key', self::KEY)->where('system', false)->delete();

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('increments_on_new_version');
        });
    }
};
