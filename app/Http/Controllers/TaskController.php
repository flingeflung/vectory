<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Models\Person;
use App\Models\Task;
use App\Models\TaskVisibility;
use App\Models\User;
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

    public function index(Request $request): View
    {
        $user = $request->user();

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
            ];
            $this->persistFilters($user, $filters);
        } elseif (! $request->hasAny(['person', 'hidden', 'all_wfs_persons', 'sort', 'direction', 'page'])) {
            $filters = $this->persistedFiltersFor($user);
        } else {
            $filters = [
                'person' => $request->query('person'),
                'hidden' => $request->boolean('hidden'),
                'all_wfs_persons' => $request->boolean('all_wfs_persons'),
            ];
        }

        $sort = in_array($request->query('sort'), self::SORTABLE_COLUMNS, true) ? $request->query('sort') : 'source_pn';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $selectedPerson = $filters['person'] ?? null;
        $showHidden = (bool) ($filters['hidden'] ?? false);
        $showAllWfsPersons = (bool) ($filters['all_wfs_persons'] ?? false);

        $query = Task::query()
            ->whereIn('tasks.source', [TaskSource::WorkflowStep, TaskSource::GraphicOrder])
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->select('tasks.*')
            ->with(['project.workflow', 'person', 'functionGroup', 'projectWorkflowStep.workflowStep', 'projectWorkflowStep.people', 'graphicOrder']);

        // Kein Rechtesystem für "alle Aufgaben sehen" bisher (siehe
        // DashboardTileCatalog) - die Auswahl "Alle"/andere Person steht
        // deshalb aktuell jedem offen, nicht nur Admins. Sobald es Rollen
        // gibt, muss das hier gegen ein echtes Recht geprüft werden.
        if ($selectedPerson === 'all') {
            // kein Personen-Filter
        } elseif ($selectedPerson) {
            $query->where('tasks.person_id', (int) $selectedPerson);
        } else {
            $query->where('tasks.person_id', $user->person?->id ?? 0);
        }

        if (! $showHidden) {
            $query->whereNotIn('tasks.id', TaskVisibility::query()->where('user_id', $user->id)->pluck('task_id'));
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
            'people' => Person::query()
                ->whereIn('id', Task::query()->whereIn('source', [TaskSource::WorkflowStep, TaskSource::GraphicOrder])->distinct()->pluck('person_id'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'selectedPerson' => $selectedPerson,
            'showHidden' => $showHidden,
            'showAllWfsPersons' => $showAllWfsPersons,
            'hiddenTaskIds' => TaskVisibility::query()->where('user_id', $user->id)->pluck('task_id')->all(),
            'wfsPeopleByTask' => $wfsPeopleByTask,
        ]);
    }

    public function toggleVisibility(Request $request, Task $task): Response
    {
        $user = $request->user();
        $existing = TaskVisibility::query()->where('user_id', $user->id)->where('task_id', $task->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            TaskVisibility::create(['tenant_id' => $task->tenant_id, 'user_id' => $user->id, 'task_id' => $task->id]);
        }

        return response()->noContent();
    }

    /**
     * @return array{person: ?string, hidden: bool, all_wfs_persons: bool}
     */
    private function persistedFiltersFor(User $user): array
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);

        return $set->config['aufgaben_filter'] ?? ['person' => null, 'hidden' => false, 'all_wfs_persons' => false];
    }

    /**
     * @param  array{person: ?string, hidden: bool, all_wfs_persons: bool}  $filters
     */
    private function persistFilters(User $user, array $filters): void
    {
        $set = ProjectColumnCatalog::ensureDefaultSetFor($user);
        $config = $set->config;
        $config['aufgaben_filter'] = $filters;
        $set->update(['config' => $config]);
    }
}
