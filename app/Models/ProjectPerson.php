<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectPersonObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'function_group_id', 'person_id', 'is_primary'])]
#[ObservedBy(ProjectPersonObserver::class)]
class ProjectPerson extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Ohne Tenant-Scope: eine per Kundenzugriff freigegebene Person (siehe
     * person_tenant, FunctionGroup::members()-Ladung in ProjectController)
     * gehört einem ANDEREN Mandanten als dem aktiven - mit Scope würde die
     * Relation sie stillschweigend zu null auflösen (Ralf-Bug-Report:
     * "Attempt to read property 'active' on null" beim Speichern von
     * Projektbeteiligten mit einer mandantsfremden, aber zugriffs­berechtigten
     * Person). Gleicher Fix wie bei User::person().
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withoutGlobalScope('tenant');
    }

    public function functionGroup(): BelongsTo
    {
        return $this->belongsTo(FunctionGroup::class);
    }
}
