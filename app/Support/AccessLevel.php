<?php

namespace App\Support;

use App\Models\User;

final class AccessLevel
{
    public const USER = 'user';

    public const ORGANIZATION_ADMIN = 'organization_admin';

    public const CENTRAL_ADMIN = 'central_admin';

    public const SUPER_ADMIN = 'super_admin';

    public static function isSuperAdmin(?User $user): bool
    {
        return $user?->role === self::SUPER_ADMIN;
    }

    public static function isCentralAdmin(?User $user): bool
    {
        return $user?->role === self::CENTRAL_ADMIN;
    }

    public static function isOrganizationAdmin(?User $user): bool
    {
        return $user?->role === self::ORGANIZATION_ADMIN;
    }

    public static function isAdmin(?User $user): bool
    {
        return in_array($user?->role, [self::ORGANIZATION_ADMIN, self::CENTRAL_ADMIN, self::SUPER_ADMIN], true);
    }

    public static function canAccessAllOrganizations(?User $user): bool
    {
        return self::isSuperAdmin($user) || self::isCentralAdmin($user);
    }

    public static function label(?User $user): string
    {
        return match ($user?->role) {
            self::SUPER_ADMIN => __('Super-Admin'),
            self::CENTRAL_ADMIN => __('Zentral-Admin'),
            self::ORGANIZATION_ADMIN => __('Organisations-Admin'),
            default => __('User'),
        };
    }
}
