<?php

namespace App\Services\CriticalProjects;

use App\Models\CalendarEntry;
use App\Models\Holiday;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CriticalProjectEvaluator
{
    /** @var array<string, Collection<int, string>> */
    private array $holidayCache = [];

    /**
     * Der Katalog ist zugleich die zentrale Quelle für Filter und Regelwerk.
     * Neue Regeln erhalten einen stabilen Code und werden in evaluate() ergänzt.
     */
    public function definitions(): Collection
    {
        return collect([
            ['code' => 'schedule.overdue', 'area' => __('Termine'), 'title' => __('Termin überschritten'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Ein noch nicht abgeschlossener Termin liegt in der Vergangenheit.'), 'exclusion' => __('Heute fällige und bereits abgeschlossene Termine.'), 'solution' => __('Termin und weiteren Ablauf prüfen; Termin bei Bedarf aktualisieren.')],
            ['code' => 'schedule.current_missing', 'area' => __('Termine'), 'title' => __('Termin im Workflow-Schritt fehlt'), 'severity' => 'watch', 'severity_label' => __('Beobachten'), 'description' => __('Ein noch nicht abgeschlossener Workflow-Schritt verlangt einen Termin, es ist aber keiner eingetragen. Beim aktuellen Schritt besteht Handlungsbedarf; fehlende Start- und Endtermine sind kritisch.'), 'exclusion' => __('Abgeschlossene Workflow-Schritte und Schritte ohne Terminpflicht, sofern sie nicht Projektstart oder Projektende festlegen.'), 'solution' => __('Termin im Dialog „Termine berechnen“ festlegen oder berechnen.')],
            ['code' => 'schedule.milestone_target', 'area' => __('Termine'), 'title' => __('Projektende nach einem Ziel-Meilenstein'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Ein Meilenstein mit der Prüfung „Ziel“ liegt vor dem berechneten Projektende; das Projekt wird bis dahin nicht fertig.'), 'exclusion' => __('Meilensteine ohne Prüfung sowie Projekte ohne berechnetes Projektende.'), 'solution' => __('Dauern kürzen, den Projektstart vorziehen (Zielscheibe im Ablaufplan) oder den Meilenstein verschieben.')],
            ['code' => 'schedule.milestone_prerequisite', 'area' => __('Termine'), 'title' => __('Projektstart vor einem Voraussetzungs-Meilenstein'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Ein Meilenstein mit der Prüfung „Voraussetzung“ liegt nach dem Projektstart; das Projekt beginnt vor seiner Voraussetzung.'), 'exclusion' => __('Meilensteine ohne Prüfung sowie Projekte ohne Projektstart.'), 'solution' => __('Projektstart nach hinten setzen (Zielscheibe im Ablaufplan) oder den Meilenstein verschieben.')],
            ['code' => 'staffing.missing', 'area' => __('Projektbeteiligte'), 'title' => __('Projektperson fehlt'), 'severity' => 'watch', 'severity_label' => __('Beobachten'), 'description' => __('Mindestens eine im Workflow benötigte Funktionsgruppe ist nicht besetzt.'), 'exclusion' => __('Funktionsgruppen außerhalb des zugewiesenen Workflows.'), 'solution' => __('Eine geeignete Projektperson für die Funktionsgruppe zuweisen.')],
            ['code' => 'staffing.person_absent', 'area' => __('Projektbeteiligte'), 'title' => __('Projektperson länger abwesend'), 'severity' => 'watch', 'severity_label' => __('Beobachten'), 'description' => __('Eine Projektperson ist mindestens drei Arbeitstage abwesend. Ab mehr als fünf Arbeitstagen besteht Handlungsbedarf.'), 'exclusion' => __('Abwesenheiten von höchstens zwei Arbeitstagen; Wochenenden und aktive Feiertage der Organisation der Person zählen nicht als Arbeitstage.'), 'solution' => __('Vertretung organisieren oder Projektbesetzung anpassen.')],
            ['code' => 'staffing.person_unavailable', 'area' => __('Projektbeteiligte'), 'title' => __('Projektperson nicht verfügbar'), 'severity' => 'blocked', 'severity_label' => __('Handlungsbedarf'), 'description' => __('Eine zugewiesene Projektperson ist inaktiv oder ihr Beschäftigungsende ist erreicht.'), 'exclusion' => __('Aktive Personen ohne erreichtes Beschäftigungsende.'), 'solution' => __('Vertretung organisieren oder Projektbesetzung anpassen.')],
            ['code' => 'project.start_still_planned', 'area' => __('Projektstatus'), 'title' => __('Projektstart erreicht, Status noch geplant'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Das Startdatum ist erreicht oder überschritten, das Projekt steht aber weiterhin auf „Geplant“.'), 'exclusion' => __('Projekte ohne Startdatum oder mit anderem Status.'), 'solution' => __('Projektstatus und tatsächlichen Start prüfen.')],
            ['code' => 'budget.plan_exceeded', 'area' => __('Planstunden'), 'title' => __('Planstunden überschritten'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Die gebuchten Stunden liegen über den Planstunden des Projekts.'), 'exclusion' => __('Projekte ohne Planstunden und Projekte innerhalb des Budgets.'), 'solution' => __('Mehraufwand prüfen und gegebenenfalls zusätzliches Budget mit dem Kunden abstimmen.')],
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function evaluate(Project $project, float $bookedHours): Collection
    {
        $findings = collect();
        $today = today();
        $definition = fn (string $code) => $this->definitions()->firstWhere('code', $code);
        $workflowSteps = $project->projectWorkflowSteps
            ->filter(fn ($step) => $step->workflowStep?->workflow_id === $project->workflow_id);

        // Neues Terminmodell: jede Phase hat ein berechnetes Ende, als Termin zählen nur benannte Phasenenden
        $isNewModel = (int) $project->schedule_model === 2;
        foreach ($workflowSteps->filter(fn ($step) => ! $step->completed_at && $step->due_date && $step->due_date->lt($today)
            && (! $isNewModel || $step->workflowStep?->has_due_date)) as $step) {
            $findings->push($this->finding($definition('schedule.overdue'),
                __(':step war am :date fällig.', ['step' => $step->workflowStep->title, 'date' => $step->due_date->format('d.m.Y')]),
                null,
                'workflow-step:'.$step->id));
        }

        // Meilensteine mit Prüfung (neues Terminmodell): Ziel = Projekt soll bis dahin fertig sein, Voraussetzung = Projekt darf erst danach beginnen
        if ($isNewModel) {
            foreach ($project->projectMilestones->filter(fn ($milestone) => $milestone->date && $milestone->check_direction) as $milestone) {
                $date = $milestone->date;
                if ($milestone->check_direction === 'target' && $project->end_date && $project->end_date->gt($date)) {
                    $findings->push($this->finding($definition('schedule.milestone_target'),
                        __('Das Projektende (:end) liegt nach dem Meilenstein „:name“ (:date).', ['end' => $project->end_date->format('d.m.Y'), 'name' => $milestone->name, 'date' => $date->format('d.m.Y')]),
                        null, 'milestone:'.$milestone->id));
                }
                if ($milestone->check_direction === 'prerequisite' && $project->start_date && $project->start_date->lt($date)) {
                    $findings->push($this->finding($definition('schedule.milestone_prerequisite'),
                        __('Das Projekt beginnt (:start) vor dem Meilenstein „:name“ (:date).', ['start' => $project->start_date->format('d.m.Y'), 'name' => $milestone->name, 'date' => $date->format('d.m.Y')]),
                        null, 'milestone:'.$milestone->id));
                }
            }
        }

        $current = $workflowSteps->firstWhere('is_current', true);
        $missingDateSteps = $workflowSteps->filter(fn ($step) => ! $step->completed_at
            && ! $step->due_date
            && ($step->workflowStep?->has_due_date || $step->effectiveIsStart() || $step->effectiveIsEnd()));
        foreach ($missingDateSteps as $step) {
            $severity = $step->is_current
                ? 'blocked'
                : ($step->effectiveIsStart() || $step->effectiveIsEnd() ? 'critical' : 'watch');
            $findings->push($this->finding(
                $definition('schedule.current_missing'),
                __('Termin für „:step“ fehlt.', ['step' => $step->workflowStep->title]),
                $severity,
                'workflow-step:'.$step->id,
            ));
        }

        $assignedGroupIds = $project->projectPeople->pluck('function_group_id')->filter()->unique();
        $requiredGroups = $workflowSteps->flatMap(fn ($step) => $step->workflowStep?->functionGroups ?? collect())->unique('id');
        $currentGroupIds = $current?->workflowStep?->functionGroups?->pluck('id') ?? collect();
        foreach ($requiredGroups->reject(fn ($group) => $assignedGroupIds->contains($group->id)) as $group) {
            $rule = $definition('staffing.missing');
            $severity = $currentGroupIds->contains($group->id) ? 'blocked' : 'watch';
            $findings->push($this->finding($rule, __('Funktionsgruppe „:group“ ist nicht besetzt.', ['group' => $group->name]), $severity, 'function-group:'.$group->id));
        }

        foreach ($project->projectPeople->filter(fn ($assignment) => $assignment->person)->groupBy('person_id') as $assignments) {
            $person = $assignments->first()->person;
            $groups = $assignments->pluck('functionGroup.short_name')->filter()->unique()->join(', ');
            $groupSuffix = $groups !== '' ? __(' (Funktionsgruppe: :groups)', ['groups' => $groups]) : '';

            if (! $person->active || ($person->end_date && $person->end_date->lte($today))) {
                $detail = ! $person->active
                    ? __(':person ist inaktiv.', ['person' => $person->fullName()])
                    : __('Das Beschäftigungsende von :person wurde am :date erreicht.', ['person' => $person->fullName(), 'date' => $person->end_date->format('d.m.Y')]);
                $findings->push($this->finding(
                    $definition('staffing.person_unavailable'),
                    $detail.$groupSuffix,
                    'blocked',
                    'person:'.$person->id,
                ));

                continue;
            }

            $currentAbsences = $person->calendarEntries
                ->where('type', CalendarEntry::TYPE_ABSENCE)
                ->filter(fn (CalendarEntry $entry) => $entry->starts_on->lte($today) && $entry->ends_on->gte($today));
            foreach ($currentAbsences as $absence) {
                $workdays = $this->workdays($absence->starts_on, $absence->ends_on, (int) $person->tenant_id);
                if ($workdays < 3) {
                    continue;
                }

                $findings->push($this->finding(
                    $definition('staffing.person_absent'),
                    __(':person ist vom :from bis :until abwesend (:days Arbeitstage).', [
                        'person' => $person->fullName(),
                        'from' => $absence->starts_on->format('d.m.Y'),
                        'until' => $absence->ends_on->format('d.m.Y'),
                        'days' => $workdays,
                    ]).$groupSuffix,
                    $workdays > 5 ? 'blocked' : 'watch',
                    'calendar-entry:'.$absence->id,
                ));
            }
        }

        if ((int) $project->status === 0 && $project->start_date && $project->start_date->lte($today)) {
            $severity = $project->start_date->lt($today) ? 'critical' : 'watch';
            $findings->push($this->finding($definition('project.start_still_planned'),
                __('Projektstart: :date.', ['date' => $project->start_date->format('d.m.Y')]), $severity, 'project'));
        }

        $plannedHours = $project->effectivePlannedHours();
        if ($plannedHours !== null && $bookedHours > $plannedHours + 0.0001) {
            $findings->push($this->finding($definition('budget.plan_exceeded'),
                __('Plan :plan h, gebucht :booked h, Überschreitung :difference h.', [
                    'plan' => number_format($plannedHours, 2, ',', '.'),
                    'booked' => number_format($bookedHours, 2, ',', '.'),
                    'difference' => number_format($bookedHours - $plannedHours, 2, ',', '.'),
                ]), null, 'project'));
        }

        return $findings;
    }

    private function workdays(CarbonInterface $start, CarbonInterface $end, int $tenantId): int
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);
        $holidays = $this->holidays($tenantId, $start->year, $end->year);
        $count = 0;

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $count++;
            }
        }

        return $count;
    }

    /** @return Collection<int, string> */
    private function holidays(int $tenantId, int $fromYear, int $toYear): Collection
    {
        $dates = collect();
        foreach (range($fromYear, $toYear) as $year) {
            $key = $tenantId.'-'.$year;
            $dates = $dates->merge($this->holidayCache[$key] ??= Holiday::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->whereYear('date', $year)
                ->pluck('date')
                ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString()));
        }

        return $dates;
    }

    /** @param array<string, mixed> $rule */
    private function finding(array $rule, string $detail, ?string $severity = null, string $subject = 'project'): array
    {
        $severity ??= $rule['severity'];

        return [
            'key' => $rule['code'].':'.$subject,
            'code' => $rule['code'],
            'area' => $rule['area'],
            'title' => $rule['title'],
            'severity' => $severity,
            'severity_label' => match ($severity) {
                'blocked' => __('Handlungsbedarf'),
                'critical' => __('Kritisch'),
                default => __('Beobachten'),
            },
            'rank' => ['watch' => 1, 'critical' => 2, 'blocked' => 3][$severity],
            'detail' => $detail,
            'solution' => $rule['solution'],
        ];
    }
}
