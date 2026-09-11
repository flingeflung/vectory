<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'tenant_id', 'last_active_tenant_id', 'person_id', 'role', 'hide_discarded_projects_on_reset', 'project_connection_sort_desc'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
    public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('tenant');
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
            'password' => 'hashed',
            'hide_discarded_projects_on_reset' => 'boolean',
            'project_connection_sort_desc' => 'boolean',
        ];
    }
}
