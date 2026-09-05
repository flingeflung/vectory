<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'role', 'permission_id'])]
class RolePermission extends Model
{
    use BelongsToTenant;

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
