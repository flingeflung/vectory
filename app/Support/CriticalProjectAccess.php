<?php

namespace App\Support;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CriticalProjectAccess
{
    public const VIEW_ALL_PERMISSION = 'critical_projects.view_all';

    /** @return Collection<int, Tenant> */
    public static function organizations(User $user): Collection
    {
        if (! SystemSetting::multiTenantEnabled()) {
            return Tenant::query()->whereKey(CurrentTenant::id())->get();
        }

        if (AccessLevel::canAccessAllOrganizations($user)) {
            return Tenant::query()->active()->orderBy('name')->get();
        }

        if (AccessLevel::isOrganizationAdmin($user)) {
            return Tenant::query()->whereKey($user->tenant_id)->get();
        }

        return CurrentTenant::availableTenants();
    }

    public static function canViewAllProjects(User $user): bool
    {
        return AccessLevel::isAdmin($user) || $user->can(self::VIEW_ALL_PERMISSION);
    }

    public static function scopeProjects(Builder $query, User $user, Collection $organizationIds): Builder
    {
        $query->whereIn('tenant_id', $organizationIds);

        if (! self::canViewAllProjects($user)) {
            $query->whereHas('projectPeople', fn (Builder $query) => $query
                ->withoutGlobalScope('tenant')
                ->where('person_id', $user->person_id ?? 0));
        }

        return $query;
    }

    public static function canViewProject(User $user, Project $project): bool
    {
        $organizationIds = self::organizations($user)->pluck('id')->map(fn ($id) => (int) $id);
        if (! $organizationIds->contains((int) $project->tenant_id)) {
            return false;
        }

        return self::canViewAllProjects($user)
            || ($user->person_id && $project->projectPeople()
                ->withoutGlobalScope('tenant')
                ->where('person_id', $user->person_id)
                ->exists());
    }
}
