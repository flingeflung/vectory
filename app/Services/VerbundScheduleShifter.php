<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Termine eines Verbunds gemeinsam verschieben (Ralf, 2026-10-09): Das Hauptprojekt klammert die Unterprojekte; hier lassen sich alle
 * Projekte um dieselbe Anzahl Arbeitstage verschieben - entweder direkt oder indem man den Start eines Projekts auf ein neues Datum legt
 * (daraus ergibt sich der Abstand in Arbeitstagen).
 *
 * Verschoben wird der Projektstart; jedes Projekt rechnet danach selbst (ProjectScheduler, Erinnerungsmails folgen). Projekte ohne Workflow,
 * z. B. ein Hauptprojekt, tragen nur Anfang und Ende und werden um dieselben Arbeitstage verschoben. Meilensteine mit festem Datum bleiben
 * stehen (die Vorschau nennt sie). Projekte ohne Startdatum, im alten Terminmodell oder ohne Zugriff werden übersprungen.
 */
class VerbundScheduleShifter
{
    public function __construct(private readonly ProjectScheduler $scheduler) {}

    /** @return Collection<int, Project> Hauptprojekt zuerst, dann die Unterprojekte nach PN */
    public function members(Project $main): Collection
    {
        return collect([$main])->concat($main->unterprojekte()->orderBy('source_pn')->get())->unique('id')->values();
    }

    /**
     * Abstand in Arbeitstagen (negativ = früher), wenn der Start des Projekts auf das Datum gelegt wird.
     */
    public function deltaForNewStart(Project $project, CarbonImmutable $newStart): int
    {
        abort_unless($project->start_date !== null, 422, __('Dieses Projekt hat noch kein Startdatum.'));
        $calendar = $this->calendar($project);
        $from = $calendar->onOrAfter($project->start_date);
        $to = $calendar->onOrAfter($newStart);

        $count = 0;
        $cursor = $from;
        $step = $to->greaterThanOrEqualTo($from) ? 1 : -1;
        while ($cursor->toDateString() !== $to->toDateString() && abs($count) < 5000) {
            $cursor = $cursor->addDays($step);
            if ($calendar->isWorkday($cursor)) {
                $count += $step;
            }
        }

        return $count;
    }

    /**
     * @return array{delta: int, projects: list<array<string, mixed>>, fixedMilestones: list<array<string, string>>, movable: int}
     */
    public function preview(Project $main, User $user, int $delta): array
    {
        $members = $this->members($main);
        $rows = [];
        foreach ($members as $project) {
            $rows[] = $this->rowFor($project, $user, $delta);
        }

        $fixed = ProjectMilestone::query()->withoutGlobalScopes()
            ->whereIn('project_id', $members->pluck('id'))->where('anchor_type', 'fixed')
            ->orderBy('project_id')->orderBy('sort')->get()
            ->map(fn (ProjectMilestone $milestone) => [
                'pn' => (string) $members->firstWhere('id', $milestone->project_id)?->source_pn,
                'name' => (string) $milestone->name,
                'date' => $milestone->date?->format('d.m.Y') ?? '–',
            ])->all();

        return [
            'delta' => $delta,
            'projects' => $rows,
            'fixedMilestones' => $fixed,
            'movable' => count(array_filter($rows, fn (array $row) => $row['reason'] === null)),
        ];
    }

    /** Verschiebt alle verschiebbaren Projekte und protokolliert das je Projekt. */
    public function apply(Project $main, User $user, int $delta): int
    {
        $members = $this->members($main)->keyBy('id');
        $rows = collect($this->preview($main, $user, $delta)['projects'])->filter(fn (array $row) => $row['reason'] === null);

        DB::transaction(function () use ($rows, $members, $delta) {
            foreach ($rows as $row) {
                $project = $members[$row['id']];
                $attributes = ['start_date' => $row['startNew']];
                if (! $project->workflow_id || (int) $project->schedule_model !== 2) {
                    $attributes['end_date'] = $row['endNew'];
                }
                $project->update($attributes);

                Activity::log($project, ActivityType::ScheduleShifted, __('Termine im Verbund um :n Arbeitstage verschoben (Projektstart :old → :new).', [
                    'n' => ($delta > 0 ? '+' : '').$delta,
                    'old' => CarbonImmutable::parse($row['startOld'])->format('d.m.Y'),
                    'new' => CarbonImmutable::parse($row['startNew'])->format('d.m.Y'),
                ]));
            }
        });

        return $rows->count();
    }

    /** @return array<string, mixed> */
    private function rowFor(Project $project, User $user, int $delta): array
    {
        $row = [
            'id' => $project->id,
            'pn' => (string) $project->source_pn,
            'title' => (string) $project->title,
            'isMain' => (int) $project->verbund_rolle === 1,
            'startOld' => $project->start_date?->toDateString(),
            'startNew' => null,
            'endOld' => $project->end_date?->toDateString(),
            'endNew' => null,
            'reason' => null,
        ];

        if (! $project->mayBeOpenedBy($user)) {
            return [...$row, 'reason' => __('Kein Zugriff auf dieses Projekt.')];
        }
        if ($project->start_date === null) {
            return [...$row, 'reason' => __('Kein Startdatum.')];
        }
        if ($project->workflow_id && (int) $project->schedule_model !== 2) {
            return [...$row, 'reason' => __('Altes Terminmodell, wird nicht verschoben.')];
        }

        $calendar = $this->calendar($project);
        $start = $calendar->shift($calendar->onOrAfter($project->start_date), $delta);
        $row['startNew'] = $start->toDateString();

        if ($project->workflow_id) {
            $plan = $this->scheduler->plan($project, $start);
            $row['endNew'] = $plan['end']?->toDateString();
        } elseif ($project->end_date !== null) {
            $row['endNew'] = $calendar->shift($calendar->onOrBefore($project->end_date), $delta)->toDateString();
        }

        return $row;
    }

    private function calendar(Project $project): WorkdayCalendar
    {
        return new WorkdayCalendar(Holiday::query()->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)->where('active', true)->pluck('date'));
    }
}
