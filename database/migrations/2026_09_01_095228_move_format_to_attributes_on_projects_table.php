<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Format ist typabhängig (siehe attribute_projekttyp_cx: System-GA/Modell-GA
     * haben kein "Format Druckbogen (offen)", alle Print-Typen schon)
     * -> gehört ins attributes-JSON, nicht als feste Spalte.
     */
    public function up(): void
    {
        DB::table('projects')
            ->whereNotNull('format')
            ->where('format', '<>', '')
            ->orderBy('id')
            ->each(function ($project) {
                $attributes = json_decode($project->attributes ?? '{}', true) ?: [];
                $attributes['format'] = $project->format;

                DB::table('projects')->where('id', $project->id)->update([
                    'attributes' => json_encode($attributes),
                ]);
            });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('format')->nullable();
        });
    }
};
