<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // restrict statt nullOnDelete: eine Person ganz ohne Set darf es
            // nicht geben (siehe PermissionTemplateController::destroy() -
            // Löschen erfordert vorher eine Umzuordnung aller zugewiesenen
            // Personen auf ein anderes Set).
            $table->foreignId('permission_template_id')->nullable()->after('legacy_role_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permission_template_id');
        });
    }
};
