<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-10-03: Die Merkmale eines Aufwandsprofils (Produktkomplexität, Entwicklungsstand
     * usw.) sind auf Anleitungen ausgerichtet und für Verwaltungsprojekte u. Ä. unpassend. Sie sind
     * jetzt pro Schablone optional (Schalter use_characteristics); abgewählte Werte bleiben erhalten,
     * werden aber nicht mehr angezeigt oder angewendet. Bestehende Schablonen behalten ihre Merkmale
     * (Schalter an), neue starten ohne.
     */
    private const CHARACTERISTIC_COLUMNS = [
        'reusable_content_share', 'languages_count', 'product_maturity', 'product_change_delays',
        'contact_availability', 'localizer_availability', 'software_share', 'product_complexity',
        'print_variants_count', 'images_count',
    ];

    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->boolean('use_characteristics')->default(false)->after('remarks');
        });

        DB::table('project_templates')->update(['use_characteristics' => true]);

        Schema::table('project_templates', function (Blueprint $table) {
            foreach (self::CHARACTERISTIC_COLUMNS as $column) {
                $table->unsignedTinyInteger($column)->nullable()->change();
            }
        });
    }

    /**
     * Nicht verlustfrei umkehrbar: Schablonen ohne Merkmale hätten beim Zurückstellen auf NOT NULL
     * keinen Wert; sie bekommen dafür die jeweils erste Stufe (1).
     */
    public function down(): void
    {
        foreach (self::CHARACTERISTIC_COLUMNS as $column) {
            DB::table('project_templates')->whereNull($column)->update([$column => 1]);
        }

        Schema::table('project_templates', function (Blueprint $table) {
            foreach (self::CHARACTERISTIC_COLUMNS as $column) {
                $table->unsignedTinyInteger($column)->nullable(false)->change();
            }
            $table->dropColumn('use_characteristics');
        });
    }
};
