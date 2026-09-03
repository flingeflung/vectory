<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Icon-Dateiname für die Projektart-Anzeige (Übersichtstabelle), analog
     * Viettos projartsub.strSymbol - übernommene Icons liegen unter
     * public/images/project-type-icons/.
     */
    public function up(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->string('symbol')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->dropColumn('symbol');
        });
    }
};
