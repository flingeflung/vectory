<?php

namespace App\Http\Controllers;

use App\Models\MailTimer;
use App\Models\ProjectMilestone;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\UserPreference;
use App\Models\WorkflowStep;
use App\Services\MailTimerService;
use App\Support\CurrentTenant;
use App\Support\PlanningAccess;
use App\Support\PlanningNav;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Planung › Erinnerungen (Ralf, 2026-10-09): alle Erinnerungsmails (Mail-Timer) auf einen Blick, mit Projekt, Sendedatum, Vorlage, Bezug,
 * Empfängern und Status. Standardmäßig nur offene (geplante oder fehlgeschlagene); auf Wunsch auch gesendete und übersprungene.
 * Wer mehrere Organisationen erreicht (z. B. die Ressourcenleitung eines Dienstleisters), wählt alle auf einmal oder eine einzelne.
 * Nur lesend; angelegt und gelöscht werden Erinnerungen im Projekt.
 */
class PlanningMailTimerController extends Controller
{
    public function index(Request $request, MailTimerService $service): View
    {
        abort_unless(PlanningAccess::canViewExtended($request->user()), 403);
        PlanningNav::remember($request->user(), 'planung.erinnerungen');

        $showDone = $request->boolean('erledigte');

        // Die Wahl wird je Person gemerkt; ohne Wahl gilt "alle". Wer nur eine Organisation erreicht, sieht kein Auswahlfeld.
        $organizations = SystemSetting::multiTenantEnabled() ? CurrentTenant::availableTenants() : collect();
        if ($organizations->isEmpty()) {
            $organizations = Tenant::query()->whereKey(CurrentTenant::id())->get();
        }
        $saved = (string) (UserPreference::configFor((int) $request->user()->id, UserPreference::PLANNING_REMINDERS)['organization'] ?? 'all');
        $choice = (string) $request->query('organisation', $saved);
        $selectedId = ctype_digit($choice) && $organizations->contains('id', (int) $choice) ? (int) $choice : null;
        if ($request->has('organisation') && ($selectedId ?? 'all') != $saved) {
            UserPreference::persist((int) $request->user()->id, UserPreference::PLANNING_REMINDERS, ['organization' => $selectedId ?? 'all']);
        }
        $organizationIds = $selectedId !== null ? [$selectedId] : $organizations->pluck('id')->all();

        $timers = MailTimer::query()->withoutGlobalScope('tenant')->whereIn('tenant_id', $organizationIds)
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
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedId,
            'currentTenantId' => CurrentTenant::id(),
            'showOrganizationColumn' => $selectedId === null && $organizations->count() > 1,
        ]);
    }
}
