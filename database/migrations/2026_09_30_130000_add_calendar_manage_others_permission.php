<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'calendar.entries.manage_others',
            'label' => 'Kalender: Einträge anderer Personen bearbeiten und löschen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'calendar.entries.manage_others')->delete();
    }
};
