<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['tenant_id', 'legacy_id', 'short_name', 'name', 'description', 'active', 'sort', 'superseded_by_id'])]
class Workflow extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * Ob dieser Workflow schon mindestens einmal einem Projekt zugewiesen
     * wurde (irgendein Schritt hat eine project_workflow_steps-Zeile) - ab
     * dann gilt er als "publiziert" im redaktionellen Sinn (Ralf: einmal
     * benutzte Inhalte nicht mehr stillschweigend ändern, siehe
     * Projektkategorien/Workflow-Diskussion). Solange false, ist der
     * Workflow ein reiner Entwurf und frei bearbeitbar; ab true nur noch
     * über "Neue Version erstellen" veränderbar.
     */
    public function isPublished(): bool
    {
        return DB::table('project_workflow_steps')
            ->join('workflow_steps', 'workflow_steps.id', '=', 'project_workflow_steps.workflow_step_id')
            ->where('workflow_steps.workflow_id', $this->id)
            ->exists();
    }
}
