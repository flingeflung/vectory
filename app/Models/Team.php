<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Team (Ralf, 2026-10-06): benannte Personengruppe einer Organisation, unabhängig von Funktionsgruppen und Rechten.
 * Bisher nur Verwaltung; Mitglieder dürfen auch ausgeliehene Personen anderer Organisationen sein.
 */
#[Fillable(['tenant_id', 'name', 'short_name', 'description', 'active'])]
class Team extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public const ROLE_MEMBER = 'member';

    public const ROLE_LEAD = 'lead';

    public const ROLE_DEPUTY = 'deputy';

    public const ROLES = [self::ROLE_MEMBER, self::ROLE_LEAD, self::ROLE_DEPUTY];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'team_person')->withPivot('role')->withTimestamps();
    }
}
