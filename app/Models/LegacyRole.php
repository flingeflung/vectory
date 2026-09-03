<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Viettos fachliche "Rolle" - reines Referenzfeld an der Person, nicht
 * Vectorys künftiges Zugriffsrollen-Modell (siehe Migration/CLAUDE.md).
 */
#[Fillable(['tenant_id', 'legacy_id', 'name', 'sort'])]
class LegacyRole extends Model
{
    use BelongsToTenant;
}
