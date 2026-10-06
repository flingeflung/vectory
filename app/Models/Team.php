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

    /**
     * Aktive Teams einer Organisation mit ihren Mitgliedern, für "Team zuweisen" bei den Projektbeteiligten.
     * Die Teamstruktur wird nicht am Projekt gespeichert - es werden nur die einzelnen Personen übernommen.
     *
     * @return list<array{id: int, name: string, members: list<array{id: int, name: string, active: bool}>}>
     */
    public static function assignableForTenant(int $tenantId): array
    {
        $collator = new \Collator('de_DE');

        return static::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)->where('active', true)
            ->with(['members' => fn ($query) => $query->withoutGlobalScope('tenant')->select('people.id', 'people.first_name', 'people.last_name', 'people.active')])
            ->get()
            ->sort(fn (Team $a, Team $b) => $collator->compare($a->name, $b->name))
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $person->fullName(),
                    'active' => (bool) $person->active,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
