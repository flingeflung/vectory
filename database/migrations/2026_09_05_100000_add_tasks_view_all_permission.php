<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drittes Recht des Katalogs - "eigene vs. alle"-Muster (siehe
     * Vietto-Analyse, Aufg getAll). Ohne dieses Recht sieht man bei den
     * Aufgaben nur seine eigenen, nicht die anderer Personen.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'tasks.view_all',
            'label' => 'Bei Aufgaben alle Personen sehen (nicht nur eigene)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'tasks.view_all')->delete();
    }
};
