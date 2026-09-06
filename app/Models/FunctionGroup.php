<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Reines Task-Routing (Illustrator-Auswahl etc.) - hat bewusst keinen
 * Einfluss auf Rechte, siehe PermissionTemplate/Person::hasPermission().
 */
#[Fillable(['tenant_id', 'legacy_id', 'name', 'short_name', 'sort', 'active'])]
class FunctionGroup extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'function_group_member')->orderBy('sort');
    }

    public function workflowSteps(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowStep::class, 'workflow_step_function_group');
    }
}
