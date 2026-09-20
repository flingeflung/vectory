<?php

namespace App\Services;

use App\Models\Attribute;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatisches Schnell-Filtern/Sortieren für jedes Attribut (Ralf,
 * 2026-09-10: "jedes neu angelegte Attribut kriegt automatisch das
 * Tempo-Extra mit"). Verallgemeinert das Verfahren, das bei "Materialnummer"
 * einmal von Hand gemacht wurde (siehe Migration
 * 2026_09_01_094141_move_material_number_and_construction_year_on_projects_table):
 * eine generierte, indizierte Spalte auf `projects`, die den Wert live aus
 * dem attributes-JSON zieht - kein zweiter Datenbestand zum Synchronhalten.
 *
 * Mehrfachauswahl-Pulldowns (Attribute::DATA_TYPE_SELECT mit multiple=true)
 * sind hier bewusst ausgenommen: mehrere Werte passen nicht in eine
 * einzelne Spalte. Das eigentliche Nutzen dieser Spalten in Filter-/
 * Sortier-UI ist noch nicht gebaut (wie bei Materialnummer bisher auch) -
 * hier geht es nur darum, dass die Infrastruktur beim Anlegen sofort
 * entsteht, nicht erst nachträglich per Hand.
 */
class AttributeColumnManager
{
    public function columnName(string $key): string
    {
        return "attributes_{$key}";
    }

    /**
     * Sortierspalte (Ralf, 2026-09-20: Zusatzfeld-Spalten in der Übersicht sortierbar): Zahlenfelder
     * müssen numerisch sortieren ("10" nach "9"), hochzählende Textfelder wie die Kundenversion nach
     * ihrer ersten Zahl ("V9" vor "V10"). Alle anderen Textfelder sortieren alphabetisch über die
     * normale Spalte, brauchen also keine zweite.
     */
    public function sortColumnName(string $key): string
    {
        return "attributes_{$key}_sort";
    }

    public function needsSortColumn(Attribute $attribute): bool
    {
        return $this->isColumnBacked($attribute)
            && ($attribute->data_type === Attribute::DATA_TYPE_NUMBER
                || ($attribute->data_type === Attribute::DATA_TYPE_TEXT && $attribute->increments_on_new_version));
    }

    public function ensureSortColumn(Attribute $attribute): void
    {
        $column = $this->sortColumnName($attribute->key);
        if (! $this->needsSortColumn($attribute) || Schema::hasColumn('projects', $column)) {
            return;
        }

        $raw = "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.{$attribute->key}'))";

        Schema::table('projects', function (Blueprint $table) use ($column, $attribute, $raw) {
            if ($attribute->data_type === Attribute::DATA_TYPE_NUMBER) {
                $table->decimal($column, 20, 4)
                    ->storedAs("IF($raw REGEXP '^-?[0-9]{1,15}([.][0-9]+)?\$', CAST($raw AS DECIMAL(20,4)), NULL)")
                    ->nullable()->index();
            } else {
                $table->unsignedBigInteger($column)
                    ->storedAs("CAST(LEFT(NULLIF(REGEXP_SUBSTR($raw, '[0-9]+'), ''), 15) AS UNSIGNED)")
                    ->nullable()->index();
            }
        });
    }

    public function isColumnBacked(Attribute $attribute): bool
    {
        return ! ($attribute->data_type === Attribute::DATA_TYPE_SELECT && $attribute->multiple);
    }

    public function ensureColumn(Attribute $attribute): void
    {
        if (! $this->isColumnBacked($attribute)) {
            return;
        }

        $column = $this->columnName($attribute->key);

        // Mehrere Mandanten können unabhängig voneinander ein Attribut mit
        // demselben technischen key anlegen (projects ist eine einzige,
        // mandantenübergreifende Tabelle) - dann existiert die Spalte schon
        // durch ein anderes Attribut, einfach weiterverwenden statt Fehler.
        if (Schema::hasColumn('projects', $column)) {
            $this->ensureSortColumn($attribute);

            return;
        }

        Schema::table('projects', function (Blueprint $table) use ($column, $attribute) {
            $table->string($column, 191)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.{$attribute->key}'))")
                ->nullable()
                ->index();
        });

        $this->ensureSortColumn($attribute);
    }

    /**
     * Löscht die generierte Spalte nur, wenn kein anderes Attribut
     * (irgendeines Mandanten) mit demselben key mehr existiert - siehe
     * ensureColumn().
     */
    public function dropColumnIfUnused(Attribute $attribute): void
    {
        if (! $this->isColumnBacked($attribute)) {
            return;
        }

        $stillUsed = Attribute::withoutGlobalScope('tenant')
            ->where('key', $attribute->key)
            ->where('id', '!=', $attribute->id)
            ->exists();

        if ($stillUsed) {
            return;
        }

        foreach ([$this->columnName($attribute->key), $this->sortColumnName($attribute->key)] as $column) {
            if (Schema::hasColumn('projects', $column)) {
                Schema::table('projects', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
}
