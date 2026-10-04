<?php

namespace Tests\Unit;

use App\Models\ProjectWorkflowStep;
use App\Services\WorkflowScheduleCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WorkflowScheduleHolidayTest extends TestCase
{
    private function step(int $id, int $days): ProjectWorkflowStep
    {
        $step = new ProjectWorkflowStep(['duration_days' => $days]);
        $step->id = $id;

        return $step;
    }

    public function test_holidays_are_skipped_forwards_and_backwards(): void
    {
        $steps = collect([$this->step(1, 1), $this->step(2, 2), $this->step(3, 1)]);

        // Referenz Montag 1.3.2027; Dienstag 2.3. ist Feiertag
        $without = (new WorkflowScheduleCalculator)->recalculate($steps, $steps[0], Carbon::parse('2027-03-01'));
        $with = (new WorkflowScheduleCalculator(['2027-03-02']))->recalculate($steps, $steps[0], Carbon::parse('2027-03-01'));

        $this->assertSame('2027-03-03', $without[2]->toDateString(), 'Ohne Feiertag: zwei Arbeitstage nach Montag = Mittwoch.');
        $this->assertSame('2027-03-04', $with[2]->toDateString(), 'Mit Feiertag am Dienstag rutscht der Termin auf Donnerstag.');

        // rückwärts: Referenz ist der letzte Schritt, Dauer 2 des Folgeschritts, Feiertag am Dienstag dazwischen
        $back = (new WorkflowScheduleCalculator(['2027-03-02']))->recalculate($steps, $steps[2], Carbon::parse('2027-03-04'));
        $this->assertSame('2027-03-03', $back[2]->toDateString());
        $this->assertSame('2027-02-26', $back[1]->toDateString(), 'Zwei Arbeitstage zurück von Mittwoch: Dienstag (Feiertag) zählt nicht, also Montag und Freitag.');
    }

    public function test_weekends_are_still_skipped_without_holidays(): void
    {
        $steps = collect([$this->step(1, 1), $this->step(2, 1)]);

        $result = (new WorkflowScheduleCalculator)->recalculate($steps, $steps[0], Carbon::parse('2027-03-05')); // Freitag

        $this->assertSame('2027-03-08', $result[2]->toDateString());
    }
}
