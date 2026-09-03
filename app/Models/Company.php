<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'legacy_id', 'name', 'short_name', 'sort'])]
class Company extends Model
{
    use BelongsToTenant;
}
