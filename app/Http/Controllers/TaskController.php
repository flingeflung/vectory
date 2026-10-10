<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Task;
use App\Models\TaskVisibility;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\OrganizationSelection;
use App\Support\ProjectColumnCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = ['source_pn', 'title', 'created_at'];

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if ($redirect = OrganizationSelection::handleOpenRequest($request, 'aufgaben')) {
            return $redirect;
        }

        // Gleiches Muster wie beim Projektfilter (ProjectController@index):
        // ein expliziter Marker unterscheidet "Filterformular abgeschickt"
        // (jetzt merken) von "nackte Navigation zu /aufgaben" (zuletzt
        // gemerkten Stand wiederherstellen) von "nur sortiert/geblättert"
        // (weder merken noch wiederherstellen - aktuelle URL gilt).
        if ($request->has('aufgabenfilter_submitted')) {
            $filters = [
                'person' => $request->query('person'),
                'hidden' => $request->boolean('hidden'),
                'all_wfs_persons' => $request->boolean('all_wfs_persons'),
                'show_inactive_people' => $request->boolean('show_inactive_people'),
                'organizations' => OrganizationSelection::fromRequest($request),
            ];
            $this->persistFilters($user, $filters);
        } elseif (! $request->hasAny(['person', 'hidden', 'all_wfs_persons', 'show_inactive_people', 'organizations_submitted', 'sort', 'direction', 'page'])) {
            $filters = $this->persistedFiltersFor($user);
        } else {
            $filters = [
                'person' => $request->query('person'),
                'hidden' => $request->boolean('hidden'),
                'all_wfs_persons' => $request->boolean('all_wfs_persons'),
                'show_inactive_people' => $request->boolean('show_inactive_people'),
                'organizations' => OrganizationSelection::fromRequest($request),
            ];
        }

        $organizations = OrganizationSelection::allowed($user);
        $selectedIds = OrganizationSelection::selected($filters['organizations'] ?? null, $organizations);

        $sort = in_array($request->query('sort'), self::SORTABLE_COLUMNS, true) ? $request->query('sort') : 'source_pn';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $selectedPerson = $filters['person'] ?? null;
        $showHidden = (bool) ($filters['hidden'] ?? false);
        $showAllWfsPersons = (bool) ($filters['all_wfs_persons'] ?? false);
        $showInactivePeople = (bool) ($filters['show_inactive_people'] ?? false);

        // Organisationsübergreifend (Ralf, 2026-10-09): der Mandanten-Filter entfällt, dafür gilt die gewählte
        // Organisationsauswahl - deshalb auch alle Beziehungen ohne den Mandanten-Filter laden.
        $unscoped = fn ($query) => $query->withoutGlobalScope('tenant');
        $query = Task::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tasks.tenant_id', $selectedIds)
            ->whereIn('tasks.source', [TaskSource::WorkflowStep, TaskSource::GraphicOrder])
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->select('tasks.*')
            ->with([
                'project' => $unscoped,
                'project.workflow' => $unscoped,
                'project.projectTypeSub' => $unscoped,
                'person',
                'functionGroup',
                'projectWorkflowStep' => $unscoped,
                'projectWorkflowStep.workflowStep' => $unscoped,
                'projectWorkflowStep.people' => $unscoped,
                'graphicOrder' => $unscoped,
            ]);

        // "Alle"/andere Person nur mit tasks.view_all - ohne das Recht zählt
        // die Auswahl nicht, auch wenn sie (z.B. per direktem Request) doch
        // mitgeschickt wurde.
        if ($selectedPerson && ! $user->can('tasks.view_all')) {
            $selectedPerson = null;
        }

        if ($selectedPerson === 'all') {
            // kein Personen-Filter
        } elseif ($selectedPerson) {
            $query->where('tasks.person_id', (int) $selectedPerson);
        } else {
            $query->where('tasks.person_id', $user->person?->id ?? 0);
        }

        if (! $showHidden) {
            $query->whereNotIn('tasks.id', TaskVisibility::query()->withoutGlobalScope('tenant')->where('user_id', $user->id)->pluck('task_id'));
        }

        $sortColumn = match ($sort) {
            'title' => 'projects.title',
            'created_at' => 'tasks.created_at',
            default => 'projects.source_pn',
        };
        $query->orderBy($sortColumn, $direction)->orderBy('tasks.id', $direction);

        $tasks = $query->paginate(25)->withQueryString();

        $wfsPeopleByTask = collect();
        if ($showAllWfsPersons) {
            foreach ($tasks as $task) {
                if ($task->projectWorkflowStep && $task->functionGroup) {
                    $wfsPeopleByTask[$task->id] = Task::assignedPeopleFor($task->projectWorkflowStep, $task->functionGroup);
                }
            }
        }

        return view('aufgaben.index', [
            'tasks' => $tasks,
            'sort' => $sort,
            'direction' => $direction,
            'peopleByGroup' => $this->peopleByFunctionGroup($showInactivePeople, $selectedIds),
            'organizations' => $organizations,
            'selectedOrganizationIds' => $selectedIds,
            'currentTenantId' => CurrentTenant::id(),
            'selectedPerson' => $selectedPerson,
            'showHidden' => $showHidden,
            'showAllWfsPersons' => $showAllWfsPersons,
            'showInactivePeople' => $showInactivePeople,
            'hiddenTaskIds' => TaskVisibility::query()->withoutGlobalScope('tenant')->where('user_id', $user->id)->pluck('task_id')->all(),
            'wfsPeopleByTask' => $wfsPeopleByTask,
        ]);
    }

    public function toggleVisibility(Request $request, int $task): Response
    {
        $user = $request->user();
        $task = Task::query()->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', OrganizationSelection::allowed($user)->pluck('id'))
            ->findOrFail($task);
        $existing = TaskVisibility::query()->withoutGlobalScope('tenant')->where('user_id', $user->id)->where('task_id', $task->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            TaskVisibility::create(['tenant_id' => $task->tenant_id, 'user_id' => $user->id, 'task_id' => $task->id]);
        }

        return response()->noContent();
    }

    /**
     * Personen für die Filterauswahl, gruppiert nach der Funktionsgruppe
     * ihrer tatsächlichen Aufgaben (nicht nach genereller
     * Funktionsgruppen-Mitgliedschaft) - zeigt also, "warum" jemand hier
     * überhaupt auftaucht. Eine Person mit Aufgaben in mehreren
     * Funktionsgruppen erscheint entsprechend in mehreren Gruppen.
     *
     * @return list<array{label: string, people: \Illuminate\Support\Collection<int, Person>}>
     */
    private function peopleByFunctionGroup(bool $includeInactive, \Illuminate\Support\Collection $organizationIds): array
    {
        $pairs = Task::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $organizationIds)
            ->whereIn('source', [TaskSource::WorkflowStep, TaskSource::GraphicOrder])
            ->whereNotNull('function_group_id')
            ->select('person_id', 'function_group_id')
            ->distinct()
            ->get();

        $people = Person::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('id', $pairs->pluck('person_id')->unique())
            ->where(function ($query) use ($organizationIds) {
                foreach ($organizationIds as $id) {
                    $query->orWhere(fn ($inner) => $inner->visibleInTenant((int) $id));
                }
            })
            ->visibleToRole(auth()->user()->role)
            ->when(! $includeInactive, fn ($query) => $query->where('active', true))
            ->get()
            ->keyBy('id');
        $groups = FunctionGroup::query()->withoutGlobalScope('tenant')->whereIn('id', $pairs->pluck('function_group_id')->unique())->orderBy('sort')->get()->keyBy('id');

        // Gleichnamige Funktionsgruppen verschiedener Organisationen erscheinen als eine Gruppe.
        return $pairs
            ->groupBy('function_group_id')
            ->sortBy(fn ($groupPairs, $groupId) => $groups->get($groupId)?->sort ?? PHP_INT_MAX)
            ->map(fn ($groupPairs, $groupId) => [
                'label' => $groups->get($groupId)?->name ?? __('Ohne Funktionsgruppe'),
                'people' => $groupPairs
                    ->map(fn ($pair) => $people->get($pair->person_id))
                    ->filter(),
            ])
            ->groupBy('label')
            ->map(fn ($sameLabel, $label) => [
                'label' => $label,
                'people' => $sameLabel->flatMap(fn ($group) => $group['people'])
                    ->unique('id')
                    ->sortBy(fn (Person $person) => $person->fullName())
                    ->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{person: ?string, hidden: bool, all_wfs_persons: bool, show_inactive_people: bool, organizations?: ?list<string>}
     */
    private function persistedFiltersFor(User $user): array
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);

        return $set->config['aufgaben_filter'] ?? ['person' => null, 'hidden' => false, 'all_wfs_persons' => false, 'show_inactive_people' => false];
    }

    /**
     * @param  array{person: ?string, hidden: bool, all_wfs_persons: bool, show_inactive_people: bool, organizations?: ?list<string>}  $filters
     */
    private function persistFilters(User $user, array $filters): void
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);
        $config = $set->config;
        $config['aufgaben_filter'] = $filters;
        $set->update(['config' => $config]);
    }
}
