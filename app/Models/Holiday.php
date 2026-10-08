<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'date', 'weekday', 'remarks', 'active'])]
#[ObservedBy(\App\Observers\HolidayObserver::class)]
class Holiday extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weekday' => 'integer',
            'active' => 'boolean',
        ];
    }
}
