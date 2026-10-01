<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Reines Task-Routing (Illustrator-Auswahl etc.) - hat bewusst keinen
 * Einfluss auf Rechte, siehe PermissionTemplate/Person::hasPermission().
 */
#[Fillable(['tenant_id', 'legacy_id', 'name', 'short_name', 'sort', 'active', 'is_illustration_group'])]
class FunctionGroup extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_illustration_group' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'function_group_member')->orderBy('sort');
    }

    public function workflowSteps(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowStep::class, 'workflow_step_function_group');
    }

    /**
     * Mitglieder, die im angegebenen Kunden für diese Funktionsgruppe zur
     * Auswahl stehen. Bei einem Kunden gehören dazu sowohl dessen direkt
     * zugeordnete Gruppenmitglieder als auch Mitglieder der entsprechenden
     * Heimat-Funktionsgruppe, sofern sie für den Kunden freigegeben sind.
     * Das Kürzel dient als mandantenübergreifend stabiler fachlicher Schlüssel.
     *
     * @return Collection<int, Person>
     */
    public function eligibleMembersInTenant(int $tenantId, string $viewerRole): Collection
    {
        $groupIds = collect([$this->id]);
        $homeTenantId = Tenant::query()->where('is_home_tenant', true)->value('id');

        if ($homeTenantId && (int) $homeTenantId !== $tenantId) {
            $homeGroupId = self::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $homeTenantId)
                ->where('short_name', $this->short_name)
                ->where('active', true)
                ->value('id');

            if ($homeGroupId) {
                $groupIds->push((int) $homeGroupId);
            }
        }

        return Person::query()
            ->withoutGlobalScope('tenant')
            ->visibleInTenant($tenantId)
            ->visibleToRole($viewerRole)
            ->whereHas('functionGroups', fn ($query) => $query->whereIn('function_groups.id', $groupIds))
            ->orderBy('sort')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
