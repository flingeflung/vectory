<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $planningId = DB::table('permissions')->where('key', 'planning.view')->value('id');
        $personBreakdownId = DB::table('permissions')->where('key', 'project.hours.person_breakdown')->value('id');

        if ($planningId) {
            DB::table('permissions')->where('id', $planningId)->update([
                'label' => 'Planung: erweiterte Planung und personenbezogene Auswertungen',
                'updated_at' => now(),
            ]);
        }

        if ($planningId && $personBreakdownId) {
            $now = now();
            foreach (DB::table('permission_template_permission')->where('permission_id', $personBreakdownId)->pluck('permission_template_id') as $templateId) {
                DB::table('permission_template_permission')->insertOrIgnore([
                    'permission_template_id' => $templateId,
                    'permission_id' => $planningId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('permissions')->where('key', 'project.hours.person_breakdown')->delete();
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'planning.view')->update([
            'label' => 'Planung: Zugriff auf den Bereich',
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insertOrIgnore([
            'key' => 'project.hours.person_breakdown',
            'label' => 'Projekt: Stunden-Aufschlüsselung nach Person und Tag einsehen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
