<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweiter, unabhängiger Verzeichnispfad je Mandant - das lokale
 * Arbeitsverzeichnis (schneller Netzlaufwerk-Zugriff, z.B. für externe
 * Korrekturleser), getrennt vom gesperrten "project_path" (nur über
 * Vectory erreichbar). Gleiches Feld-Muster wie project_path, siehe
 * 2026_09_07_103706_add_project_path_to_tenants_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('arbeitsverzeichnis_path', 500)->nullable()->after('project_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('arbeitsverzeichnis_path');
        });
    }
};
