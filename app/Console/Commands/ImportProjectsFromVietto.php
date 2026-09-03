<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Tenant;
use Faker\Factory as FakerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importiert Projekte aus der Vietto-Referenz-DB (rein lesend, Connection
 * "vietto") in Vectory. Der Titel (strTitle) wird unverändert übernommen -
 * auf expliziten Wunsch (echte Produkt-/Doku-Bezeichnungen statt Lorem
 * Ipsum). Andere personenbezogene/produktidentifizierende Freitextfelder
 * (Codename, Initiator, Systemmodell, Materialnummer, Bemerkung) werden
 * weiterhin durch Faker-Daten ersetzt, die pro Vietto-PN geseedet sind
 * (reproduzierbar bei erneutem Lauf). Strukturelle Felder (Status, Termine,
 * Typ-Codes, Version, Flags) bleiben unverändert, damit Volumen und
 * Verteilung realistisch bleiben.
 */
class ImportProjectsFromVietto extends Command
{
    protected $signature = 'projects:import-from-vietto {--limit= : Nur die ersten N Projekte importieren (zum Testen)}';

    protected $description = 'Projekte aus der Vietto-Referenz-DB anonymisiert nach Vectory importieren';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden. Bitte zuerst die Migrationen/Seeder ausführen.');

            return self::FAILURE;
        }

        $query = DB::connection('vietto')->table('projekte')->orderBy('pn');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $rows = $query->get();

        $this->info("{$rows->count()} Projekte in Vietto gefunden.");

        $faker = FakerFactory::create('de_DE');
        $bar = $this->output->createProgressBar($rows->count());

        // Vietto enthält vereinzelt technisch ungültige Legacy-Daten (z.B. "-0001-11-30").
        $sanitizeDate = function (?string $value): ?string {
            if (! $value || ! preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $matches)) {
                return null;
            }

            return ((int) $matches[1]) < 1000 ? null : $value;
        };

        foreach ($rows as $row) {
            $faker->seed(crc32($row->pn));

            Project::updateOrCreate(
                ['tenant_id' => $tenant->id, 'source_pn' => $row->pn],
                [
                    'title' => $row->strTitle ?: $faker->sentence(4),
                    'codename' => $row->strCodename !== null && $row->strCodename !== '' ? $faker->word() : null,
                    'initiator' => $row->strInitiator !== null && $row->strInitiator !== '' ? $faker->name() : null,
                    'system_model' => $row->strSystemModell !== null && $row->strSystemModell !== '' ? $faker->words(2, true) : null,
                    'construction_year' => $row->strBaujahr ?: null,
                    'project_type_main' => $row->projIDmain,
                    'project_type_sub' => $row->projIDsub,
                    'version' => $row->intVersion,
                    'status' => $row->intBearbStatus,
                    'archived' => (bool) $row->blnIsInArchiv,
                    'localization' => (bool) $row->blnLokalisierung,
                    'publication_date' => $sanitizeDate($row->dtgPublDate),
                    'start_date' => $sanitizeDate($row->dtgStartDate),
                    'end_date' => $sanitizeDate($row->dtgEndDate),
                    'remarks' => $row->txtBemerk !== null && $row->txtBemerk !== '' ? $faker->realText(200) : null,

                    // Variable, projekttyp-abhängige Attribute (Analogon zu Viettos attribute/
                    // attribute_projekttyp_cx, "orangener Bereich" im Vietto-Formular).
                    // Materialnummer ist produktidentifizierend -> anonymisiert.
                    'attributes' => array_filter([
                        'material_number' => $row->strMatnr !== null && $row->strMatnr !== '' ? $faker->numerify('#########') : null,
                        'farbe' => $row->intFarbe,
                        'heftung' => $row->intHeftung,
                        'erstauflage' => $row->intErstauflage,
                        'format' => $row->strSizeFertigesDok ?: $row->strSizeBogen,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import abgeschlossen.');

        return self::SUCCESS;
    }
}
