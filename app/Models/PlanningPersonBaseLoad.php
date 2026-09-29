<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'person_id', 'planning_base_load_id', 'year', 'name', 'calculation_type', 'value', 'valid_from', 'valid_to'])]
class PlanningPersonBaseLoad extends Model
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function baseLoad(): BelongsTo
    {
        return $this->belongsTo(PlanningBaseLoad::class, 'planning_base_load_id');
    }
}
