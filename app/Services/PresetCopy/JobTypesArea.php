<?php

namespace App\Services\PresetCopy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zeiterfassung: Jobgruppen (Identität = Name) und Jobtypen (Identität = Kürzel, nur aktive). Wer einen Jobtyp wählt,
 * bekommt seine Jobgruppe automatisch mit. Überschreiben gleicht Name und Gruppe des Jobtyps an. Personen-Zuordnungen
 * und gebuchte Stunden gehören zur Quelle und bleiben dort.
 */
class JobTypesArea implements PresetArea
{
    public function key(): string
    {
        return 'job-types';
    }

    public function label(): string
    {
        return __('Zeiterfassung (Jobtypen)');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $targetGroups = $this->groups($targetTenantId)->map(fn ($g) => mb_strtolower($g->name))->flip();
        $targetCodes = $this->jobs($targetTenantId)->map(fn ($j) => mb_strtolower((string) $j->code))->flip();
        $sourceJobs = $this->jobs($sourceTenantId);
        $items = [];

        foreach ($this->groups($sourceTenantId) as $group) {
            $items[] = [
                'key' => 'g:'.$group->id, 'label' => $group->name, 'parent' => null,
                'conflict' => $targetGroups->has(mb_strtolower($group->name)), 'renamable' => false, 'note' => null,
            ];
            foreach ($sourceJobs->where('job_group_id', $group->id) as $job) {
                $items[] = $this->jobItem($job, $targetCodes, 'g:'.$group->id);
            }
        }
        foreach ($sourceJobs->whereNull('job_group_id') as $job) {
            $items[] = $this->jobItem($job, $targetCodes, null);
        }

        return $items;
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $sourceGroups = $this->groups($sourceTenantId);
        $sourceJobs = $this->jobs($sourceTenantId);

        // Gewählte Jobtypen ziehen ihre Jobgruppe nach.
        $wantedGroups = collect(array_keys($choices))->filter(fn ($k) => str_starts_with($k, 'g:'))->map(fn ($k) => (int) substr($k, 2));
        foreach (array_keys($choices) as $key) {
            if (str_starts_with($key, 'j:') && ($job = $sourceJobs->firstWhere('id', (int) substr($key, 2))) && $job->job_group_id) {
                $wantedGroups->push($job->job_group_id);
            }
        }

        $groupMap = [];
        foreach ($sourceGroups->whereIn('id', $wantedGroups->unique()->all()) as $group) {
            $existing = $this->groups($targetTenantId)->first(fn ($g) => mb_strtolower($g->name) === mb_strtolower($group->name));
            $explicit = isset($choices['g:'.$group->id]);
            if ($existing) {
                $groupMap[$group->id] = $existing->id;
                if ($explicit) {
                    $report->add($this->label(), $group->name, __('Jobgruppe gibt es schon - unverändert'));
                }

                continue;
            }
            $groupMap[$group->id] = DB::table('job_groups')->insertGetId([
                'tenant_id' => $targetTenantId, 'name' => $group->name, 'sort' => 1 + (int) DB::table('job_groups')->where('tenant_id', $targetTenantId)->max('sort'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $report->add($this->label(), $group->name, $explicit ? __('Jobgruppe angelegt') : __('Jobgruppe angelegt (als Voraussetzung)'));
        }

        foreach ($sourceJobs as $job) {
            $choice = $choices['j:'.$job->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $label = trim(($job->code ? $job->code.' ' : '').$job->name);
            $data = ['name' => $job->name, 'job_group_id' => $job->job_group_id ? ($groupMap[$job->job_group_id] ?? null) : null];
            $existing = $job->code === null ? null
                : $this->jobs($targetTenantId)->first(fn ($j) => mb_strtolower((string) $j->code) === mb_strtolower($job->code));

            if ($existing) {
                if ($choice === 'overwrite') {
                    DB::table('job_types')->where('id', $existing->id)->where('tenant_id', $targetTenantId)->update($data + ['updated_at' => now()]);
                    $report->add($this->label(), $label, __('überschrieben'));
                } else {
                    $report->add($this->label(), $label, __('übersprungen (gibt es schon)'));
                }

                continue;
            }

            DB::table('job_types')->insert($data + [
                'tenant_id' => $targetTenantId, 'code' => $job->code, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $report->add($this->label(), $label, __('angelegt'));
        }
    }

    private function jobItem(object $job, Collection $targetCodes, ?string $parent): array
    {
        return [
            'key' => 'j:'.$job->id, 'label' => trim(($job->code ? $job->code.' – ' : '').$job->name), 'parent' => $parent,
            'conflict' => $job->code !== null && $targetCodes->has(mb_strtolower($job->code)), 'renamable' => false, 'note' => null,
        ];
    }

    /** @return Collection<int, object> */
    private function groups(int $tenantId): Collection
    {
        return DB::table('job_groups')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get();
    }

    /** @return Collection<int, object> Nur aktive Jobtypen */
    private function jobs(int $tenantId): Collection
    {
        return DB::table('job_types')->where('tenant_id', $tenantId)->where('active', true)->orderBy('code')->orderBy('name')->get();
    }
}
