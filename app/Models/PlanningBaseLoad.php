<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'year', 'name', 'calculation_type', 'value', 'valid_from', 'valid_to'])]
class PlanningBaseLoad extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }
}
