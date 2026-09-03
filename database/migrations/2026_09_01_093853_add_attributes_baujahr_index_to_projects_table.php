<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Beispiel für das Muster "gezielter Index statt EAV-Join": das
     * JSON-Attribut "baujahr" wird als generierte, indizierte Spalte
     * gespiegelt, weil es filter-/sortierrelevant ist. Weitere Attribute
     * bekommen dasselbe nur bei tatsächlichem Bedarf, nicht pauschal.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('attributes_baujahr', 30)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.baujahr'))")
                ->nullable()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('attributes_baujahr');
        });
    }
};
