<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
     * Kleine Icon-Variante (Vietto: "_kl"-Suffix vorm Dateinamen) für
     * kompakte Listen wie die Dashboard-Kacheln, statt der normalen Größe
     * aus der Projektübersicht.
     */
    public function smallSymbol(): ?string
    {
        return $this->symbol ? Str::beforeLast($this->symbol, '.').'_kl.'.Str::afterLast($this->symbol, '.') : null;
    }
}
