<?php

namespace App\Services\CriticalProjects;

use App\Models\CriticalProjectFinding;
use App\Models\CriticalProjectFindingState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CriticalProjectFindingTracker
{
    /**
     * Gleicht die aktuell berechneten Befunde mit den gespeicherten
     * Vorkommen ab. Verschwindet eine Ursache, wird ihr Vorkommen samt
     * persönlicher Zustände gelöscht. Ein späteres Wiederauftreten erhält
     * dadurch ein neues Vorkommen.
     *
     * @param  Collection<int, array{project: mixed, findings: Collection}>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function sync(Collection $rows, User $user): Collection
    {
        $projectIds = $rows->pluck('project.id')->map(fn ($id) => (int) $id)->values();

        CriticalProjectFindingState::query()
            ->where('user_id', $user->id)
            ->whereDate('hidden_until', '<', today())
            ->update(['hidden_until' => null]);
        CriticalProjectFindingState::query()
            ->where('user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->whereNull('hidden_until')
            ->delete();

        $stored = CriticalProjectFinding::query()
            ->whereIn('project_id', $projectIds)
            ->with(['states' => fn ($query) => $query->where('user_id', $user->id)])
            ->get()
            ->groupBy('project_id');

        return DB::transaction(function () use ($rows, $stored, $user) {
            return $rows->map(function (array $row) use ($stored, $user) {
                $projectId = (int) $row['project']->id;
                $byKey = $stored->get($projectId, collect())->keyBy('finding_key');
                $currentKeys = $row['findings']->pluck('key');

                $byKey->reject(fn ($occurrence, $key) => $currentKeys->contains($key))
                    ->each->delete();

                $row['findings'] = $row['findings']->map(function (array $finding) use ($byKey, $projectId, $user) {
                    $occurrence = $byKey->get($finding['key']);
                    if (! $occurrence) {
                        $occurrence = CriticalProjectFinding::query()->firstOrCreate([
                            'project_id' => $projectId,
                            'finding_key' => $finding['key'],
                            'rule_code' => $finding['code'],
                        ]);
                    }

                    $state = $occurrence->states->firstWhere('user_id', $user->id);
                    $finding['occurrence'] = $occurrence;
                    $finding['state'] = $state;
                    $finding['is_hidden'] = (bool) ($state?->hidden_until?->gte(today()));

                    return $finding;
                });

                return $row;
            });
        });
    }
}
