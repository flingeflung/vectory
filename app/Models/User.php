<?php

namespace App\Models;

use App\Support\AccessLevel;
use App\Support\Morph;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['status', 'activated_at', 'name', 'username', 'email', 'password', 'tenant_id', 'last_active_tenant_id', 'person_id', 'role', 'hide_discarded_projects_on_reset', 'project_connection_sort_desc'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Morphen (siehe App\Support\Morph): Ist DIESER Benutzer der angemeldete echte Super-Admin mit aktivem Morphen? Dann liefern
     * Rolle und Heimat-Organisation die gemorphten Werte, sonst die gespeicherten. Andere Benutzer sind nie betroffen.
     */
    private function isMorphed(): bool
    {
        return ($this->attributes['role'] ?? null) === AccessLevel::SUPER_ADMIN
            && Morph::state() !== null
            && Auth::id() === $this->getKey();
    }

    protected function getRoleAttribute($value)
    {
        return $this->isMorphed() ? Morph::state()['role'] : $value;
    }

    protected function getTenantIdAttribute($value)
    {
        // Nur der Organisations-Admin gehört "seiner" (aktiven) Organisation an. Ein gemorphter User behält seine Heimat-Organisation:
        // seine Person gehört dorthin, und in andere Organisationen kommt er wie ein echter User per Freigabe (siehe CurrentTenant).
        if ($this->isMorphed() && Morph::state()['role'] === AccessLevel::ORGANIZATION_ADMIN) {
            return Morph::state()['tenant_id'];
        }

        return $value;
    }

    public function isSuperAdmin(): bool
    {
        return AccessLevel::isSuperAdmin($this);
    }

    public function isCentralAdmin(): bool
    {
        return AccessLevel::isCentralAdmin($this);
    }

    public function isOrganizationAdmin(): bool
    {
        return AccessLevel::isOrganizationAdmin($this);
    }

    public function isAdmin(): bool
    {
        return AccessLevel::isAdmin($this);
    }

    public function canAccessAllOrganizations(): bool
    {
        return AccessLevel::canAccessAllOrganizations($this);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * withoutGlobalScope('tenant'): die EIGENE Person eines Users muss
     * immer auflösbar sein, unabhängig davon, welcher Kunde gerade aktiv
     * ist - sonst würde z.B. Gate::before() (AppServiceProvider,
     * $user->person?->hasPermission()) für JEDE Rechteprüfung fälschlich
     * "nein" liefern, sobald man einen Kunden ansieht, der nicht der
     * eigene Heimat-Mandant ist (Ralfs Bug-Report: "+Neues Projekt"-Button
     * nur bei einem bestimmten Kunden sichtbar). Person::BelongsToTenant
     * filtert sonst automatisch auf CurrentTenant::id() - hier bewusst
     * NICHT gewollt, das ist die eigene Identität, keine Personenliste
     * eines fremden Mandanten.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('tenant');
    }

    /**
     * "Meine Projektgruppen" - Gruppen, die dieser Nutzer sehen/bearbeiten
     * kann (Sichtbarkeit, kein Besitzer-Konzept, siehe ProjectGroup).
     */
    public function projectGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProjectGroup::class, 'project_group_user')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'password' => 'hashed',
            'hide_discarded_projects_on_reset' => 'boolean',
            'project_connection_sort_desc' => 'boolean',
        ];
    }

    /** Konto-Status: ein vorbereitetes Konto hat weder Benutzername noch Passwort, bis die Person den Aktivierungslink nutzt. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public function isPending(): bool
    {
        return ($this->attributes['status'] ?? self::STATUS_ACTIVE) === self::STATUS_PENDING;
    }

    /** Darf sich anmelden: aktives Konto und aktive Person (Ralf, 2026-10-08: deaktivierte Personen sind gesperrt). */
    public function mayLogIn(): bool
    {
        return ! $this->isPending() && ($this->person === null || (bool) $this->person->active);
    }
}
