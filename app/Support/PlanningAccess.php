<?php

namespace App\Support;

use App\Models\User;

final class PlanningAccess
{
    public static function canOpen(?User $user): bool
    {
        return self::canViewExtended($user)
            || (bool) $user?->person?->functionGroups()->exists();
    }

    public static function canViewExtended(?User $user): bool
    {
        return $user?->can('planning.view') ?? false;
    }
}
