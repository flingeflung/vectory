<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wochenstunden-Historie einer Person (Ralf, 2026-09-28) - Grundlage der
 * personellen Ressourcenplanung, siehe Migration
 * create_person_weekly_hours_table. Rein anhängbare Historie: valid_to wird
 * NIE direkt vom Nutzer gesetzt, sondern automatisch beim Anlegen des
 * jeweils nächsten Datensatzes (siehe PersonController::storeWeeklyHours()).
 */
#[Fillable(['tenant_id', 'person_id', 'hours', 'valid_from', 'valid_to'])]
class PersonWeeklyHours extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:1',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('tenant');
    }
}
