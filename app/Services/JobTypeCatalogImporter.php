<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Jobgruppen + Jobtypen eines anderen Kunden (Ralf, 2026-09-19:
 * "bau den Import nach dem Papierformate-Muster") - pull-basiert und
 * beliebig oft wiederholbar wie PaperFormatCatalogImporter: Gruppen
 * (gleicher Name) und Jobtypen (gleiches Kürzel) im Zielkunden werden
 * übersprungen statt dupliziert oder überschrieben. Nur aktive Jobtypen
 * werden übernommen. Personen-Zuordnungen und gebuchte Stunden gehören
 * zum Quellkunden und bleiben bewusst dort.
 */
class JobTypeCatalogImporter
{
    /**
     * @return array{groups_copied: int, groups_skipped: int, jobs_copied: int, jobs_skipped: int}
     */
    public function import(Tenant $source, Tenant $target): array
    {
        return DB::transaction(function () use ($source, $target) {
            $groupMap = [];
            $groupsCopied = 0;
            $groupsSkipped = 0;

            $existingGroups = DB::table('job_groups')->where('tenant_id', $target->id)->get()
                ->keyBy(fn ($g) => mb_strtolower($g->name));
            $nextSort = 1 + (int) DB::table('job_groups')->where('tenant_id', $target->id)->max('sort');

            foreach (DB::table('job_groups')->where('tenant_id', $source->id)->orderBy('sort')->orderBy('name')->get() as $group) {
                $existing = $existingGroups->get(mb_strtolower($group->name));
                if ($existing) {
                    $groupMap[$group->id] = $existing->id;
                    $groupsSkipped++;

                    continue;
                }

                $groupMap[$group->id] = DB::table('job_groups')->insertGetId([
                    'tenant_id' => $target->id, 'name' => $group->name, 'sort' => $nextSort++,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $groupsCopied++;
            }

            $existingCodes = DB::table('job_types')->where('tenant_id', $target->id)->pluck('code')
                ->filter()->map(fn ($code) => mb_strtolower($code))->flip();
            $jobsCopied = 0;
            $jobsSkipped = 0;

            foreach (DB::table('job_types')->where('tenant_id', $source->id)->where('active', true)->orderBy('code')->orderBy('name')->get() as $job) {
                if ($job->code !== null && $existingCodes->has(mb_strtolower($job->code))) {
                    $jobsSkipped++;

                    continue;
                }

                DB::table('job_types')->insert([
                    'tenant_id' => $target->id,
                    'job_group_id' => $job->job_group_id ? ($groupMap[$job->job_group_id] ?? null) : null,
                    'code' => $job->code, 'name' => $job->name, 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $jobsCopied++;
            }

            return [
                'groups_copied' => $groupsCopied, 'groups_skipped' => $groupsSkipped,
                'jobs_copied' => $jobsCopied, 'jobs_skipped' => $jobsSkipped,
            ];
        });
    }
}
