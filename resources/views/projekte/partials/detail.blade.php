@php
    $statusOptions = [0 => __('Geplant'), 1 => __('In Bearbeitung'), 2 => __('Beendet'), 3 => __('Verworfen')];
    $isOverlay = $overlay ?? false;
    $navParams = fn ($target) => array_filter(['project' => $target, 'sort' => $sort ?? null, 'direction' => $direction ?? 'asc', 'filter' => $filters ?? []]);

    // Gemeinsamer Button-Look fürs ganze Overlay: gefüllter grauer
    // Hintergrund grenzt Buttons klar von weißen Eingabefeldern ab
    // (die nur einen Rahmen haben).
    $secondaryBtn = 'inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200';
    $secondaryBtnDisabled = 'inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-300 cursor-not-allowed';
@endphp

<div class="{{ $isOverlay ? 'flex h-full min-h-0 flex-col' : 'p-4' }}">
    <div
        class="flex items-center justify-between gap-2 {{ $isOverlay ? 'shrink-0 rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-2 cursor-move select-none' : 'mb-1' }}"
        @if ($isOverlay) data-drag-handle title="{{ __('Ziehen zum Verschieben') }}" @endif
    >
        <div class="flex items-center gap-2">
            <span class="text-base font-semibold text-gray-900">{{ $project->source_pn }}</span>

            <x-favorite-star :project="$project" :is-favorite="$project->isFavoritedBy(auth()->user())" />

            <div class="flex items-center text-gray-500">
                @php
                    $navBtn = 'p-1.5 rounded hover:bg-gray-100 hover:text-gray-800 disabled:opacity-25 disabled:hover:bg-transparent disabled:hover:text-gray-500';
                    $chevronLeft = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>';
                    $chevronRight = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>';
                @endphp
                @if ($isOverlay)
                    <button
                        type="button"
                        @disabled(! $previousProject)
                        onclick="window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $previousProject?->id ?? 'null' }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } }))"
                        class="{{ $navBtn }}"
                        title="{{ __('Vorheriges Projekt') }}"
                    >{!! $chevronLeft !!}</button>
                    <button
                        type="button"
                        @disabled(! $nextProject)
                        onclick="window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $nextProject?->id ?? 'null' }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } }))"
                        class="{{ $navBtn }}"
                        title="{{ __('Nächstes Projekt') }}"
                    >{!! $chevronRight !!}</button>
                @else
                    <a
                        href="{{ $previousProject ? route('projekte.show', $navParams($previousProject)) : '#' }}"
                        class="{{ $navBtn }} {{ ! $previousProject ? 'pointer-events-none opacity-25' : '' }}"
                        title="{{ __('Vorheriges Projekt') }}"
                    >{!! $chevronLeft !!}</a>
                    <a
                        href="{{ $nextProject ? route('projekte.show', $navParams($nextProject)) : '#' }}"
                        class="{{ $navBtn }} {{ ! $nextProject ? 'pointer-events-none opacity-25' : '' }}"
                        title="{{ __('Nächstes Projekt') }}"
                    >{!! $chevronRight !!}</a>
                @endif
            </div>
        </div>

        {{-- Platz für weitere Aktions-Buttons (Aufgabe zuweisen, Grafikauftrag, Sperrmail, ...) – folgt später. --}}
        <div class="flex-1"></div>

        @if ($isOverlay)
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-overlay' }))"
                class="text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Schließen') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @else
            <a href="{{ route('projekte') }}" class="text-xs text-gray-600 hover:text-gray-900">
                &laquo; {{ __('Zur Übersicht') }}
            </a>
        @endif
    </div>

    <div
        class="{{ $isOverlay ? 'flex min-h-0 flex-1 flex-col' : '' }}"
        x-data="{ activeTab: 'details' }"
        x-init="
            {{-- Fragment wird beim Blättern (Vor/Zurück-Pfeile) komplett neu geladen -
                 Alpine-State geht dabei verloren. Über window gemerkt, damit der
                 gewählte Tab dabei erhalten bleibt statt immer auf Details zu springen. --}}
            activeTab = window.projectOverlayActiveTab || 'details';
            $watch('activeTab', value => window.projectOverlayActiveTab = value)
        "
    >
        {{-- Titel, Meldungen und Reiter bleiben fix stehen - nur der Inhalt
             darunter soll scrollen. --}}
        <div class="{{ $isOverlay ? 'shrink-0 px-4 pt-3' : '' }}">
            <div class="mb-3 text-sm text-gray-600">{{ $project->title }}</div>

            @if (($justSaved ?? false))
                <div class="mb-2 rounded bg-green-50 px-2 py-1 text-xs text-green-700">{{ __('Gespeichert.') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-2 rounded bg-red-50 px-2 py-1 text-xs text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3 flex gap-4 border-b border-gray-200 text-sm">
                <button type="button" @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Details') }}</button>
                <button type="button" @click="activeTab = 'vorgaenge'" :class="activeTab === 'vorgaenge' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Vorgänge') }}</button>
                <button type="button" @click="activeTab = 'workflow_steps'" :class="activeTab === 'workflow_steps' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Workflow') }}</button>
            </div>
        </div>

        <div class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto px-4 pb-3' : '' }}">
        <div x-show="activeTab === 'details'">
        <form id="project-detail-form" method="POST" action="{{ route('projekte.update', $project) }}" class="space-y-4 text-sm">
            @csrf
            @method('patch')

        <div class="flex gap-3">
        <div class="w-1 shrink-0 rounded-full" style="background-color: #09f" title="{{ __('Stammdaten') }}"></div>
        <div class="min-w-0 flex-1 space-y-3">

        <div>
            <label class="block text-xs text-gray-500">{{ __('Bezeichnung') }}</label>
            <input type="text" name="title" value="{{ old('title', $project->title) }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm" required>
        </div>

        <div class="grid grid-cols-4 gap-2">
            <div class="col-span-2">
                <label class="block text-xs text-gray-500">{{ __('Modell/System') }}</label>
                <input type="text" name="system_model" value="{{ old('system_model', $project->system_model) }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Baujahr') }}</label>
                <input type="text" name="construction_year" value="{{ old('construction_year', $project->construction_year) }}" class="mt-0.5 w-full max-w-[100px] rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Version') }}</label>
                <input type="number" name="version" value="{{ old('version', $project->version) }}" class="mt-0.5 w-full max-w-[80px] rounded border-gray-300 py-1 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <div class="col-span-2">
                <label class="block text-xs text-gray-500">{{ __('Initiator') }}</label>
                <input type="text" name="initiator" value="{{ old('initiator', $project->initiator) }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Status') }}</label>
                <select name="status" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project->status) == $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <div>
                <label class="block text-xs text-gray-500">{{ __('Start') }}</label>
                <input type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" class="mt-0.5 rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Ende') }}</label>
                <input type="date" name="end_date" value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}" class="mt-0.5 rounded border-gray-300 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500">{{ __('Publikation') }}</label>
                <input type="date" name="publication_date" value="{{ old('publication_date', $project->publication_date?->format('Y-m-d')) }}" class="mt-0.5 rounded border-gray-300 py-1 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
            <textarea name="remarks" rows="2" class="mt-0.5 w-full rounded border-gray-300 text-sm">{{ old('remarks', $project->remarks) }}</textarea>
        </div>

        <div
            x-data="{
                editingMarkets: false,
                marketSets: {{ \Illuminate\Support\Js::from($marketSets->mapWithKeys(fn ($set) => [$set->id => $set->markets->pluck('id')])) }},
                selectedSet: '',
                applySet() {
                    if (! this.selectedSet) return;
                    const checkboxes = [...this.$root.querySelectorAll('input[name=\'markets[]\']')];

                    if (this.selectedSet === '__all__') {
                        checkboxes.forEach(cb => cb.checked = true);
                    } else if (this.selectedSet === '__none__') {
                        checkboxes.forEach(cb => cb.checked = false);
                    } else {
                        const ids = this.marketSets[this.selectedSet] ?? [];
                        checkboxes.forEach(cb => cb.checked = ids.includes(Number(cb.value)));
                    }

                    this.selectedSet = '';
                },
            }"
        >
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">{{ __('Markt') }}</label>
                <button type="button" @click="editingMarkets = !editingMarkets" class="{{ $secondaryBtn }}">
                    <span x-show="!editingMarkets">{{ __('Ändern') }}</span>
                    <span x-show="editingMarkets" x-cloak>{{ __('Fertig') }}</span>
                </button>
            </div>

            <div x-show="!editingMarkets">
                @if ($project->markets->isEmpty())
                    <div class="mt-0.5 text-gray-400">&ndash; {{ __('Kein Markt zugewiesen') }} &ndash;</div>
                @else
                    <div class="mt-0.5 text-gray-700">
                        @if ($project->markets->count() > 4)
                            <span class="text-xs text-gray-500">{{ $project->markets->count() }} {{ __('Märkte') }}:</span>
                        @endif
                        @foreach ($project->markets as $market)
                            <span class="font-semibold">{{ $market->country_iso }}{{ strtolower($market->language_code) }}</span>@if ($market->no_translation)*@endif {{ $market->country_short_name }}@if (! $loop->last), @endif
                        @endforeach
                    </div>
                    @if ($project->markets->contains('no_translation', true))
                        <div class="mt-0.5 text-xs text-gray-400">* {{ __('Es wird keine Übersetzung für diesen Markt durchgeführt') }}</div>
                    @endif
                @endif
            </div>

            <div class="mt-1.5">
                @php
                    $localizationOld = old('localization');
                    $localizationValue = $localizationOld !== null ? $localizationOld : ($project->localization === null ? '' : ($project->localization ? '1' : '0'));
                @endphp
                <label class="block text-xs text-gray-500">{{ __('Übersetzung/Lokalisierung notwendig?') }}</label>
                <select name="localization" class="mt-0.5 w-48 rounded border-gray-300 py-1 text-sm">
                    <option value="" @selected($localizationValue === '')>{{ __('– nicht zugewiesen –') }}</option>
                    <option value="1" @selected($localizationValue === '1')>{{ __('Ja') }}</option>
                    <option value="0" @selected($localizationValue === '0')>{{ __('Nein') }}</option>
                </select>
            </div>

            <div x-show="editingMarkets" x-cloak class="mt-0.5">
                <div class="mb-1.5 flex items-center gap-1.5">
                    <select x-model="selectedSet" class="rounded border-gray-300 py-1 text-xs">
                        <option value="">{{ __('Standard-Märkte zuweisen') }}</option>
                        <option value="__all__">{{ __('Alle auswählen') }}</option>
                        <option value="__none__">{{ __('Alle entfernen') }}</option>
                        @if ($marketSets->isNotEmpty())
                            <optgroup label="{{ __('Gespeicherte Sets') }}">
                                @foreach ($marketSets as $set)
                                    <option value="{{ $set->id }}">{{ $set->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <button type="button" x-show="selectedSet" x-cloak @click="applySet()" class="rounded bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">
                        {{ __('Zuweisen') }}
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-1 max-h-56 overflow-y-auto rounded border border-gray-300 bg-white p-2 text-xs">
                @foreach ($allMarkets as $market)
                    <label class="flex items-center gap-1 text-gray-600">
                        <input
                            type="checkbox"
                            name="markets[]"
                            value="{{ $market->id }}"
                            class="shrink-0 rounded border-gray-300"
                            @checked($project->markets->contains('id', $market->id))
                        >
                        {{ $market->label() }}
                    </label>
                @endforeach
                </div>
            </div>
        </div>

        </div>
        </div>

        @if ($attributes->isNotEmpty())
        <div class="flex gap-3">
        <div class="w-1 shrink-0 rounded-full" style="background-color: {{ $project->attribute_section_color }}" title="{{ __('Typspezifische Attribute') }}"></div>
        <div class="min-w-0 flex-1">
            <div class="grid grid-cols-4 gap-2">
                @foreach ($attributes as $attribute)
                    <div>
                        <label class="block text-xs text-gray-500">{{ $attribute->label }}</label>
                        <input
                            type="{{ $attribute->data_type === 'number' ? 'number' : 'text' }}"
                            name="attributes[{{ $attribute->key }}]"
                            value="{{ old('attributes.'.$attribute->key, $project->attributes[$attribute->key] ?? '') }}"
                            class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm"
                        >
                    </div>
                @endforeach
            </div>
        </div>
        </div>
        @endif

        <div class="flex gap-3">
        <div class="w-1 shrink-0 rounded-full" style="background-color: #396" title="{{ __('Ablaufdaten') }}"></div>
        <div class="min-w-0 flex-1 space-y-3">

        <div x-data="{ editingWorkflow: false }">
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">{{ __('Workflow') }}</label>
                <button type="button" @click="editingWorkflow = !editingWorkflow" class="{{ $secondaryBtn }}">
                    <span x-show="!editingWorkflow">{{ __('Ändern') }}</span>
                    <span x-show="editingWorkflow" x-cloak>{{ __('Fertig') }}</span>
                </button>
            </div>

            <div x-show="!editingWorkflow" class="mt-0.5 text-gray-700">
                {{ $project->workflow?->name ?? __('– kein Workflow zugewiesen –') }}
            </div>

            <div x-show="editingWorkflow" x-cloak class="mt-0.5">
                <select name="workflow_id" class="w-full max-w-sm rounded border-gray-300 py-1 text-sm">
                    <option value="">{{ __('– kein Workflow zugewiesen –') }}</option>
                    @foreach ($availableWorkflows as $availableWorkflow)
                        <option value="{{ $availableWorkflow->id }}" @selected(old('workflow_id', $project->workflow_id) == $availableWorkflow->id)>{{ $availableWorkflow->name }}{{ ! $availableWorkflow->active ? ' ('.__('inaktiv').')' : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div x-data="{ editingPeople: false }">
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500">{{ __('Projektbeteiligte Personen') }}</label>
                <button type="button" @click="editingPeople = !editingPeople" class="{{ $secondaryBtn }}">
                    <span x-show="!editingPeople">{{ __('Ändern') }}</span>
                    <span x-show="editingPeople" x-cloak>{{ __('Fertig') }}</span>
                </button>
            </div>

            <div x-show="!editingPeople">
                @php $groupedPeople = $project->projectPeople->groupBy('function_group_id'); @endphp
                @if ($groupedPeople->isEmpty())
                    <div class="mt-0.5 text-gray-400">&ndash; {{ __('Keine Personen zugeordnet') }} &ndash;</div>
                @else
                    <div class="mt-0.5 grid grid-cols-2 gap-x-4 gap-y-0.5 text-gray-700">
                        @foreach ($allFunctionGroups as $group)
                            @php $entries = $groupedPeople->get($group->id); @endphp
                            @if ($entries)
                                <div>
                                    <span class="text-gray-500">{{ $group->short_name }}:</span>
                                    @foreach ($entries as $entry)
                                        {{ $entry->person->fullName() }}@if ($entry->is_primary)<span class="text-amber-500" title="{{ __('Erstansprechpartner') }}">&#9733;</span>@endif @if (! $loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div x-show="editingPeople" x-cloak class="mt-0.5 max-h-56 overflow-y-auto rounded border border-gray-300 bg-white p-2 text-xs space-y-2">
                @foreach ($allFunctionGroups as $group)
                    @if ($group->members->isNotEmpty())
                        @php
                            $currentEntries = $project->projectPeople->where('function_group_id', $group->id);
                            $currentPersonIds = $currentEntries->pluck('person_id')->all();
                            $currentPrimaryId = optional($currentEntries->firstWhere('is_primary', true))->person_id;
                        @endphp
                        <div>
                            <div class="mb-0.5 font-medium text-gray-600">{{ $group->name }}</div>
                            <div class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                                @foreach ($group->members as $person)
                                    <label class="flex items-center gap-1 text-gray-600">
                                        <input
                                            type="checkbox"
                                            name="project_people[{{ $group->id }}][]"
                                            value="{{ $person->id }}"
                                            class="shrink-0 rounded border-gray-300"
                                            @checked(in_array($person->id, $currentPersonIds, true))
                                        >
                                        <input
                                            type="radio"
                                            name="project_people_primary[{{ $group->id }}]"
                                            value="{{ $person->id }}"
                                            class="shrink-0"
                                            title="{{ __('Als Erstansprechpartner markieren') }}"
                                            onclick="this.previousElementSibling.checked = true"
                                            @checked($currentPrimaryId === $person->id)
                                        >
                                        {{ $person->fullName() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div>
            <label class="flex items-center gap-1 text-xs text-gray-700">
                <input type="hidden" name="archived" value="0">
                <input type="checkbox" name="archived" value="1" class="rounded border-gray-300" @checked(old('archived', $project->archived))>
                {{ __('Archiviert') }}
            </label>
        </div>

        </div>
        </div>
        </form>
        </div>

        <div x-show="activeTab === 'vorgaenge'" x-cloak class="space-y-1 text-xs text-gray-600">
            @forelse ($project->activities as $activity)
                <div>
                    <span class="text-gray-400">{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                    {{ $activity->message }}
                    @if ($activity->user)
                        <span class="text-gray-400">({{ $activity->user->name }})</span>
                    @endif
                </div>
            @empty
                <div class="text-gray-400">&ndash; {{ __('Keine Vorgänge') }} &ndash;</div>
            @endforelse
        </div>

        <div x-show="activeTab === 'workflow_steps'" x-cloak class="text-sm">
            @if (! $project->workflow)
                <div class="text-gray-400">&ndash; {{ __('Kein Workflow zugewiesen') }} &ndash;</div>
            @else
                @php
                    // Alte/andere Workflow-Generationen können noch Schritt-Zeilen für
                    // dieses Projekt haben (siehe Kommentar bei der Zuweisungslogik) -
                    // hier nur die Schritte des AKTUELL zugewiesenen Workflows zeigen.
                    $currentSteps = $project->projectWorkflowSteps
                        ->filter(fn ($pws) => $pws->workflowStep && $pws->workflowStep->workflow_id === $project->workflow_id)
                        ->sortBy('sort')
                        ->values();
                @endphp
                <div class="mb-3">
                    <span class="text-gray-500">{{ __('Workflow') }}:</span>
                    <span class="font-semibold text-gray-900">{{ $project->workflow->name }}</span>
                </div>

                @if ($currentSteps->isEmpty())
                    <div class="text-gray-400">&ndash; {{ __('Keine Schritte vorhanden') }} &ndash;</div>
                @else
                    <div class="flex flex-col items-center">
                        @foreach ($currentSteps as $pws)
                            @php
                                $step = $pws->workflowStep;
                                $isDone = $pws->completed_at !== null;
                            @endphp
                            @php
                                $peopleByGroup = $pws->people->groupBy('function_group_id');
                            @endphp
                            <div
                                x-data="{ expanded: false }"
                                class="w-full max-w-2xl rounded-md px-3 py-2 {{ $pws->is_current ? 'border-2 border-blue-500' : 'border border-gray-300' }}"
                                style="background-color: {{ $step->lifecycleColor() }}"
                            >
                                <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 flex-1 items-start gap-2">
                                    <span class="shrink-0 text-lg font-semibold text-gray-400">{{ $loop->iteration }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-900">{{ $step->title }}</div>
                                        @if ($step->functionGroups->isNotEmpty())
                                            <div class="text-xs text-gray-600">{{ $step->functionGroups->pluck('short_name')->implode(', ') }}</div>
                                        @endif

                                        @if ($step->description)
                                            <div class="mt-0.5 text-xs text-gray-700">
                                                <span x-show="!expanded" @click="expanded = true" class="cursor-pointer text-gray-500 hover:text-gray-700">&hellip;</span>
                                                <span
                                                    x-show="expanded" x-cloak
                                                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                                    @click="expanded = false"
                                                    class="cursor-pointer"
                                                >{{ $step->description }}</span>
                                            </div>
                                        @endif

                                        @if ($step->has_due_date && $step->milestone_title)
                                            <div
                                                x-data="{ value: {{ \Illuminate\Support\Js::from($pws->due_date?->format('Y-m-d')) }}, saving: false }"
                                                class="mt-1 text-xs"
                                                @click.stop
                                            >
                                                <div class="font-medium text-blue-700">{{ __('Termin') }}</div>
                                                <div class="flex items-center gap-1 text-gray-700">
                                                    <span>{{ $step->milestone_title }}:</span>
                                                    <input
                                                        type="date"
                                                        x-model="value"
                                                        :disabled="saving"
                                                        @change="
                                                            saving = true;
                                                            fetch({{ \Illuminate\Support\Js::from(route('projekte.workflow-steps.due-date', [$project, $pws])) }}, {
                                                                method: 'PATCH',
                                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                                                body: JSON.stringify({ due_date: value || null }),
                                                            }).finally(() => saving = false);
                                                        "
                                                        class="rounded border-gray-300 py-0.5 text-xs"
                                                    >
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mt-0.5 text-xs text-gray-600">
                                            @if ($isDone)
                                                {{ __('Erledigt') }}: {{ $pws->completed_at->format('d.m.Y') }}
                                            @elseif ($pws->is_current)
                                                <span class="font-medium text-blue-700">{{ __('Aktuell') }}</span>
                                                @if ($pws->started_at)
                                                    &middot; {{ __('seit') }} {{ $pws->started_at->format('d.m.Y') }}
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($step->functionGroups->isNotEmpty())
                                    <div class="shrink-0 text-xs">
                                        @foreach ($step->functionGroups as $group)
                                            @php $groupPeople = $peopleByGroup->get($group->id, collect()); @endphp
                                            <div class="mb-1">
                                                <div class="font-semibold text-gray-800">{{ $group->name }}</div>
                                                @forelse ($groupPeople as $entry)
                                                    <div class="text-gray-700">{{ $entry->person->fullName() }}</div>
                                                @empty
                                                    <div class="text-gray-400">&ndash;</div>
                                                @endforelse
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                </div>
                            </div>
                            @unless ($loop->last)
                                <svg class="h-5 w-5 shrink-0 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v10.638l3.96-4.158a.75.75 0 111.08 1.04l-5.25 5.5a.75.75 0 01-1.08 0l-5.25-5.5a.75.75 0 111.08-1.04l3.96 4.158V3.75A.75.75 0 0110 3z" clip-rule="evenodd" />
                                </svg>
                            @endunless
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
        </div>
    </div>

    @if ($isOverlay)
        <div
            x-data="{
                isDirty: false,
                showClose: true,
                showSave: false,
                switchTimeout: null,
                check() {
                    const dirty = window.projectOverlayIsDirty ? window.projectOverlayIsDirty() : true;
                    if (dirty === this.isDirty) {
                        return;
                    }
                    this.isDirty = dirty;
                    clearTimeout(this.switchTimeout);
                    // Erst die eine Seite ganz ausfaden lassen, dann erst die
                    // andere einfaden - beide gleichzeitig laufen zu lassen
                    // ließ den Schließen-Button beim Umschalten nach links
                    // 'springen', während rechts daneben schon die
                    // Speichern-Buttons standen.
                    if (dirty) {
                        this.showClose = false;
                        this.switchTimeout = setTimeout(() => { this.showSave = true; }, 150);
                    } else {
                        this.showSave = false;
                        this.switchTimeout = setTimeout(() => { this.showClose = true; }, 150);
                    }
                },
            }"
            x-init="
                const form = document.getElementById('project-detail-form');
                form?.addEventListener('input', () => check());
                form?.addEventListener('change', () => check());
                check();
            "
            class="shrink-0 rounded-b-lg border-t border-gray-200 bg-white px-4 py-2"
        >
            <div class="flex min-h-[34px] items-center justify-end">
                <button
                    type="button"
                    x-show="showClose"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-overlay' }))"
                    class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                >
                    {{ __('Schließen') }}
                </button>
                <div
                    x-show="showSave"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="flex gap-2"
                >
                    <button type="submit" form="project-detail-form" class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                        {{ __('Speichern') }}
                    </button>
                    <button type="submit" form="project-detail-form" name="close_after_save" value="1" class="rounded bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
                        {{ __('Speichern und Schließen') }}
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="flex justify-end gap-2 pt-1">
            <button type="submit" form="project-detail-form" class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                {{ __('Speichern') }}
            </button>
            <button type="submit" form="project-detail-form" name="close_after_save" value="1" class="rounded bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
                {{ __('Speichern und Schließen') }}
            </button>
        </div>
    @endif
</div>
