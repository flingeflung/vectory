<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['tenant_id', 'project_id', 'user_id', 'type', 'message', 'is_automatic'])]
class Activity extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'is_automatic' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Zentraler Anlege-Helfer für Vorgänge - immer hierüber loggen statt
     * Activity::create() direkt, damit tenant_id/user_id konsistent
     * befüllt werden.
     */
    public static function log(Project $project, ActivityType $type, string $message): self
    {
        return self::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'message' => $message,
            'is_automatic' => true,
        ]);
    }
}
