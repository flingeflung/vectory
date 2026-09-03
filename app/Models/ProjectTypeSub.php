<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['tenant_id', 'project_type_main_id', 'legacy_id', 'name', 'color', 'symbol', 'sort'])]
class ProjectTypeSub extends Model
{
    use BelongsToTenant;

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
