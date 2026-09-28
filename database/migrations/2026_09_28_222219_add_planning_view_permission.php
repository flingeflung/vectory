<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Neuer Hauptnavigationspunkt "Planung" (Ralf, 2026-09-28) - rechtegesteuert,
     * startet bei niemandem automatisch, Ralf weist es selbst zu.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'planning.view',
            'label' => 'Planung: Zugriff auf den Bereich',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'planning.view')->delete();
    }
};
