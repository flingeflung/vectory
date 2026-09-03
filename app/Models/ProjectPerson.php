<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectPersonObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'function_group_id', 'person_id', 'is_primary'])]
#[ObservedBy(ProjectPersonObserver::class)]
class ProjectPerson extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function functionGroup(): BelongsTo
    {
        return $this->belongsTo(FunctionGroup::class);
    }
}
