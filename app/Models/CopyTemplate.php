<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Projekte kopieren"-Vorlage (Ralf, 2026-09-11): legt fest, welche
 * Attribute (feste Felder + Zusatzfelder, siehe Attribute::SYSTEM_FIELDS)
 * beim Kopieren eines Projekts übernommen werden.
 */
#[Fillable(['tenant_id', 'name', 'sort'])]
class CopyTemplate extends Model
{
    use BelongsToTenant;

    /**
     * Bewusst nicht "attributes()" genannt - kollidiert mit Eloquents
     * eigenem internen $attributes-Array für die Modell-Spalten selbst.
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'copy_template_attribute');
    }
}
