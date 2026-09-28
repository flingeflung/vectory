<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verknuepfung "Set bindet Baustein ein" - beide Seiten zeigen auf
     * permission_templates.id (selbstreferenzierend), deshalb zwei eigene
     * Spaltennamen statt der Standard-Konvention. permission_template_id =
     * das Set (obere Ebene), baustein_id = der eingebundene Baustein
     * (untere Ebene, siehe ProjectTemplate::bausteine()-Relation). Ein
     * Baustein selbst hat hier nie einen Eintrag als permission_template_id
     * - das wird ausschliesslich im Controller/View durchgesetzt (kein
     * Baustein-Auswahlformular fuer Bausteine selbst), nicht per DB-
     * Constraint, da Laravel-Migrationen keine bedingten Check-Constraints
     * unterstuetzen.
     */
    public function up(): void
    {
        Schema::create('permission_template_baustein', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_template_id')->constrained('permission_templates')->cascadeOnDelete();
            $table->foreignId('baustein_id')->constrained('permission_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_template_id', 'baustein_id'], 'template_baustein_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_template_baustein');
    }
};
