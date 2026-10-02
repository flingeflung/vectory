<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['critical_project_finding_id', 'user_id', 'acknowledged_at', 'hidden_until'])]
class CriticalProjectFindingState extends Model
{
    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'hidden_until' => 'date',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(CriticalProjectFinding::class, 'critical_project_finding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
