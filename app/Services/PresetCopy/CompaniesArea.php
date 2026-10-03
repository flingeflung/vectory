<?php

namespace App\Services\PresetCopy;

use App\Models\Company;

/** Dienstleister (Firmen) der Organisation - nur der Katalog, keine Personen. */
class CompaniesArea extends CatalogArea
{
    public function key(): string
    {
        return 'companies';
    }

    public function label(): string
    {
        return __('Dienstleister');
    }

    protected function modelClass(): string
    {
        return Company::class;
    }

    protected function copiedColumns(): array
    {
        return ['short_name'];
    }
}
