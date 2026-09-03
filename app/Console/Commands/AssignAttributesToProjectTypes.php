<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt aus Viettos attribute_projekttyp_cx (rein lesend), welche
 * unserer Katalog-Attribute zu welcher Projektart (project_type_sub)
 * gehören. "format" fasst Viettos drei Size-Attribute zusammen.
 */
class AssignAttributesToProjectTypes extends Command
{
    protected $signature = 'attributes:assign-project-types';

    protected $description = 'Attribut-Projektart-Zuordnung aus Vietto übernehmen';

    /**
     * @var array<string, list<string>>
     */
    private const VIETTO_ATTR_MAP = [
        'material_number' => ['MatNr'],
        'format' => ['Size offen', 'Size book', 'Size'],
        'farbe' => ['Farbigk'],
        'heftung' => ['Heftung'],
        'erstauflage' => ['Erstaufl'],
    ];

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $attributes = Attribute::query()->where('tenant_id', $tenant->id)->get()->keyBy('key');

        $allViettoKeys = collect(self::VIETTO_ATTR_MAP)->flatten()->all();

        $mappings = DB::connection('vietto')
            ->table('attribute_projekttyp_cx as cx')
            ->join('attribute as a', 'a.valID', '=', 'cx.valAttributID')
            ->whereIn('a.strAttr', $allViettoKeys)
            ->select('cx.valProjektartSubID', 'a.strAttr')
            ->get();

        $count = 0;

        foreach (self::VIETTO_ATTR_MAP as $ourKey => $viettoKeys) {
            $attribute = $attributes->get($ourKey);

            if (! $attribute) {
                $this->warn("Attribut '{$ourKey}' nicht im Katalog gefunden, übersprungen.");

                continue;
            }

            $projectTypeSubs = $mappings
                ->whereIn('strAttr', $viettoKeys)
                ->pluck('valProjektartSubID')
                ->unique();

            foreach ($projectTypeSubs as $projectTypeSub) {
                DB::table('attribute_project_type')->updateOrInsert([
                    'attribute_id' => $attribute->id,
                    'project_type_sub' => $projectTypeSub,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->info("{$count} Zuordnungen gespeichert.");

        return self::SUCCESS;
    }
}
