<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'tenant_id', 'mode', 'scale', 'selected_person_ids', 'known_person_ids'])]
class ProjectGanttPreference extends Model
{
    protected function casts(): array
    {
        return [
            'scale' => 'integer',
            'selected_person_ids' => 'array',
            'known_person_ids' => 'array',
        ];
    }
}
