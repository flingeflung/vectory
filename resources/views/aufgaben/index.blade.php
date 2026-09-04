<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Aufgaben') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <form method="GET" action="{{ route('aufgaben') }}" class="mb-3 flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <input type="hidden" name="aufgabenfilter_submitted" value="1">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('Personen') }}:</span>
                    <select name="person" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        <option value="" @selected(! $selectedPerson)>{{ __('– Eigene –') }}</option>
                        <option value="all" @selected($selectedPerson === 'all')>{{ __('– Alle –') }}</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected($selectedPerson === (string) $person->id)>{{ $person->fullName() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-1.5 text-gray-700">
                    <input type="checkbox" name="hidden" value="1" onchange="this.form.submit()" class="rounded border-gray-300" @checked($showHidden)>
                    {{ __('Ausgeblendete zeigen') }}
                </label>

                <label class="flex items-center gap-1.5 text-gray-700" title="{{ __('Alle Personen dieses Workflow-Schritts zeigen') }}">
                    <input type="checkbox" name="all_wfs_persons" value="1" onchange="this.form.submit()" class="rounded border-gray-300" @checked($showAllWfsPersons)>
                    {{ __('Alle WFS-Personen zeigen') }}
                </label>
            </form>

            <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                {{ __(':count Aufgabe(n) gefunden', ['count' => $tasks->total()]) }}
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col">
                <div class="flex-1 min-h-0 overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Vis.') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Typ') }}</th>
                                <x-sortable-th field="source_pn" :sort="$sort" :direction="$direction">{{ __('PN') }}</x-sortable-th>
                                <x-sortable-th field="title" :sort="$sort" :direction="$direction">{{ __('Projektbez.') }}</x-sortable-th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Workflow') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Funktionsgruppe') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ $showAllWfsPersons ? __('WFS-Personen') : __('Person') }}</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Aufgabe/WF-Schritt') }}</th>
                                <x-sortable-th field="created_at" :sort="$sort" :direction="$direction">{{ __('vom') }}</x-sortable-th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tasks as $task)
                                @php
                                    $project = $task->project;
                                    $typeSub = $project?->project_type_sub_model;
                                    $step = $task->projectWorkflowStep?->workflowStep;
                                @endphp
                                <tr
                                    x-data="{ visible: {{ in_array($task->id, $hiddenTaskIds, true) ? 'false' : 'true' }} }"
                                    x-show="visible || {{ $showHidden ? 'true' : 'false' }}"
                                    x-transition
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <input
                                            type="checkbox"
                                            x-model="visible"
                                            @change="fetch({{ \Illuminate\Support\Js::from(route('aufgaben.visibility', $task)) }}, { method: 'POST', headers: { 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} } })"
                                            class="rounded border-gray-300"
                                            title="{{ __('Sichtbar/ausgeblendet') }}"
                                        >
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($typeSub?->smallSymbol())
                                            <img src="{{ asset('images/dashboard-icons/'.$typeSub->smallSymbol()) }}" alt="" class="h-3 w-auto shrink-0">
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        @if ($project)
                                            <x-pn-link :project="$project" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 max-w-xs truncate">{{ $project?->title }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $project?->workflow?->name ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $task->functionGroup?->short_name ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        @if ($showAllWfsPersons)
                                            {{ ($wfsPeopleByTask->get($task->id) ?? collect())->map->fullName()->implode(', ') ?: '–' }}
                                        @else
                                            {{ $task->person?->fullName() ?? '–' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-900">{{ $step?->short_title ?? $step?->title ?? '–' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $task->created_at?->format('d.m.Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-6 text-center text-gray-500">{{ __('Keine Aufgaben vorhanden.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="shrink-0 px-4 py-3 border-t border-gray-100">
                    {{ $tasks->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
