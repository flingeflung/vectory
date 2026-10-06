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
     * Aktive Teams, die bei den Projektbeteiligten eines Projekts zur Auswahl stehen (Ralf, 2026-10-06): die der Organisation des Projekts
     * UND die aller Organisationen, auf die der angemeldete Benutzer Zugriff hat - ein Team der Heimatfirma lässt sich also auch in einem
     * Kundenprojekt zuweisen. Die Teamstruktur wird nicht am Projekt gespeichert, es werden nur die einzelnen Personen übernommen.
     *
     * @return list<array{id: int, name: string, org: ?string, members: list<array{id: int, name: string, active: bool, released: bool}>}>
     */
    public static function assignableForProject(int $projectTenantId): array
    {
        $collator = new \Collator('de_DE');
        $tenantIds = collect([$projectTenantId])
            ->merge(\App\Support\CurrentTenant::availableTenants()->pluck('id'))
            ->unique()->values();
        $tenants = Tenant::query()->withoutGlobalScopes()->whereIn('id', $tenantIds)->get()->keyBy('id');

        $teams = static::query()->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $tenantIds)->where('active', true)
            ->with(['members' => fn ($query) => $query->withoutGlobalScope('tenant')->select('people.id', 'people.tenant_id', 'people.first_name', 'people.last_name', 'people.active')])
            ->get();
        // Ein Team einer anderen Organisation steht nur zur Verfügung, wenn mindestens eine seiner Personen für die Organisation des
        // Projekts freigeschaltet ist (Ralf, 2026-10-06); Teams der Projekt-Organisation selbst immer.
        $teams = $teams->filter(fn (Team $team) => $team->tenant_id === $projectTenantId
            || $team->members->contains(fn (Person $person) => $person->isVisibleInTenant($projectTenantId)))->values();
        // Nur wenn Teams aus mehreren Organisationen zur Auswahl stehen, wird die Organisation dazugeschrieben.
        $showOrg = $teams->pluck('tenant_id')->unique()->count() > 1;

        return $teams
            ->sort(fn (Team $a, Team $b) => $collator->compare($a->name, $b->name))
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'org' => $showOrg ? ($tenants->get($team->tenant_id)?->short_name ?? $tenants->get($team->tenant_id)?->name) : null,
                'members' => $team->members->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $person->fullName(),
                    'active' => (bool) $person->active,
                    // für die Organisation des Projekts freigeschaltet (sonst nicht zuweisbar)
                    'released' => $person->isVisibleInTenant($projectTenantId),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
