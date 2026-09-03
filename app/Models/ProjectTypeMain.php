<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'legacy_id', 'name', 'sort'])]
class ProjectTypeMain extends Model
{
    use BelongsToTenant;

    public function subs(): HasMany
    {
        return $this->hasMany(ProjectTypeSub::class)->orderBy('sort');
    }
}
