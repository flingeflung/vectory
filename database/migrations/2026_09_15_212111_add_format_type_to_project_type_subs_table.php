<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            // 1 = Standard-Formate (Katalog-Kombinationen), 2 = Variable
            // Formate (freier Text) - siehe ProjectTypeSub::FORMAT_TYPES.
            // Default 2 (freier Text, keine Katalog-Abhängigkeit) als
            // unkritischer Rückfallwert für bestehende Arten.
            $table->unsignedTinyInteger('format_type')->default(2)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->dropColumn('format_type');
        });
    }
};
