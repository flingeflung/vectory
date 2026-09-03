<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'project_id'])]
class Favorite extends Model
{
    use BelongsToTenant;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
