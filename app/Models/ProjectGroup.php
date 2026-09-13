<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name'])]
class ProjectGroup extends Model
{
    use BelongsToTenant;

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_group_project')->withTimestamps();
    }

    /**
     * Nutzer, die diese Gruppe sehen/bearbeiten können - kein Besitzer-
     * Konzept, echtes Teilen (siehe Migration).
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_group_user')->withTimestamps();
    }
}
