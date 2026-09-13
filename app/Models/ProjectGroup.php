<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Zentrale Sichtbarkeits-/Zugriffsprüfung - war ursprünglich private in
     * ProjectGroupController, jetzt hierher verschoben, damit auch
     * MultichangeController (und künftige Stellen) dieselbe Prüfung nutzen,
     * statt sie zu duplizieren.
     */
    public function authorizeViewer(): void
    {
        abort_unless($this->tenant_id === CurrentTenant::id(), 404);
        abort_unless($this->viewers()->where('users.id', Auth::id())->exists(), 403);
    }
}
