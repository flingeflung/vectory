<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use Illuminate\Console\Command;

/**
 * Füllt die neuen echten Fremdschlüssel projects.project_type_main_id/
 * project_type_sub_id aus den alten, rohen Vietto-Legacy-Werten
 * (project_type_main/project_type_sub) - einmalig nach der Migration.
 *
 * Legacy-Wert 0 bedeutet in Vietto "nichts ausgewählt" -> bleibt null.
 * Werte ohne passenden Datensatz (in Vietto selbst gelöscht/inaktiv) ->
 * bleiben ebenfalls null, außer für den Sonderfall unten.
 *
 * Ralf-Entscheidung (2026-09-09) für Sanitär: die dort in Vietto
 * inaktiven Arten "Leitfaden"/"Praxishandbuch" werden auf die neue Art
 * "Fachbuch" umgehängt, "Z-Maß-Katalog" auf die bestehende "Datenblatt".
 */
class BackfillProjectTypeIds extends Command
{
    protected $signature = 'projects:backfill-type-ids';

    protected $description = 'project_type_main_id/project_type_sub_id aus den alten Legacy-Werten befüllen';

    /**
     * @var array<int, array<int, string>>
     */
    private array $subOverridesByTenant = [
        1 => [
            10 => 'Fachbuch',
            17 => 'Fachbuch',
            20 => 'Datenblatt',
        ],
    ];

    public function handle(): int
    {
        $mainUpdated = 0;
        $subUpdated = 0;
        $mainUnresolved = 0;
        $subUnresolved = 0;

        Project::query()->withoutGlobalScope('tenant')
            ->where(fn ($q) => $q->whereNotNull('project_type_main')->orWhereNotNull('project_type_sub'))
            ->chunkById(500, function ($projects) use (&$mainUpdated, &$subUpdated, &$mainUnresolved, &$subUnresolved) {
                $mainsByTenant = [];
                $subsByTenant = [];

                foreach ($projects as $project) {
                    $tenantId = $project->tenant_id;

                    $mainsByTenant[$tenantId] ??= ProjectTypeMain::query()->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
                    $subsByTenant[$tenantId] ??= ProjectTypeSub::query()->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)->pluck('id', 'name');
                    $subLegacyMap = ProjectTypeSub::query()->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)->whereNotNull('legacy_id')->pluck('id', 'legacy_id');

                    $updates = [];

                    if ($project->project_type_main !== null && $project->project_type_main !== 0) {
                        $mainId = $mainsByTenant[$tenantId][$project->project_type_main] ?? null;
                        if ($mainId) {
                            $updates['project_type_main_id'] = $mainId;
                            $mainUpdated++;
                        } else {
                            $mainUnresolved++;
                        }
                    }

                    if ($project->project_type_sub !== null && $project->project_type_sub !== 0) {
                        $overrideName = $this->subOverridesByTenant[$tenantId][$project->project_type_sub] ?? null;
                        $subId = $overrideName
                            ? ($subsByTenant[$tenantId][$overrideName] ?? null)
                            : ($subLegacyMap[$project->project_type_sub] ?? null);

                        if ($subId) {
                            $updates['project_type_sub_id'] = $subId;
                            $subUpdated++;
                        } else {
                            $subUnresolved++;
                        }
                    }

                    if ($updates !== []) {
                        $project->newQuery()->whereKey($project->id)->update($updates);
                    }
                }
            });

        $this->info("Kategorien verknüpft: {$mainUpdated} (unauflösbar: {$mainUnresolved})");
        $this->info("Arten verknüpft: {$subUpdated} (unauflösbar: {$subUnresolved})");

        return self::SUCCESS;
    }
}
