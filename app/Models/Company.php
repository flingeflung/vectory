<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'legacy_id', 'name', 'short_name', 'sort'])]
class Company extends Model
{
    use BelongsToTenant;

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
