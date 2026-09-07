<?php

namespace App\Models\Concerns;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Mandantenfähigkeit (Single-DB-Ansatz): scoped Models werden automatisch auf
 * den GERADE AKTIVEN Mandanten eingeschränkt (siehe CurrentTenant - bei
 * ausgeschalteter Mandantenfähigkeit weiterhin einfach der Heimat-Mandant
 * des Users, ändert nichts am bisherigen Verhalten) und beim Anlegen damit
 * befüllt.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', CurrentTenant::id());
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && Auth::check()) {
                $model->tenant_id = CurrentTenant::id();
            }
        });
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
