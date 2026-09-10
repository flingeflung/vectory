<?php

namespace App\Observers;

use App\Models\Attribute;
use App\Services\AttributeColumnManager;

/**
 * Räumt die automatisch generierte Datenbankspalte auf, sobald ein
 * Attribut gelöscht wird (siehe AttributeColumnManager) - als Observer statt
 * nur im Controller, damit das auch bei jedem anderen Löschweg greift
 * (z.B. Cascade-Delete, Tinker), nicht nur beim expliziten "Löschen"-Klick.
 */
class AttributeObserver
{
    public function deleted(Attribute $attribute): void
    {
        app(AttributeColumnManager::class)->dropColumnIfUnused($attribute);
    }
}
