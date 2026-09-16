<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heftung kam beim Vietto-Import als rohe Zahl (`intHeftung`) rein, ohne
 * echtes Pulldown - beim Kunden `_Standardkunde` (Tenant 10) existiert das
 * Attribut bereits korrekt kuratiert als data_type=select mit vier Optionen
 * (gefalzt/geheftet/geklebt/geschnitten, "online" bewusst nicht dabei, siehe
 * Print-Formate-Backlog: Online-GA ist ein eigenes, späteres Feature). Diese
 * Migration bringt Sanitär (Tenant 1) auf denselben Stand, inklusive
 * Datenmigration der ~6.700 bestehenden Projekt-Werte (Ralf, 2026-09-16,
 * Vorbedingung für den Heftungs-Filter im Print-Formate-Feature Schritt 4).
 *
 * Alle Options-Werte/Zahlen unten sind feste, selbst geschriebene Konstanten
 * (kein Nutzer-Input) - direkte String-Interpolation in den JSON_SET-
 * Aufrufen ist hier unbedenklich. JSON_EXTRACT(...) wird bewusst als STRING
 * gegen ein String-gebundenes Zahl-Literal verglichen (nicht als Zahl,
 * `CAST(x AS JSON)` gibt es in MariaDB gar nicht) - sonst versucht der
 * Server bei einem gemischten Lauf (manche Zeilen schon auf String
 * umgestellt, andere noch nicht) eine implizite DECIMAL-Umwandlung und
 * bricht mit "Truncated incorrect DECIMAL value" ab.
 */
return new class extends Migration
{
    private const TENANT_ID = 1;

    private const OPTIONS = ['gefalzt', 'geheftet', 'geklebt', 'geschnitten'];

    /**
     * Vietto `heftung`-Katalog: 1=gefalzt, 2=geheftet, 3=geklebt,
     * 4=geschnitten, 5=online (online kommt hier nicht vor, s.o.).
     */
    private const LEGACY_VALUE_MAP = [1 => 'gefalzt', 2 => 'geheftet', 3 => 'geklebt', 4 => 'geschnitten'];

    public function up(): void
    {
        $attributeId = DB::table('attributes')->where('tenant_id', self::TENANT_ID)->where('key', 'heftung')->value('id');

        if ($attributeId === null) {
            return;
        }

        DB::table('attributes')->where('id', $attributeId)->update(['data_type' => 'select']);

        foreach (self::OPTIONS as $sort => $value) {
            DB::table('attribute_options')->updateOrInsert(
                ['attribute_id' => $attributeId, 'value' => $value],
                ['label' => $value, 'sort' => $sort, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach (self::LEGACY_VALUE_MAP as $legacyNumber => $optionValue) {
            DB::statement(
                "UPDATE projects SET attributes = JSON_SET(attributes, '$.heftung', '{$optionValue}')
                 WHERE tenant_id = ? AND JSON_EXTRACT(attributes, '$.heftung') = ?",
                [self::TENANT_ID, (string) $legacyNumber]
            );
        }

        // -1 = "nicht gesetzt" in Vietto - Schlüssel ganz entfernen statt
        // eines expliziten null-Werts (entspricht "Attribut nie befüllt").
        DB::statement(
            "UPDATE projects SET attributes = JSON_REMOVE(attributes, '$.heftung')
             WHERE tenant_id = ? AND JSON_EXTRACT(attributes, '$.heftung') = ?",
            [self::TENANT_ID, '-1']
        );
    }

    public function down(): void
    {
        $attributeId = DB::table('attributes')->where('tenant_id', self::TENANT_ID)->where('key', 'heftung')->value('id');

        if ($attributeId === null) {
            return;
        }

        foreach (self::LEGACY_VALUE_MAP as $legacyNumber => $optionValue) {
            DB::statement(
                "UPDATE projects SET attributes = JSON_SET(attributes, '$.heftung', {$legacyNumber})
                 WHERE tenant_id = ? AND JSON_EXTRACT(attributes, '$.heftung') = ?",
                [self::TENANT_ID, $optionValue]
            );
        }

        DB::table('attribute_options')->where('attribute_id', $attributeId)->delete();
        DB::table('attributes')->where('id', $attributeId)->update(['data_type' => 'number']);
    }
};
