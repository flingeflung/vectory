<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        $attributes = [
            ['key' => 'material_number', 'label' => 'Materialnummer', 'data_type' => 'text', 'sort' => 10],
            ['key' => 'format', 'label' => 'Format', 'data_type' => 'text', 'sort' => 20],
            ['key' => 'farbe', 'label' => 'Farbigkeit', 'data_type' => 'number', 'sort' => 30],
            ['key' => 'heftung', 'label' => 'Heftung', 'data_type' => 'number', 'sort' => 40],
            ['key' => 'erstauflage', 'label' => 'Erstauflage', 'data_type' => 'number', 'sort' => 50],
        ];

        foreach ($attributes as $attribute) {
            Attribute::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $attribute['key']],
                $attribute
            );
        }
    }
}
