<?php

namespace App\Services\PresetCopy;

use App\Models\BusinessUnit;

/** Geschäftsbereiche der Organisation. */
class BusinessUnitsArea extends CatalogArea
{
    public function key(): string
    {
        return 'business-units';
    }

    public function label(): string
    {
        return __('Geschäftsbereiche');
    }

    protected function modelClass(): string
    {
        return BusinessUnit::class;
    }

    protected function copiedColumns(): array
    {
        return ['active'];
    }
}
