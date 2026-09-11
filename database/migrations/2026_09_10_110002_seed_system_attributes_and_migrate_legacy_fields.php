<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-10: "frei mischbar mit Zusatzfeldern" - die bisher fest
     * verdrahteten Felder bekommen eigene Attribute-Zeilen (system=true, nur
     * die Reihenfolge änderbar), damit sie einen echten sort-Wert tragen und
     * sich mit Zusatzfeldern mischen lassen (siehe Attribute::SYSTEM_FIELDS,
     * das hier bewusst dupliziert statt importiert wird - Migrationen bleiben
     * unabhängig vom aktuellen Modellstand).
     *
     * Baujahr/Initiator/Übersetzung-Lokalisierung sind bewusst NICHT
     * system=true, sondern wandern als normale (löschbare/umbenennbare)
     * Zusatzfelder raus ("du kannst ja die drei genannten aus den fest
     * vorhandenen auslagern und unter die Zusatzfelder verschieben") -
     * ihre bisherigen Spaltenwerte werden einmalig ins attributes-JSON
     * übernommen, die alten Spalten bleiben unangetastet stehen (keine
     * andere Stelle liest sie danach noch, siehe ProjectController).
     */
    private const SYSTEM_FIELDS = [
        'stammdaten' => [
            'title' => 'Bezeichnung',
            'system_model' => 'Modell/System',
            'project_type' => 'Projektkategorie/-art',
            'version' => 'Version',
            'status' => 'Status',
            'start_date' => 'Start',
            'end_date' => 'Ende',
            'markets' => 'Markt',
            'remarks' => 'Bemerkungen',
        ],
        'ablaufdaten' => [
            'workflow_id' => 'Workflow',
            'publication_date' => 'Publikation',
            'project_people' => 'Projektbeteiligte Personen',
            'archived' => 'Archiviert',
        ],
    ];

    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            foreach (self::SYSTEM_FIELDS as $section => $fields) {
                // Vorhandene Zeilen dieses Bereichs nach hinten schieben, damit
                // die System-Felder wie bisher optisch zuerst erscheinen
                // (entspricht der alten festen Anzeigereihenfolge) - Ralf kann
                // danach frei per Drag&Drop umsortieren.
                DB::table('attributes')->where('tenant_id', $tenantId)->where('section', $section)
                    ->increment('sort', count($fields));

                $sort = 0;
                foreach ($fields as $key => $label) {
                    DB::table('attributes')->insert([
                        'tenant_id' => $tenantId,
                        'section' => $section,
                        'system' => true,
                        'key' => $key,
                        'label' => $label,
                        'data_type' => 'text',
                        'sort' => $sort,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sort++;
                }
            }

            $this->migrateLegacyField($tenantId, 'construction_year', 'Baujahr', 'text');
            $this->migrateLegacyField($tenantId, 'initiator', 'Initiator', 'text');
            $this->migrateLegacyField($tenantId, 'localization', 'Übersetzung/Lokalisierung notwendig?', 'select');
        }

        $this->addGeneratedColumn('attributes_construction_year', 'construction_year');
        $this->addGeneratedColumn('attributes_initiator', 'initiator');
        $this->addGeneratedColumn('attributes_localization', 'localization');
    }

    private function migrateLegacyField(int $tenantId, string $key, string $label, string $dataType): void
    {
        $nextSort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'stammdaten')->max('sort');

        $attributeId = DB::table('attributes')->insertGetId([
            'tenant_id' => $tenantId,
            'section' => 'stammdaten',
            'system' => false,
            'key' => $key,
            'label' => $label,
            'data_type' => $dataType,
            'sort' => $nextSort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($dataType === 'select') {
            DB::table('attribute_options')->insert([
                ['attribute_id' => $attributeId, 'value' => 'ja', 'label' => 'Ja', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['attribute_id' => $attributeId, 'value' => 'nein', 'label' => 'Nein', 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $projects = DB::table('projects')->where('tenant_id', $tenantId)->whereNotNull($key)->select('id', 'attributes', $key)->get();
        foreach ($projects as $project) {
            $values = json_decode($project->attributes ?? '{}', true) ?? [];
            $raw = $project->{$key};
            $values[$key] = $dataType === 'select' ? (((int) $raw) === 1 ? 'ja' : 'nein') : $raw;
            DB::table('projects')->where('id', $project->id)->update(['attributes' => json_encode($values)]);
        }
    }

    private function addGeneratedColumn(string $column, string $key): void
    {
        if (Schema::hasColumn('projects', $column)) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) use ($column, $key) {
            $table->string($column, 191)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.{$key}'))")
                ->nullable()
                ->index();
        });
    }

    public function down(): void
    {
        foreach (['attributes_construction_year', 'attributes_initiator', 'attributes_localization'] as $column) {
            if (Schema::hasColumn('projects', $column)) {
                Schema::table('projects', function (Blueprint $table) use ($column) {
                    $table->dropIndex("projects_{$column}_index");
                    $table->dropColumn($column);
                });
            }
        }

        $ids = DB::table('attributes')->whereIn('key', ['construction_year', 'initiator', 'localization'])
            ->orWhere('system', true)->pluck('id');
        DB::table('attribute_options')->whereIn('attribute_id', $ids)->delete();
        DB::table('attributes')->whereIn('id', $ids)->delete();
    }
};
