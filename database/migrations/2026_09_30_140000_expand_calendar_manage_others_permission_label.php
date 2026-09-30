<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'calendar.entries.manage_others')
            ->update(['label' => 'Kalender: Einträge anderer Personen anlegen, bearbeiten und löschen']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'calendar.entries.manage_others')
            ->update(['label' => 'Kalender: Einträge anderer Personen bearbeiten und löschen']);
    }
};
