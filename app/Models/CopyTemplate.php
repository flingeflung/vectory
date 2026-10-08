<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Projekte kopieren"-Vorlage (Ralf, 2026-09-11): legt fest, welche
 * Attribute (feste Felder + Zusatzfelder, siehe Attribute::SYSTEM_FIELDS)
 * beim Kopieren eines Projekts übernommen werden.
 */
#[Fillable(['tenant_id', 'name', 'sort', 'planning_parts'])]
class CopyTemplate extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['planning_parts' => 'array'];
    }

    /**
     * Planungs-Bereiche, die mitkopiert werden (Schlüssel aus PlanningTransfer: durations, planned_hours, milestones).
     * Der Workflow und die Projektbeteiligten laufen über die Felder der Vorlage.
     *
     * @return list<string>
     */
    public function planningParts(): array
    {
        return array_values(array_intersect($this->planning_parts ?? [], self::COPYABLE_PLANNING_PARTS));
    }

    public const COPYABLE_PLANNING_PARTS = ['durations', 'planned_hours', 'milestones'];

    /**
     * Bewusst nicht "attributes()" genannt - kollidiert mit Eloquents
     * eigenem internen $attributes-Array für die Modell-Spalten selbst.
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'copy_template_attribute');
    }
}
