<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zeile deckt beide Blickrichtungen ab (siehe Migration) - "project"
 * ist die Seite von "label" (project -> relatedProject), "relatedProject"
 * die Seite von "label_reverse" (relatedProject -> project).
 */
#[Fillable(['tenant_id', 'project_id', 'related_project_id', 'label', 'label_reverse', 'created_by_user_id'])]
class ProjectConnection extends Model
{
    use BelongsToTenant;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function relatedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'related_project_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
