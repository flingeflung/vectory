<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: "jobload.overview.view_all" bündelte zwei
     * unterschiedliche Dinge in einem Recht - andere EINZELNE Personen
     * einsehen (personenbezogen, der eigentlich Leistungskontrolle-
     * sensible Teil) und die kundenweite Auswertung nach Jobgruppen
     * (aggregiert, ohne Personenbezug). Aufgeteilt in zwei eigene Rechte.
     * Dazu ein drittes, neues Recht für die geplante (noch nicht gebaute)
     * projektbezogene Personen×Tage-Aufschlüsselung im Zeiten-Tab - noch
     * an keine View gekoppelt, Ralf weist es selbst zu, sobald die View
     * steht.
     *
     * Bestehende Zuweisungen von "view_all" werden 1:1 auf BEIDE neuen
     * Rechte übertragen (keine funktionale Änderung durch dieses Update),
     * damit niemand durch die Aufteilung stillschweigend Zugriff verliert -
     * Ralf kann die drei Rechte danach in Ruhe selbst feiner verteilen.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            [
                'key' => 'jobload.overview.view_others',
                'label' => 'Zeiterfassung: andere einzelne Personen einsehen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'jobload.overview.customer_summary',
                'label' => 'Zeiterfassung: kundenweite Auswertung nach Jobgruppen sehen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'project.hours.person_breakdown',
                'label' => 'Projekt: Stunden-Aufschlüsselung nach Person und Tag einsehen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $oldId = DB::table('permissions')->where('key', 'jobload.overview.view_all')->value('id');
        $viewOthersId = DB::table('permissions')->where('key', 'jobload.overview.view_others')->value('id');
        $customerSummaryId = DB::table('permissions')->where('key', 'jobload.overview.customer_summary')->value('id');

        if ($oldId) {
            $templateIds = DB::table('permission_template_permission')->where('permission_id', $oldId)->pluck('permission_template_id');
            $now = now();
            foreach ($templateIds as $templateId) {
                DB::table('permission_template_permission')->insertOrIgnore([
                    ['permission_template_id' => $templateId, 'permission_id' => $viewOthersId, 'created_at' => $now, 'updated_at' => $now],
                    ['permission_template_id' => $templateId, 'permission_id' => $customerSummaryId, 'created_at' => $now, 'updated_at' => $now],
                ]);
            }
            DB::table('permissions')->where('id', $oldId)->delete();
        }
    }

    public function down(): void
    {
        DB::table('permissions')->insert([
            'key' => 'jobload.overview.view_all',
            'label' => 'Zeiterfassung: alle Personen + Auswertung eines Kunden sehen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oldId = DB::table('permissions')->where('key', 'jobload.overview.view_all')->value('id');
        $viewOthersId = DB::table('permissions')->where('key', 'jobload.overview.view_others')->value('id');
        $now = now();
        foreach (DB::table('permission_template_permission')->where('permission_id', $viewOthersId)->pluck('permission_template_id') as $templateId) {
            DB::table('permission_template_permission')->insertOrIgnore([
                'permission_template_id' => $templateId, 'permission_id' => $oldId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('permissions')->whereIn('key', ['jobload.overview.view_others', 'jobload.overview.customer_summary', 'project.hours.person_breakdown'])->delete();
    }
};
