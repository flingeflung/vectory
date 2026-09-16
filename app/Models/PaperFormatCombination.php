<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Freigegebene Ausgangs-/Endformat-Paare (Vietto-Analyse "Print-Formate",
 * entspricht dort `formate_cx`). Ob es sich um Typ 1 (Ausgangsformat =
 * Endformat) oder Typ 2 (A != E) handelt, wird bewusst nicht gespeichert,
 * sondern aus dem Vergleich der beiden Format-IDs abgeleitet (siehe
 * isSameFormat()) - Schritt 4 nutzt das für den Heftungs-Filter.
 */
#[Fillable(['tenant_id', 'input_format_id', 'output_format_id', 'fold_count', 'remark', 'active'])]
class PaperFormatCombination extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function inputFormat(): BelongsTo
    {
        return $this->belongsTo(PaperFormat::class, 'input_format_id');
    }

    public function outputFormat(): BelongsTo
    {
        return $this->belongsTo(PaperFormat::class, 'output_format_id');
    }

    public function isSameFormat(): bool
    {
        return $this->input_format_id === $this->output_format_id;
    }

    public function isUsedByProjects(): bool
    {
        return Project::where('paper_format_combination_id', $this->id)->exists();
    }

    /**
     * Heftungs-Filter aus Vietto (ajax_formate_get.php): gefalzt erlaubt nur
     * Kombinationen mit A != E, geheftet/geklebt/geschnitten nur A = E.
     * Ohne (bekannte) Heftung keine Einschränkung. $heftung erwartet den
     * Options-Wert des "heftung"-Attributs (siehe attribute_options.value),
     * z.B. $project->attributes['heftung'] ?? null.
     */
    public function scopeMatchingHeftung(Builder $query, ?string $heftung): Builder
    {
        return match ($heftung) {
            'gefalzt' => $query->whereColumn('input_format_id', '!=', 'output_format_id'),
            'geheftet', 'geklebt', 'geschnitten' => $query->whereColumn('input_format_id', '=', 'output_format_id'),
            default => $query,
        };
    }
}
