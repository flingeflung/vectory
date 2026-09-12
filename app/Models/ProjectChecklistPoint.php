<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abhak-Zustand pro Projekt+Punkt (Vietto: checklist_items). Speichert wie
 * im Vorbild wer/wann zuletzt geändert hat, aber KEINE Historie (Uncheck+
 * Recheck durch eine andere Person überschreibt den vorherigen Bearbeiter -
 * bewusst wie Vietto übernommen, kein Bug, den wir gefunden haben, nur
 * fehlende Historie, die niemand verlangt hat).
 */
#[Fillable(['tenant_id', 'project_id', 'checklist_point_id', 'done', 'done_by_person_id', 'done_at'])]
class ProjectChecklistPoint extends Model
{
    protected function casts(): array
    {
        return ['done' => 'boolean', 'done_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(ChecklistPoint::class, 'checklist_point_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'done_by_person_id');
    }
}
