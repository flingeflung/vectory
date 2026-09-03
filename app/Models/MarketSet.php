<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'legacy_id', 'name', 'sort'])]
class MarketSet extends Model
{
    use BelongsToTenant;

    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_set_market');
    }
}
