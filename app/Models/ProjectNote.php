<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Bemerkungen" (TYPE_REMARK, nur in Vectory sichtbar) und "Änderungen zur
 * Vorversion" (TYPE_CHANGE, können auch nach außen gehen, z. B. an
 * Hotline/QM/Vertrieb - Vietto-Vorbild: eine Tabelle "bemerkungen" mit
 * intTyp 1/2). Einträge werden nur angelegt/gelöscht, nie bearbeitet -
 * daher kein updated_at.
 */
#[Fillable(['tenant_id', 'project_id', 'type', 'text', 'created_by_user_id', 'created_at'])]
class ProjectNote extends Model
{
    use BelongsToTenant;

    public const TYPE_REMARK = 'remark';

    public const TYPE_CHANGE = 'change';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
