<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'date', 'remarks', 'active'])]
class Holiday extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'active' => 'boolean',
        ];
    }
}
