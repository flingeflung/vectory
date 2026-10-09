<?php

namespace App\Http\Controllers;

use App\Models\MailTimer;
use App\Models\ProjectMilestone;
use App\Models\WorkflowStep;
use App\Services\MailTimerService;
use App\Support\PlanningAccess;
use App\Support\PlanningNav;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Planung › Erinnerungen (Ralf, 2026-10-09): alle Erinnerungsmails (Mail-Timer) der Organisation auf einen Blick, mit Projekt, Sendedatum,
 * Vorlage, Bezug, Empfängern und Status. Standardmäßig nur offene (geplante oder fehlgeschlagene); auf Wunsch auch gesendete und übersprungene.
 * Nur lesend; angelegt und gelöscht werden Erinnerungen im Projekt.
 */
class PlanningMailTimerController extends Controller
{
    public function index(Request $request, MailTimerService $service): View
    {
        abort_unless(PlanningAccess::canViewExtended($request->user()), 403);
        PlanningNav::remember($request->user(), 'planung.erinnerungen');

        $showDone = $request->boolean('erledigte');

        $timers = MailTimer::query()
            ->when(! $showDone, fn ($query) => $query->whereNull('sent_at')->whereNull('skipped_at'))
            ->with(['project', 'mailTemplate', 'step'])
            ->get()
            // Beendete, verworfene und archivierte Projekte bekommen keine Erinnerungen mehr; nur Projekte, die der Nutzer öffnen darf
            ->filter(fn (MailTimer $timer) => $timer->project
                && $timer->project->mayBeOpenedBy($request->user())
                && ($showDone || (! $timer->project->archived && in_array((int) $timer->project->status, [0, 1], true))))
            ->sortBy(fn (MailTimer $timer) => ($timer->send_date?->format('Y-m-d') ?? '9999-99-99').' '.$timer->project->source_pn.' '.str_pad((string) $timer->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $stepTitles = WorkflowStep::query()->withoutGlobalScopes()->whereIn('id', $timers->pluck('reference_step_id')->filter()->unique())->pluck('title', 'id')->all();
        $milestoneNames = ProjectMilestone::query()->withoutGlobalScopes()->whereIn('id', $timers->pluck('reference_milestone_id')->filter()->unique())->pluck('name', 'id')->all();

        return view('planning.erinnerungen', [
            'rows' => $timers->map(fn (MailTimer $timer) => [
                'timer' => $timer,
                'project' => $timer->project,
                'rule' => $service->ruleText($timer, $stepTitles, $milestoneNames),
                'groups' => $service->groupNames($timer),
                'recipients' => count($service->recipients($timer)),
            ]),
            'showDone' => $showDone,
        ]);
    }
}
