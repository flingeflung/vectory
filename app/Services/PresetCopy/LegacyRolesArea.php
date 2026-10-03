<?php

namespace App\Services\PresetCopy;

use App\Models\LegacyRole;

/** Rollen (fachliche Funktionen) der Organisation. */
class LegacyRolesArea extends CatalogArea
{
    public function key(): string
    {
        return 'legacy-roles';
    }

    public function label(): string
    {
        return __('Rollen');
    }

    protected function modelClass(): string
    {
        return LegacyRole::class;
    }

    protected function copiedColumns(): array
    {
        return [];
    }
}
