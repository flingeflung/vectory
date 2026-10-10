<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mitteilung vom Admin an die Startseite einer oder mehrerer Organisationen (Ralf, 2026-10-10). Gilt bis einschließlich
 * ends_on, ohne Datum bis zum Löschen.
 */
#[Fillable(['text', 'ends_on', 'created_by_user_id'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['ends_on' => 'date'];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'announcement_tenant');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->whereHas('tenants', fn (Builder $tenants) => $tenants->where('tenants.id', $tenantId));
    }

    public function isActive(): bool
    {
        return $this->ends_on === null || $this->ends_on->gte(today());
    }
}
