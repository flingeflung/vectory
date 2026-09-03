<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Mandantenfähigkeit (Single-DB-Ansatz): scoped Models werden automatisch auf
 * den Mandanten des eingeloggten Users eingeschränkt und beim Anlegen damit
 * befüllt. Solange es nur einen (Default-)Mandanten gibt, ändert das nichts
 * am sichtbaren Verhalten.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', Auth::user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
