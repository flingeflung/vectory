<?php

namespace App\Services\PresetCopy;

use App\Models\Department;

/** Abteilungen der Organisation. */
class DepartmentsArea extends CatalogArea
{
    public function key(): string
    {
        return 'departments';
    }

    public function label(): string
    {
        return __('Abteilungen');
    }

    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function copiedColumns(): array
    {
        return ['short_name', 'active'];
    }
}
