<?php

namespace App\Services\CriticalProjects;

use App\Models\Project;
use Illuminate\Support\Collection;

class CriticalProjectEvaluator
{
    /**
     * Der Katalog ist zugleich die zentrale Quelle für Filter und Regelwerk.
     * Neue Regeln erhalten einen stabilen Code und werden in evaluate() ergänzt.
     */
    public function definitions(): Collection
    {
        return collect([
            ['code' => 'schedule.overdue', 'area' => __('Termine'), 'title' => __('Termin überschritten'), 'severity' => 'critical', 'severity_label' => __('Kritisch'), 'description' => __('Ein noch nicht abgeschlossener Termin liegt in der Vergangenheit.'), 'exclusion' => __('Heute fällige und bereits abgeschlossene Termine.'), 'solution' => __('Termin und weiteren Ablauf prüfen; Termin bei Bedarf aktualisieren.')],
            ['code' => 'schedule.current_missing', 'area' => __('Termine'), 'title' => __('Termin im Workflow-Schritt fehlt'), 'severity' => 'watch', 'severity_label' => __('Beobachten'), 'description' => __('Ein noch nicht abgeschlossener Workflow-Schritt verlangt einen Termin, es ist aber keiner eingetragen. Beim aktuellen Schritt besteht Handlungsbedarf; fehlende Start- und Endtermine sind kritisch.'), 'exclusion' => __('Abgeschlossene Workflow-Schritte und Schritte ohne Terminpflicht, sofern sie nicht Projektstart oder Projektende festlegen.'), 'solution' => __('Termin im Dialog „Termine berechnen“ festlegen oder berechnen.')],
            ['code' => 'staffing.missing', 'area' => __('Projektbeteiligte'), 'title' => __('Projektperson fehlt'), 'severity' => 'watch', 'severity_label' => __('Beobachten'), 'description' => __('Mindestens eine im Workflow benötigte Funktionsgruppe ist nicht besetzt.'), 'exclusion' => __('Funktionsgruppen außerhalb des zugewiesenen Workflows.'), 'solution' => __('Eine geeignete Projektperson für die Funktionsgruppe zuweisen.')],
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

        foreach ($workflowSteps->filter(fn ($step) => ! $step->completed_at && $step->due_date && $step->due_date->lt($today)) as $step) {
            $findings->push($this->finding($definition('schedule.overdue'),
                __(':step war am :date fällig.', ['step' => $step->workflowStep->title, 'date' => $step->due_date->format('d.m.Y')]),
                null,
                'workflow-step:'.$step->id));
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
