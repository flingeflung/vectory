<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['tenant_id', 'name', 'is_verbund'])]
class ProjectGroup extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_verbund' => 'boolean'];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_group_project')->withTimestamps();
    }

    /**
     * Nutzer, die diese Gruppe sehen/bearbeiten können - kein Besitzer-
     * Konzept, echtes Teilen (siehe Migration). Gilt NICHT für Verbund-
     * Gruppen (siehe authorizeViewer()/visibleTo()) - die sind für jeden
     * mit Zugriff auf den Mandanten sichtbar, unabhängig von dieser Liste.
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
     *
     * "Projektverbund" (Ralf, 2026-09-14): eine Verbund-Gruppe soll für
     * JEDEN mit Zugriff auf diesen Mandanten sichtbar/bearbeitbar sein,
     * auch ohne explizites Teilen - und das LIVE, auch für Personen, die
     * erst später zum Mandanten dazustoßen (kein einmaliger Snapshot beim
     * Verbund-Anlegen). Die erste Prüfung oben (tenant_id === CurrentTenant::id())
     * beweist das bereits: CurrentTenant::id() liefert nur Mandanten, für
     * die der Nutzer laut CurrentTenant::userCanAccess() Zugriff hat -
     * deshalb reicht bei einer Verbund-Gruppe ein einfaches return, ohne
     * die Viewer-Liste zu prüfen.
     */
    public function authorizeViewer(): void
    {
        abort_unless($this->tenant_id === CurrentTenant::id(), 404);

        if ($this->is_verbund) {
            return;
        }

        abort_unless($this->viewers()->where('users.id', Auth::id())->exists(), 403);
    }

    /**
     * Gruppen, die dieser Nutzer sehen darf - eigene (per project_group_
     * user geteilte) UND alle Verbund-Gruppen des aktuellen Mandanten
     * (siehe authorizeViewer()). Ersetzt die früheren direkten
     * User::projectGroups()-Aufrufe an den entsprechenden Stellen.
     */
    public static function visibleTo(User $user): Builder
    {
        return static::query()->where(
            fn ($query) => $query->whereHas('viewers', fn ($q) => $q->where('users.id', $user->id))
                ->orWhere('is_verbund', true)
        );
    }
}
