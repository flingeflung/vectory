<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persönliche Spaltenbreiten einer Tabellenansicht (siehe Migration) -
 * bewusst kein BelongsToTenant, gilt für den Benutzer über alle Kunden.
 */
#[Fillable(['user_id', 'table_key', 'column_widths'])]
class UserTablePreference extends Model
{
    /**
     * Tabellen, für die Spaltenbreiten gespeichert werden dürfen. Neue
     * Tabellenansicht anschließen = hier ergänzen + x-data="columnResize(...)"
     * (siehe resources/js/column-resize.js).
     */
    public const TABLE_KEYS = ['projekte'];

    protected function casts(): array
    {
        return ['column_widths' => 'array'];
    }

    /**
     * @return array<string, int> Spaltenschlüssel => Breite in px, leer = Standardlayout
     */
    public static function widthsFor(int $userId, string $tableKey): array
    {
        return static::query()->where('user_id', $userId)->where('table_key', $tableKey)->value('column_widths') ?? [];
    }
}
