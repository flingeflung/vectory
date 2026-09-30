<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;

class PersonTableColumnCatalog
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function available(bool $multiTenantEnabled): array
    {
        return array_values(array_filter([
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'short_name', 'label' => __('Kürzel')],
            ['key' => 'type', 'label' => __('Typ')],
            ['key' => 'resource_planning', 'label' => __('Ressourcenplanung')],
            ['key' => 'calendar', 'label' => __('Kalender')],
            $multiTenantEnabled ? ['key' => 'tenant', 'label' => __('Kunde')] : null,
            ['key' => 'company', 'label' => SystemSetting::companyLabel()],
            ['key' => 'role', 'label' => __('Rolle')],
            ['key' => 'department', 'label' => __('Abteilung')],
            ['key' => 'business_unit', 'label' => __('Geschäftsbereich')],
            ['key' => 'permission_set', 'label' => __('Rechte-Set')],
            ['key' => 'email', 'label' => __('E-Mail')],
            ['key' => 'last_login', 'label' => __('Letzter Login')],
        ]));
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function orderedFor(User $user, bool $multiTenantEnabled): array
    {
        $available = collect(self::available($multiTenantEnabled))->keyBy('key');
        $savedOrder = UserPreference::configFor($user->id, UserPreference::PEOPLE_TABLE)['column_order'] ?? [];
        $savedOrder = is_array($savedOrder) ? $savedOrder : [];

        return collect($savedOrder)
            ->filter(fn ($key) => is_string($key) && $available->has($key))
            ->unique()
            ->map(fn (string $key) => $available->get($key))
            ->concat($available->except($savedOrder)->values())
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $columnOrder
     */
    public static function persistOrder(User $user, array $columnOrder, bool $multiTenantEnabled): void
    {
        $availableKeys = array_column(self::available($multiTenantEnabled), 'key');
        $order = array_values(array_intersect(array_unique($columnOrder), $availableKeys));
        $order = array_values(array_merge($order, array_diff($availableKeys, $order)));

        UserPreference::persist($user->id, UserPreference::PEOPLE_TABLE, ['column_order' => $order]);
    }
}
