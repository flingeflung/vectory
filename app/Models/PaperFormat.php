<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Basis-Katalog für Print-Papierformate (Ralf, 2026-09-15, Vietto-Analyse
 * "Print-Formate", entspricht dort `formate`). Reine Einzelgrößen - die
 * eigentlich nutzbaren Format-KOMBINATIONEN (Ausgangsformat -> Endformat)
 * sind ein eigenes Modell, siehe PaperFormatCombination.
 */
#[Fillable(['tenant_id', 'name', 'short_name', 'width_mm', 'height_mm', 'remark', 'show_dimensions', 'active', 'sort'])]
class PaperFormat extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'show_dimensions' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function combinationsAsInput(): HasMany
    {
        return $this->hasMany(PaperFormatCombination::class, 'input_format_id');
    }

    public function combinationsAsOutput(): HasMany
    {
        return $this->hasMany(PaperFormatCombination::class, 'output_format_id');
    }

    public function isUsedInCombination(): bool
    {
        return $this->combinationsAsInput()->exists() || $this->combinationsAsOutput()->exists();
    }
}
