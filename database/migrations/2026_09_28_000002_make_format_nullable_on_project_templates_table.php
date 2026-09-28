<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: "Format" (Online/Print/Online und Print) ist ein
     * Erbe aus der Technische-Doku-spezifischen Vietto-Historie und passt
     * nicht auf jede künftige Projektart. Erster, kleiner Schritt (die
     * grössere Frage - mandantenkonfigurierbarer Katalog statt fest
     * verdrahteter Werte - bleibt offen): "– keiner –" als erste Option,
     * analog zum Workflow-Feld - dafür muss die Spalte nullable sein.
     */
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('format')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('format')->nullable(false)->change();
        });
    }
};
