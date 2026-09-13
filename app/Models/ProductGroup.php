<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'number', 'name', 'sort'])]
class ProductGroup extends Model
{
    use BelongsToTenant;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
