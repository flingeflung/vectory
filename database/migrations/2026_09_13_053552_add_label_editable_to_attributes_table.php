<?php

use App\Models\Attribute;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Korrektur der Migration 2026_09_11_090001: "Modell/System" wurde
     * dort zu einem normalen, löschbaren Zusatzfeld gemacht, weil nur
     * Zusatzfelder eine änderbare Bezeichnung hatten. Ralf, 2026-09-13:
     * "Es muss grundsätzlich ein festes Feld für die Produktbezeichnung
     * vorhanden sein" - Existenz muss garantiert sein (wie andere System-
     * Felder), nur die Caption soll pro Kunde frei bleiben. Fix: eigenes
     * label_editable-Flag statt des falschen system=false-Workarounds.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('label_editable')->default(false)->after('system');
        });

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $existing = DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'system_model')->first();

            if ($existing) {
                DB::table('attributes')->where('id', $existing->id)->update(['system' => true, 'label_editable' => true]);

                continue;
            }

            $nextSort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', Attribute::SECTION_STAMMDATEN)->max('sort');

            DB::table('attributes')->insert([
                'tenant_id' => $tenantId,
                'section' => Attribute::SECTION_STAMMDATEN,
                'system' => true,
                'label_editable' => true,
                'key' => 'system_model',
                'label' => 'Modell/System',
                'data_type' => Attribute::DATA_TYPE_TEXT,
                'sort' => $nextSort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('label_editable');
        });
    }
};
