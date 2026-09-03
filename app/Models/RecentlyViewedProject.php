<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'project_id', 'viewed_at'])]
class RecentlyViewedProject extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Projekt als "geöffnet" vermerken - bestehenden Eintrag nur auf den
     * neuen Zeitpunkt aktualisieren (kein Duplikat), danach auf die letzten
     * self::LIMIT Einträge je Nutzer trimmen. Entspricht Viettos
     * lastopened-Tabelle (dort Cap=15, siehe write_lastopened()).
     */
    public const LIMIT = 15;

    public static function record(Project $project, User $user): void
    {
        self::updateOrCreate(
            ['user_id' => $user->id, 'project_id' => $project->id],
            ['tenant_id' => $project->tenant_id, 'viewed_at' => now()]
        );

        $staleIds = self::where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->skip(self::LIMIT)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            self::whereIn('id', $staleIds)->delete();
        }
    }
}
