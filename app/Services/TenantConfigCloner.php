<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Tenant;

/**
 * Grundausstattung einer neuen Organisation. Das frühere Klonen einer ganzen Organisation ("Als Kopie von")
 * gibt es nicht mehr (Ralf, 2026-10-03): Voreinstellungen von einer Organisation zur anderen übernimmt die
 * zentrale Seite "Konfiguration übernehmen" (App\Services\PresetCopy\PresetCopier), einzeln wählbar und
 * auch für bestehende Organisationen.
 */
class TenantConfigCloner
{
    /**
     * Legt für einen Mandanten die festen "system"-Felder an (Bezeichnung, Start, Workflow, ...), falls sie noch
     * fehlen (Ralf, 2026-09-10: "frei mischbar mit Zusatzfeldern"). Läuft für JEDEN neuen Mandanten (siehe
     * TenantController::store()) - anders als die Zusatzfelder sind system-Felder kein kopierbarer Bestand, sondern
     * Grundausstattung.
     */
    public function seedSystemAttributes(Tenant $tenant): void
    {
        foreach (Attribute::SYSTEM_FIELDS as $section => $fields) {
            $sort = 0;
            foreach ($fields as $key => $label) {
                $exists = Attribute::query()->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)->where('key', $key)->exists();

                if (! $exists) {
                    Attribute::query()->create([
                        'tenant_id' => $tenant->id,
                        'section' => $section,
                        'system' => true,
                        'label_editable' => in_array($key, Attribute::LABEL_EDITABLE_SYSTEM_FIELDS, true),
                        'key' => $key,
                        'label' => $label,
                        'data_type' => Attribute::DATA_TYPE_TEXT,
                        'sort' => $sort,
                    ]);
                }
                $sort++;
            }
        }
    }
}
