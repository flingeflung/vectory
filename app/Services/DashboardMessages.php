<?php

namespace App\Services;

use App\Enums\GraphicOrderStatus;
use App\Enums\TaskSource;
use App\Models\Announcement;
use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\Task;
use App\Models\User;
use App\Services\CriticalProjects\CriticalProjectOverview;
use App\Support\OrganizationSelection;
use Illuminate\Support\Collection;

/**
 * Meldungen der Startseite (Ralf, 2026-10-10): „Wo muss ich heute hinschauen?“ - je Meldung eine Zeile mit Zahl und
 * Sprung zur passenden Seite, nur wenn die Zahl größer als 0 ist. Gezählt wird über alle Organisationen, die der
 * Benutzer einbeziehen darf.
 */
class DashboardMessages
{
    public function __construct(private readonly CriticalProjectOverview $criticalProjects) {}

    /**
     * @return Collection<int, array{key: string, tone: string, count: ?int, text: string, detail?: ?string, hint: string, url: ?string}>
     */
    public function forUser(User $user): Collection
    {
        $organizationIds = OrganizationSelection::allowed($user)->pluck('id')->map(fn ($id) => (int) $id);
        $messages = Announcement::query()
            ->active()
            ->forTenant((int) $user->tenant_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $announcement) => [
                'key' => 'announcement-'.$announcement->id,
                'tone' => 'blue',
                'count' => null,
                'text' => $announcement->title,
                'detail' => $announcement->text,
                'hint' => __('Mitteilung Ihrer Administration'),
                'url' => null,
            ]);

        if ($user->can('project.view') && $user->person_id) {
            $summary = $this->criticalProjects->summaryForUser($user, $organizationIds);
            if ($summary['projects'] > 0) {
                $text = trans_choice('kritisches Projekt|kritische Projekte', $summary['projects']);
                if ($summary['blocked'] > 0) {
                    $text .= ', '.__('davon :count mit Handlungsbedarf', ['count' => $summary['blocked']]);
                }
                $messages->push([
                    'key' => 'critical_projects',
                    'tone' => $summary['blocked'] > 0 ? 'red' : 'orange',
                    'count' => $summary['projects'],
                    'text' => $text,
                    'hint' => __('Projekte, bei denen Sie als Person beteiligt sind und die kritische Punkte haben. Ausgeblendete Punkte zählen nicht mit.'),
                    'url' => route('critical-projects.index'),
                ]);
            }
        }

        if ($user->person_id) {
            $approvals = $this->pendingApprovals($user, $organizationIds);
            if ($approvals > 0) {
                $messages->push([
                    'key' => 'approvals',
                    'tone' => 'amber',
                    'count' => $approvals,
                    'text' => trans_choice('Freigabe wartet auf Sie|Freigaben warten auf Sie', $approvals),
                    'hint' => __('Projekte, bei denen Sie im aktuellen Workflow-Schritt zuständig sind und die Freigabe noch nicht erteilt wurde.'),
                    'url' => route('aufgaben'),
                ]);
            }

            $unassigned = $this->unassignedIllustrationOrders($user, $organizationIds);
            if ($unassigned > 0) {
                $openStatuses = collect(GraphicOrderStatus::cases())->filter(fn ($status) => $status->isOpen())->map(fn ($status) => $status->value)->values()->all();
                $messages->push([
                    'key' => 'illustration_orders',
                    'tone' => 'sky',
                    'count' => $unassigned,
                    'text' => trans_choice('Illustrationsauftrag ohne Illustrator|Illustrationsaufträge ohne Illustrator', $unassigned),
                    'hint' => __('Offene Aufträge ohne Illustrator: Sie sehen die, die Sie erteilt haben, die Ihrer Projekte und - als Illustrator - alle in Ihrer Organisation.'),
                    'url' => route('illustrationen', ['illustrationsfilter_submitted' => 1, 'status' => $openStatuses, 'illustrator' => ['none']]),
                ]);
            }
        }

        return $messages;
    }

    /**
     * Projekte, in deren aktuellem Freigabe-Schritt der Benutzer zuständig ist und die Freigabe noch aussteht.
     *
     * @param  Collection<int, int>  $organizationIds
     */
    private function pendingApprovals(User $user, Collection $organizationIds): int
    {
        return Task::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $organizationIds)
            ->where('person_id', $user->person_id)
            ->where('source', TaskSource::WorkflowStep)
            ->whereHas('project', fn ($project) => $project->withoutGlobalScope('tenant')->whereIn('status', [0, 1]))
            ->whereHas('projectWorkflowStep', fn ($step) => $step->withoutGlobalScope('tenant')
                ->whereNull('milestone_done_at')
                ->whereHas('workflowStep', fn ($workflowStep) => $workflowStep->withoutGlobalScope('tenant')->where('js_function', 'wfs_freigabe')))
            ->distinct()
            ->count('project_id');
    }

    /**
     * Offene Illustrationsaufträge ohne Illustrator, die den Benutzer betreffen: von ihm erteilt, in einem seiner
     * Projekte oder - wenn er zur Illustrations-Funktionsgruppe der Organisation gehört - alle der Organisation.
     *
     * @param  Collection<int, int>  $organizationIds
     */
    private function unassignedIllustrationOrders(User $user, Collection $organizationIds): int
    {
        $personId = (int) $user->person_id;
        $poolTenantIds = $organizationIds->filter(fn ($tenantId) => FunctionGroup::query()
            ->availableForTenant($tenantId, false)
            ->where('is_illustration_group', true)
            ->whereHas('members', fn ($members) => $members->withoutGlobalScope('tenant')->where('people.id', $personId))
            ->exists())->values();
        $openStatuses = collect(GraphicOrderStatus::cases())->filter(fn ($status) => $status->isOpen())->map(fn ($status) => $status->value)->values()->all();

        return GraphicOrder::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $organizationIds)
            ->whereNull('illustrator_person_id')
            ->whereIn('graphic_order_status_id', $openStatuses)
            ->whereHas('project', fn ($project) => $project->withoutGlobalScope('tenant')->whereIn('status', [0, 1]))
            ->where(function ($orders) use ($personId, $poolTenantIds) {
                $orders->where('initiated_by_person_id', $personId)
                    ->orWhereIn('tenant_id', $poolTenantIds)
                    ->orWhereHas('project', fn ($project) => $project->withoutGlobalScope('tenant')
                        ->whereHas('projectPeople', fn ($people) => $people->withoutGlobalScope('tenant')->where('person_id', $personId)));
            })
            ->count();
    }
}
