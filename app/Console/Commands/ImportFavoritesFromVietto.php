<?php

namespace App\Console\Commands;

use App\Models\Favorite;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Favoriten aus Vietto (favoriten, rein lesend). Vietto kennt
 * Favoriten je valUserID – da Vectory aktuell nur für Ralf einen echten
 * Vietto-Gegenpart hat (dort User Nr. 1), importieren wir nur dessen Zeilen.
 * Andere Vietto-User haben (noch) keinen Vectory-Account, dem man ihre
 * Favoriten zuordnen könnte.
 */
class ImportFavoritesFromVietto extends Command
{
    protected $signature = 'favorites:import-from-vietto';

    protected $description = 'Favoriten aus Vietto übernehmen (nur Ralf, Vietto-User Nr. 1)';

    private const VIETTO_USER_ID = 1;

    public function handle(): int
    {
        $tenant = Tenant::first();
        $user = User::where('email', 'ergetr65@googlemail.com')->first();

        if (! $tenant || ! $user) {
            $this->error('Kein Mandant oder passender Benutzer vorhanden.');

            return self::FAILURE;
        }

        $pns = DB::connection('vietto')->table('favoriten')
            ->where('valUserID', self::VIETTO_USER_ID)
            ->pluck('strPN');

        $projectIds = Project::query()->where('tenant_id', $tenant->id)->whereIn('source_pn', $pns)->pluck('id');

        $count = 0;
        foreach ($projectIds as $projectId) {
            Favorite::updateOrCreate(
                ['user_id' => $user->id, 'project_id' => $projectId],
                ['tenant_id' => $tenant->id]
            );
            $count++;
        }

        $this->info("{$count} Favoriten importiert.");

        return self::SUCCESS;
    }
}
