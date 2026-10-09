<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Soll-Ist-Vergleich der Ressourcenplanung (Ralf, 2026-10-09): Wie viele der für Projekte verfügbaren Stunden sind schon verplant, wie viele
 * bleiben übrig - als Jahressumme und je Monat.
 * - Soll (verfügbar) = Arbeitszeit - Abwesenheit (aus dem Kalender) - Grundlast, tageweise aus ProjectPlanningCalculator::capacityByDay().
 * - Ist (verplant) = die Planstunden, die in Projekten "Geplant" oder "In Bearbeitung" auf die Personen verteilt sind, über Projektlaufzeit
 *   und Einsatzplan auf die Tage gelegt (ProjectPlanningCalculator::plannedHoursByDay()).
 * - Ausblick (Ralf, 2026-10-09): gerechnet wird nur ab einem Stichtag (Standard heute, von Hand änderbar); was davor liegt, ist ausgegeben und
 *   zählt nicht mehr (ein Rückblick Plan gegen tatsächliche Stunden kommt später als eigene Auswertung). Laufende Projekte zählen nur mit dem
 *   Teil ab dem Stichtag. Die Kapazität des ganzen Jahres steht zusätzlich als Orientierung in 'yearAvailable'.
 * - Nicht verteilt = Planstunden der Projekte, die noch keiner Person zugewiesen sind (nur Information, zählt nicht als verplant).
 */
class PlanningTargetActual
{
    /** Projektstatus, die als verplant zählen: 0 = Geplant, 1 = In Bearbeitung */
    public const COUNTED_STATUSES = [0, 1];

    public function __construct(private readonly ProjectPlanningCalculator $calculator) {}

    /**
     * @param  Collection<int, Person>  $people  sichtbare Personen der Ressourcenplanung
     * @return array{from: CarbonImmutable, yearAvailable: float, months: list<array{month: int, past: bool, available: float, planned: float, remaining: float}>, available: float, planned: float, remaining: float, utilization: float|null, unassigned: float, unassignedProjects: int}
     */
    public function forYear(Collection $people, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, int $tenantId, ?CarbonImmutable $from = null): array
    {
        $from = $from === null || $from->lessThan($yearStart) ? $yearStart : $from;
        $fromKey = $from->toDateString();
        $yearAvailable = 0.0;
        $available = array_fill(1, 12, 0.0);
        $planned = array_fill(1, 12, 0.0);

        $this->calculator->prime($people, $yearStart, $yearEnd);
        foreach ($people as $person) {
            foreach ($this->calculator->capacityByDay($person, $yearStart, $yearEnd) as $date => $values) {
                $yearAvailable += (float) $values['available'];
                if ($date >= $fromKey) {
                    $available[(int) substr($date, 5, 2)] += (float) $values['available'];
                }
            }
        }

        $assignments = ProjectPerson::query()->withoutGlobalScopes()
            ->whereIn('person_id', $people->pluck('id'))
            ->where('planned_hours', '>', 0)
            ->whereHas('project', fn ($query) => $query->withoutGlobalScopes()
                ->whereIn('status', self::COUNTED_STATUSES)->where('archived', false)
                ->whereNotIn('tenant_id', Tenant::inactiveIds())
                ->whereNotNull('start_date')->whereNotNull('end_date')
                ->whereDate('start_date', '<=', $yearEnd->toDateString())->whereDate('end_date', '>=', $fromKey))
            ->with(['project' => fn ($query) => $query->withoutGlobalScopes(), 'person' => fn ($query) => $query->withoutGlobalScopes()])
            ->get();
        foreach ($assignments as $assignment) {
            foreach ($this->calculator->plannedHoursByDay($assignment, $from, $yearEnd) as $date => $hours) {
                $planned[(int) substr($date, 5, 2)] += (float) $hours;
            }
        }

        $months = [];
        foreach (range(1, 12) as $month) {
            $months[] = [
                'month' => $month,
                'past' => CarbonImmutable::create($yearStart->year, $month, 1)->endOfMonth()->lessThan($from),
                'available' => round($available[$month], 2),
                'planned' => round($planned[$month], 2),
                'remaining' => round($available[$month] - $planned[$month], 2),
            ];
        }
        $totalAvailable = round(array_sum($available), 2);
        $totalPlanned = round(array_sum($planned), 2);

        [$unassigned, $unassignedProjects] = $this->unassigned($from, $yearEnd);

        return [
            'from' => $from,
            'yearAvailable' => round($yearAvailable, 2),
            'months' => $months,
            'available' => $totalAvailable,
            'planned' => $totalPlanned,
            'remaining' => round($totalAvailable - $totalPlanned, 2),
            'utilization' => $totalAvailable > 0 ? round($totalPlanned / $totalAvailable * 100, 1) : null,
            'unassigned' => $unassigned,
            'unassignedProjects' => $unassignedProjects,
        ];
    }

    /**
     * Planstunden der Projekte der aktiven Organisation, die noch nicht auf Personen verteilt sind.
     *
     * @return array{0: float, 1: int}
     */
    private function unassigned(CarbonImmutable $yearStart, CarbonImmutable $yearEnd): array
    {
        $sum = 0.0;
        $count = 0;
        $projects = Project::query()
            ->whereIn('status', self::COUNTED_STATUSES)->where('archived', false)
            ->whereNotNull('start_date')->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $yearEnd->toDateString())->whereDate('end_date', '>=', $yearStart->toDateString())
            ->with(['functionGroupHours', 'projectTemplate.functionGroups', 'projectPeople'])
            ->get();
        foreach ($projects as $project) {
            $plan = $project->effectivePlannedHours();
            if ($plan === null || $plan <= 0) {
                continue;
            }
            $missing = $plan - (float) $project->projectPeople->sum('planned_hours');
            if ($missing > 0.005) {
                $sum += $missing;
                $count++;
            }
        }

        return [round($sum, 2), $count];
    }
}
