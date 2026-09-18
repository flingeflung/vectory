<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'jobload.overview.view_all',
            'label' => 'Zeiterfassung: Übersicht aller Personen eines Kunden sehen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'jobload.overview.view_all')->delete();
    }
};
