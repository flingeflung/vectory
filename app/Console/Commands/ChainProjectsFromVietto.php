<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Tenant;
use App\Support\ViettoVersionChains;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Zieht die Vietto-Versionsketten (Stamm-ID + Position) für bereits importierte
 * Projekte nach - OHNE den Import erneut laufen zu lassen. Der Import würde
 * Titel, Zusatzfelder usw. aller Projekte überschreiben; dieser Befehl fasst
 * ausschließlich stamm_id und stamm_position an (Zuordnung über die
 * Projektnummer). Wiederholbar: die IDs werden aus dem Ketten-Schlüssel
 * abgeleitet, ein zweiter Lauf ändert nichts mehr.
 */
class ChainProjectsFromVietto extends Command
{
    protected $signature = 'projects:chain-from-vietto {--tenant= : Mandanten-ID (Standard: erster Mandant)} {--dry-run : nur auswerten, nichts schreiben}';

    protected $description = 'Setzt Stamm-ID und Kettenposition bereits importierter Projekte nach den Vietto-Versionsketten (Mat.-Nr./ODN).';

    public function handle(): int
    {
        $tenant = $this->option('tenant') ? Tenant::find($this->option('tenant')) : Tenant::first();
        if (! $tenant) {
            $this->error('Mandant nicht gefunden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('projekte')
            ->select('pn', 'strMatnr', 'strODN', 'intPublikationstyp')
            ->orderBy('pn')
            ->get();

        $map = ViettoVersionChains::build($rows, $tenant->id);

        $chains = collect($map)->groupBy('stamm_id');
        $multi = $chains->filter(fn ($members) => $members->count() > 1);

        $existing = Project::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('source_pn', array_keys($map))
            ->pluck('id', 'source_pn');

        $this->info("Mandant {$tenant->id} ({$tenant->name}): {$rows->count()} Vietto-Projekte, davon {$existing->count()} mit Mat.-Nr. oder ODN und in Vectory vorhanden.");
        $this->info("Ketten mit mehr als einer Version: {$multi->count()} ({$multi->sum(fn ($m) => $m->count())} Projekte), längste Kette: ".($multi->map->count()->max() ?? 0).'.');

        if ($this->option('dry-run')) {
            $this->warn('Trockenlauf - es wurde nichts geschrieben.');

            return self::SUCCESS;
        }

        // Direkt per Query-Builder: kein Model-Event, kein "zuletzt geändert von".
        $written = 0;
        foreach ($existing as $pn => $id) {
            DB::table('projects')->where('id', $id)->update($map[$pn]);
            $written++;
        }

        $this->info("{$written} Projekte aktualisiert.");

        return self::SUCCESS;
    }
}
