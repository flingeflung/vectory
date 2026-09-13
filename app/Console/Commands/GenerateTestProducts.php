<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Mini-PIM-Testdaten für die "Produkte"-Seite (Ralf, 2026-09-13) - die
 * echten Daten kommen später über eine PIM-Schnittstelle (eigenes Projekt
 * mit dem jeweiligen Kunden), bis dahin 2000 Zufallsprodukte pro Kunde.
 * Wiederholt ausführbar: räumt vorher die Testdaten dieses Kunden weg statt
 * sie zu duplizieren.
 */
class GenerateTestProducts extends Command
{
    protected $signature = 'produkte:testdaten {tenant : Tenant-ID} {--count=2000 : Anzahl Produkte}';

    protected $description = 'Mini-PIM-Testdaten (Produktgruppen + Zufallsprodukte) für einen Kunden erzeugen';

    /**
     * Ralf, 2026-09-13: genau diese sechs Gruppen, Nummer+Name fest
     * vorgegeben (kein Zufall).
     */
    private const GROUPS = [
        ['number' => 'A1', 'name' => 'Waschmaschinen'],
        ['number' => 'F1', 'name' => 'Wäschetrockner'],
        ['number' => 'Z3', 'name' => 'Geschirrspüler'],
        ['number' => 'D7', 'name' => 'Heißluftfritteusen'],
        ['number' => 'G5', 'name' => 'Mikrowellen'],
        ['number' => 'U9', 'name' => 'Backöfen'],
    ];

    /**
     * Bausteine für Fantasie-Produktnamen (10-20 Zeichen, siehe randomName())
     * - rein zur Optik, keine echten Produktbezeichnungen.
     */
    private const NAME_PARTS = [
        'Kompakt', 'Digital', 'Profi', 'Master', 'Vario', 'Comfort', 'Silent', 'Turbo', 'Eco',
        'Premium', 'Ultra', 'Classic', 'Style', 'Smart', 'Xpert', 'Flex', 'Duo', 'Mini', 'Quadro',
        'Solo', 'Aqua', 'Therm', 'Vento', 'Cristal', 'Nova', 'Pulso', 'Delta', 'Sigma', 'Orbit', 'Fusion',
    ];

    /**
     * Ralf: "so jedes 5. Produkt ca. befüllen mit Texten wie 'mit
     * Schurkspitzel' oder 'geeignet für Knorkspurzel'" - eigene
     * Fantasiewörter statt echter Fachbegriffe, per Vorlage+Wort kombiniert
     * für Abwechslung statt nur der zwei genannten Beispiele.
     */
    private const EXTRA_TEXT_TEMPLATES = ['mit :word', 'geeignet für :word', 'inkl. :word', 'für den Einsatz mit :word', 'ohne :word'];

    private const EXTRA_TEXT_WORDS = [
        'Schurkspitzel', 'Knorkspurzel', 'Flimmerdüse', 'Bratzelklammer', 'Wusselrad',
        'Plimmerkontakt', 'Schnorxel', 'Krimpelventil', 'Dussellager', 'Flauschmodul',
        'Zwirbeldichtung', 'Murkelsensor', 'Trapselklappe', 'Quirlfassung', 'Nibbelstecker',
    ];

    public function handle(): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));
        if (! $tenant) {
            $this->error('Kunde nicht gefunden.');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');

        $groups = collect(self::GROUPS)->map(fn (array $group, int $index) => ProductGroup::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => $group['number']],
            ['name' => $group['name'], 'sort' => $index]
        ));

        Product::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->delete();

        $rows = [];
        $now = now();
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'tenant_id' => $tenant->id,
                'product_group_id' => $groups->random()->id,
                'product_number' => 'P-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'name' => $this->randomName(),
                'extra_text' => random_int(1, 5) === 1 ? $this->randomExtraText() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        collect($rows)->chunk(500)->each(fn ($chunk) => Product::withoutGlobalScope('tenant')->insert($chunk->all()));

        $this->info("Fertig: {$count} Produkte + ".count(self::GROUPS)." Produktgruppen für {$tenant->name}.");

        return self::SUCCESS;
    }

    private function randomName(): string
    {
        $name = self::NAME_PARTS[array_rand(self::NAME_PARTS)].self::NAME_PARTS[array_rand(self::NAME_PARTS)];

        // Noch einen dritten Baustein dranhängen, solange Platz ist - sonst
        // landen alle Namen im unteren Teil der geforderten 10-20-Zeichen-
        // Spanne (zwei Bausteine allein erreichen nie mehr als ~14 Zeichen).
        while (mb_strlen($name) < 20 && random_int(0, 1) === 1) {
            $candidate = $name.self::NAME_PARTS[array_rand(self::NAME_PARTS)];
            if (mb_strlen($candidate) > 20) {
                break;
            }
            $name = $candidate;
        }

        if (mb_strlen($name) > 20) {
            $name = mb_substr($name, 0, 20);
        }
        while (mb_strlen($name) < 10) {
            $name .= (string) random_int(0, 9);
        }

        return $name;
    }

    private function randomExtraText(): string
    {
        $template = self::EXTRA_TEXT_TEMPLATES[array_rand(self::EXTRA_TEXT_TEMPLATES)];
        $word = self::EXTRA_TEXT_WORDS[array_rand(self::EXTRA_TEXT_WORDS)];

        return str_replace(':word', $word, $template);
    }
}
