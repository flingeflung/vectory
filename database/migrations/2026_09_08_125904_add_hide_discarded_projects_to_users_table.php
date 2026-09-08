<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persönliche Einstellung: verworfene Projekte beim Zurücksetzen des
     * Projektfilters immer ausblenden (Vietto-Vorbild:
     * voreinst_projektfilter_verworfene). Anders als in Vietto keine
     * separate Option für "beendete Projekte ausblenden" - Ralf wollte das
     * bewusst auf diesen einen Fall vereinfachen. Default true: von den 8
     * Vietto-Usern, die die Option je gesetzt haben, wollten alle 8 sie an.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('hide_discarded_projects_on_reset')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hide_discarded_projects_on_reset');
        });
    }
};
