<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_type_main_id', 'legacy_id', 'name', 'active', 'color', 'symbol', 'sort', 'format_type'])]
class ProjectTypeSub extends Model
{
    use BelongsToTenant;

    /**
     * Steuert die Formatauswahl im Projekt (Vietto: `projartsub.intFormattyp`).
     * Fest im Code definiert statt frei erweiterbar (gleiche Begründung wie
     * beim Rechte-Katalog: Vectory wählt vorgegebene Optionen, keine
     * Kunden-eigenen Typen) - Formattyp 3 "Online-GA" bewusst noch nicht
     * enthalten, kommt später als eigenes "Online-Dokument"-Feature.
     */
    public const FORMAT_TYPE_STANDARD = 1;

    public const FORMAT_TYPE_VARIABLE = 2;

    public static function formatTypes(): array
    {
        return [
            self::FORMAT_TYPE_STANDARD => __('Standard-Formate (Katalog)'),
            self::FORMAT_TYPE_VARIABLE => __('Variable Formate (freier Text)'),
        ];
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function main(): BelongsTo
    {
        return $this->belongsTo(ProjectTypeMain::class, 'project_type_main_id');
    }

    /**
     * Symbolkatalog (Ralf, 2026-09-26): kein DB-Katalog, sondern einfach
     * alle Bilddateien im Icon-Verzeichnis - Ralf legt neue Symbole direkt
     * per Datei-Upload dort ab (FTP/Total Commander), kein Admin-Upload-UI
     * nötig. "_kl"-Varianten sind nur Begleitdateien einer Hauptdatei und
     * tauchen im Katalog nicht als eigene Wahl auf.
     *
     * @return list<string> Dateinamen, alphabetisch
     */
    public static function availableSymbols(): array
    {
        $files = glob(public_path('images/project-type-icons/*.{png,svg,PNG,SVG,webp,WEBP}'), GLOB_BRACE) ?: [];

        $names = collect($files)
            ->map(fn (string $path) => basename($path))
            ->reject(fn (string $name) => str_contains($name, '_kl.'))
            ->values()
            ->sort(SORT_STRING | SORT_FLAG_CASE)
            ->values()
            ->all();

        return $names;
    }
}
