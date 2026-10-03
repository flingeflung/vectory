<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Für Zuordnungen "Person ↔ Projekt/Schritt/Aufgabe" (Ralf, 2026-10-03): ist die zugeordnete Person von einer
 * deaktivierten Organisation, ist sie überall ausgeblendet - dann darf auch die Zuordnung selbst nicht mehr
 * auftauchen (sonst läuft die Oberfläche auf eine fehlende Person und bricht ab). Die Daten bleiben unverändert
 * in der Datenbank; mit der Reaktivierung ist die Zuordnung sofort wieder da.
 */
trait HidesInactiveOrganizationPersons
{
    protected static function bootHidesInactiveOrganizationPersons(): void
    {
        static::addGlobalScope('person_link_active', function (Builder $builder) {
            $inactive = Tenant::inactiveIds();
            if ($inactive === []) {
                return;
            }

            $builder->whereNotIn(
                $builder->getModel()->getTable().'.person_id',
                fn ($query) => $query->select('id')->from('people')->whereIn('tenant_id', $inactive),
            );
        });
    }
}
