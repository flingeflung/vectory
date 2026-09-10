<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'key', 'label', 'data_type', 'sort', 'available_in_mail_templates'])]
class Attribute extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'available_in_mail_templates' => 'boolean',
        ];
    }
}
