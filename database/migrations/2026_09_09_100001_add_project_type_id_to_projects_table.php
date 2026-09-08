<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Echte Fremdschlüssel zusätzlich zu den bestehenden project_type_main/
     * project_type_sub-Spalten (die bleiben unverändert - sie tragen die
     * rohe Vietto-Legacy-ID und werden weiterhin für
     * attribute_project_type gebraucht, siehe Project::relevantAttributes()).
     * Grund für die neuen Spalten: project_type_sub verweist nur auf
     * Datensätze, die eine legacy_id haben (aus Vietto importiert) - neu
     * angelegte Arten haben keine und könnten Projekten nie zugewiesen
     * werden. Rückfüllung der Bestandsdaten in einem separaten Schritt.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_type_main_id')->nullable()->after('project_type_main')->constrained('project_type_mains')->nullOnDelete();
            $table->foreignId('project_type_sub_id')->nullable()->after('project_type_sub')->constrained('project_type_subs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_type_main_id');
            $table->dropConstrainedForeignId('project_type_sub_id');
        });
    }
};
