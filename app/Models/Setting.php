<?php

namespace App\Models;

use App\Support\CurrentTenant;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Frühere Einstellungen bleiben für bestehende Daten erhalten. */
#[Fillable(['tenant_id', 'key', 'value'])]
class Setting extends Model
{
    use BelongsToTenant;

    public static function ganttMaxProjects(): int
    {
        $value = Tenant::query()->whereKey(CurrentTenant::id())->value('gantt_max_projects');

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]) ?: 50;
    }
}
