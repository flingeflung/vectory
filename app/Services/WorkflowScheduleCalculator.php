<?php

namespace App\Services;

use App\Models\ProjectWorkflowStep;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Terminberechnung für die Workflow-Schritte eines Projekts (Vietto-Vorbild:
 * ajax_workflow_edittermine.php, "neu berechnen"). Ausgehend von einem
 * Referenz-Schritt mit gültigem Datum werden alle anderen Termine-Schritte
 * (has_due_date an der Vorlage) vorwärts/rückwärts über ihre jeweilige Dauer
 * in Arbeitstagen neu berechnet.
 *
 * Zwei bewusste Abweichungen von Vietto (mit Ralf abgestimmt):
 * - Feiertage kommen aus dem Feiertagskalender der Organisation (Admin > Feiertage), nicht aus
 *   einer fest einprogrammierten Liste wie in Vietto (Ralf, 2026-10-04: "Wenn wir innerhalb der
 *   Projekte mit konkreten Daten rechnen, müssen die Feiertage mit einfließen"). Wochenenden
 *   und aktive Feiertage werden übersprungen.
 * - Rückwärtsrechnung nutzt die ECHTE Dauer des Folgeschritts, nicht wie in
 *   Vietto pauschal 1 Arbeitstag (dortiger Bug, vorwärts wird korrekt die
 *   echte Dauer verwendet - Ralf: "selbstverständlich ohne Bug").
 */
class WorkflowScheduleCalculator
{
    /** @var array<string, true> Feiertage als Y-m-d-Schlüssel */
    private array $holidays;

    /** @param  iterable<string>  $holidays  Feiertage der Organisation (Y-m-d) */
    public function __construct(iterable $holidays = [])
    {
        $this->holidays = [];
        foreach ($holidays as $date) {
            $this->holidays[Carbon::parse($date)->toDateString()] = true;
        }
    }

    public function addWorkingDays(Carbon $date, int $days): Carbon
    {
        return $this->shiftWorkingDays($date, max($days, 1), 1);
    }

    public function subWorkingDays(Carbon $date, int $days): Carbon
    {
        return $this->shiftWorkingDays($date, max($days, 1), -1);
    }

    private function shiftWorkingDays(Carbon $date, int $days, int $direction): Carbon
    {
        $cursor = $date->copy();
        while ($days > 0) {
            $cursor->addDays($direction);
            if ($cursor->isWeekday() && ! isset($this->holidays[$cursor->toDateString()])) {
                $days--;
            }
        }

        return $cursor;
    }

    /**
     * @param  Collection<int, ProjectWorkflowStep>  $steps  Sortiert nach sort, nur has_due_date-Schritte.
     * @return Collection<int, Carbon> Vorgeschlagene Termine, indiziert nach ProjectWorkflowStep-ID (inkl. des Referenz-Schritts selbst, unverändert).
     */
    public function recalculate(Collection $steps, ProjectWorkflowStep $reference, Carbon $referenceDate): Collection
    {
        $ordered = $steps->values();
        $refIndex = $ordered->search(fn (ProjectWorkflowStep $step) => $step->id === $reference->id);

        $dates = collect();
        $dates[$reference->id] = $referenceDate->copy();

        for ($i = $refIndex + 1; $i < $ordered->count(); $i++) {
            $step = $ordered[$i];
            $previousDate = $dates[$ordered[$i - 1]->id];
            $dates[$step->id] = $this->addWorkingDays($previousDate, $step->effectiveDurationDays());
        }

        for ($i = $refIndex - 1; $i >= 0; $i--) {
            $step = $ordered[$i];
            $nextStep = $ordered[$i + 1];
            $nextDate = $dates[$nextStep->id];
            $dates[$step->id] = $this->subWorkingDays($nextDate, $nextStep->effectiveDurationDays());
        }

        return $dates;
    }
}
