<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung Checkliste <-> Projekt (Vietto: checklist_projekt_cx). Bewusst
 * kein BelongsToTenant - Scoping läuft über die project_id (die selbst
 * schon tenant-gescoped ist), tenant_id steht hier nur zur schnelleren
 * direkten Abfrage.
 */
#[Fillable(['tenant_id', 'project_id', 'checklist_id', 'activated_by_person_id', 'activated_at'])]
class ProjectChecklist extends Model
{
    protected function casts(): array
    {
        return ['activated_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'activated_by_person_id');
    }
}
