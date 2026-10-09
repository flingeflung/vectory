<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['tenant_id', 'project_id', 'user_id', 'type', 'message', 'is_automatic', 'occurred_on', 'is_highlighted', 'edited_by', 'edited_at'])]
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
            'occurred_on' => 'date',
            'is_highlighted' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** Von Hand erfasster Vorgang (nicht automatisch protokolliert). */
    public function isNote(): bool
    {
        return $this->type === ActivityType::Note;
    }

    /** Tag, nach dem der Vorgang einsortiert wird: das Ereignisdatum, sonst der Tag der Erfassung. */
    public function sortDate(): string
    {
        return ($this->occurred_on ?? $this->created_at->local())->format('Y-m-d');
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
