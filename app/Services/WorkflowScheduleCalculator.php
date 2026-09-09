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
 * - Nur Wochenenden übersprungen, keine Feiertage - Viettos Feiertagsliste
 *   ist hart codiert auf Deutschland (teils sogar nur rheinischen Karneval),
 *   für ein international ausgerichtetes Vectory unpassend. Eigener
 *   Feiertagskalender pro Land/Mandant wäre ein separates, späteres Thema.
 * - Rückwärtsrechnung nutzt die ECHTE Dauer des Folgeschritts, nicht wie in
 *   Vietto pauschal 1 Arbeitstag (dortiger Bug, vorwärts wird korrekt die
 *   echte Dauer verwendet - Ralf: "selbstverständlich ohne Bug").
 */
class WorkflowScheduleCalculator
{
    public static function addWorkingDays(Carbon $date, int $days): Carbon
    {
        return $date->copy()->addWeekdays(max($days, 1));
    }

    public static function subWorkingDays(Carbon $date, int $days): Carbon
    {
        return $date->copy()->subWeekdays(max($days, 1));
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
            $dates[$step->id] = self::addWorkingDays($previousDate, $step->effectiveDurationDays());
        }

        for ($i = $refIndex - 1; $i >= 0; $i--) {
            $step = $ordered[$i];
            $nextStep = $ordered[$i + 1];
            $nextDate = $dates[$nextStep->id];
            $dates[$step->id] = self::subWorkingDays($nextDate, $nextStep->effectiveDurationDays());
        }

        return $dates;
    }
}
