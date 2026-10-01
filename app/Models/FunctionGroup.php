<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
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

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->withoutGlobalScope('tenant')->where($field ?? $this->getRouteKeyName(), $value);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'function_group_member')->orderBy('sort');
    }

    public function availableTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'function_group_tenant')->withTimestamps();
    }

    public static function catalogTenantId(int $tenantId): int
    {
        if (! SystemSetting::multiTenantEnabled()) {
            return $tenantId;
        }

        return (int) (Tenant::query()->where('is_home_tenant', true)->value('id') ?? $tenantId);
    }

    public function scopeAvailableForTenant(Builder $query, int $tenantId, bool $includeInactive = true): Builder
    {
        $catalogTenantId = self::catalogTenantId($tenantId);

        return $query->withoutGlobalScope('tenant')
            ->where('function_groups.tenant_id', $catalogTenantId)
            ->when($catalogTenantId !== $tenantId, fn (Builder $query) => $query->whereHas(
                'availableTenants',
                fn (Builder $query) => $query->where('tenants.id', $tenantId),
            ))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('function_groups.active', true));
    }

    public function isAvailableForTenant(int $tenantId): bool
    {
        return self::query()->availableForTenant($tenantId)->whereKey($this->id)->exists();
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
        return Person::query()
            ->withoutGlobalScope('tenant')
            ->visibleInTenant($tenantId)
            ->visibleToRole($viewerRole)
            ->whereHas('functionGroups', fn ($query) => $query->where('function_groups.id', $this->id))
            ->orderBy('sort')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
