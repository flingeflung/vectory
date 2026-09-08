<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['tenant_id', 'legacy_id', 'short_name', 'name', 'description', 'active', 'sort', 'superseded_by_id', 'published_at'])]
class Workflow extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'published_at' => 'datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * Ob dieser Workflow bewusst veröffentlicht wurde (explizites Flag,
     * published_at) - ab dann gilt er als "publiziert" im redaktionellen
     * Sinn (Ralf: einmal veröffentlichte Inhalte nicht mehr stillschweigend
     * ändern) und ist nur noch über "Neue Version erstellen" veränderbar.
     *
     * BEWUSST NICHT mehr von der Nutzung durch ein Projekt abgeleitet (frühere
     * Version dieser Methode): das sperrte einen Workflow schon beim ersten
     * TEST-Zuweisen an ein Projekt, genau während man ihn noch ausprobiert/
     * korrigiert (Ralfs konkreter Bug-Report). Ein Entwurf bleibt jetzt
     * beliebig oft testweise zuweisbar UND frei bearbeitbar, bis man ihn
     * bewusst veröffentlicht (siehe WorkflowController::publish()).
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
