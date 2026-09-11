@php
    $statusOptions = [0 => __('Geplant'), 1 => __('In Bearbeitung'), 2 => __('Beendet'), 3 => __('Verworfen')];
    $isOverlay = $overlay ?? false;
    $navParams = fn ($target) => array_filter(['project' => $target, 'sort' => $sort ?? null, 'direction' => $direction ?? 'asc', 'filter' => $filters ?? []]);

    // Gemeinsamer Button-Look fürs ganze Overlay: gefüllter grauer
    // Hintergrund grenzt Buttons klar von weißen Eingabefeldern ab
    // (die nur einen Rahmen haben).
    $secondaryBtn = 'inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover';
    $secondaryBtnDisabled = 'inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-300 cursor-not-allowed';

    // Start/Ende werden einseitig aus dem als Start/Ende markierten
    // Workflow-Schritt übernommen (ProjectWorkflowStepObserver), sobald ein
    // solcher Schritt existiert - das Feld hier manuell zu ändern, wäre dann
    // wirkungslos (der nächste Termin am WFS überschreibt es wieder) und
    // damit irreführend. Also nur editierbar, solange es keinen WFS gibt,
    // der diese Rolle trägt (Ralf-Feedback).
    $startStep = $project->projectWorkflowSteps->first(fn ($pws) => $pws->effectiveIsStart());
    $endStep = $project->projectWorkflowSteps->first(fn ($pws) => $pws->effectiveIsEnd());
    $stepLabel = fn ($pws) => $pws->effectiveMilestoneTitle() ?: $pws->workflowStep->title;

    // Ralf: "Bitte weniger Weißraum insgesamt" - Stammdaten/Ablaufdaten
    // laufen deshalb als 2-spaltiges Raster statt einer Feld-pro-Zeile-
    // Liste; von Natur aus breite Felder (Fließtext, Mehrfachauswahl,
    // Personen-/Markt-Zuordnung) spannen beide Spalten.
    $isWideField = function ($field) {
        if ($field->system) {
            return in_array($field->key, ['title', 'start_date', 'status', 'remarks', 'markets', 'project_people', 'project_connections', 'remarks_echo', 'changes_vs_previous_version', 'date_progress', 'progress'], true);
        }

        return $field->data_type === \App\Models\Attribute::DATA_TYPE_TEXTAREA
            || ($field->data_type === \App\Models\Attribute::DATA_TYPE_SELECT && $field->multiple);
    };
@endphp

<div class="{{ $isOverlay ? 'flex h-full min-h-0 flex-col' : 'p-4' }}">
    <div
        class="flex items-center justify-between gap-2 {{ $isOverlay ? 'shrink-0 rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-2 cursor-move select-none' : 'mb-1' }}"
        @if ($isOverlay) data-drag-handle title="{{ __('Ziehen zum Verschieben') }}" @endif
    >
        <div class="flex items-center gap-2">
            <span class="text-base font-semibold text-gray-900">{{ $project->source_pn }}</span>

            <x-favorite-star :project="$project" :is-favorite="$project->isFavoritedBy(auth()->user())" />

            <x-project-directory-status :project="$project" :status="$directoryStatus" :suggested-folder-name="$directorySuggestedFolderName" />

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
                        onclick="window.confirmDiscardIfDirty('projectOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $previousProject?->id ?? 'null' }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } })))"
                        class="{{ $navBtn }}"
                        title="{{ __('Vorheriges Projekt') }}"
                    >{!! $chevronLeft !!}</button>
                    <button
                        type="button"
                        @disabled(! $nextProject)
                        onclick="window.confirmDiscardIfDirty('projectOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $nextProject?->id ?? 'null' }}, sort: {{ \Illuminate\Support\Js::from($sort ?? null) }}, direction: {{ \Illuminate\Support\Js::from($direction ?? 'asc') }}, filters: {{ \Illuminate\Support\Js::from($filters ?? []) }} } })))"
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

        {{-- Aktions-Buttons stehen neben den Reitern (S. u.), nicht hier in
             der Verschiebeleiste - die dient nur zum Ziehen des Overlays. --}}
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
            <div class="mb-3 flex items-center gap-1.5 text-sm text-gray-600">
                {{ $project->title }}
                <x-status-icon :status="$project->status" class="inline-block h-4 w-auto shrink-0 align-middle" />
            </div>

            @if (($justSaved ?? false))
                <x-flash-message class="mb-2 px-2 py-1 text-xs">{{ __('Gespeichert.') }}</x-flash-message>
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

            <div class="mb-3 flex items-center justify-between gap-4 border-b border-gray-200 text-sm">
                <div class="flex gap-4">
                    <button type="button" @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Details') }}</button>
                    <button type="button" @click="activeTab = 'vorgaenge'" :class="activeTab === 'vorgaenge' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Vorgänge') }}</button>
                    <button type="button" @click="activeTab = 'workflow_steps'" :class="activeTab === 'workflow_steps' ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="pb-2">{{ __('Workflow') }}</button>
                </div>

                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="window.openIllustrationOrders({{ $project->id }})"
                        class="{{ $secondaryBtn }}"
                    >
                        {{ __('Illustrationsauftrag') }}
                    </button>
                    @can('project.create')
                        <button
                            type="button"
                            @click="window.openProjectCopy({{ $project->id }})"
                            class="{{ $secondaryBtn }}"
                        >
                            {{ __('Projekt kopieren') }}
                        </button>
                    @endcan
                    {{-- weitere Aktions-Buttons (Aufgabe zuweisen, -> Projekt-Pool, Fehlercheck, Sichtbarkeit, Sperrmail, ...) folgen später. --}}
                </div>
            </div>
        </div>

        <div class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto px-4 pb-3' : '' }}">
        <div x-show="activeTab === 'details'">
        <form id="project-detail-form" method="POST" action="{{ route('projekte.update', $project) }}" class="space-y-4 text-sm">
        <div>
        <div class="text-[11px] font-medium" style="color: #999">{{ __('Stammdaten') }}</div>
        <div class="border-t-2" style="border-color: #09f"></div>
        <div class="flex gap-3">
        <div class="w-0.5 shrink-0 rounded-full" style="background-color: #09f" title="{{ __('Stammdaten') }}"></div>
        <div class="min-w-0 flex-1">

        <div class="grid grid-cols-2 gap-x-4">
        @php $col = 0; $row = 0; @endphp
        @foreach ($stammdatenAttributes as $field)
            @php
                $wide = $isWideField($field);
                if ($wide && $col !== 0) { $row++; $col = 0; }
                $isFirstRow = $row === 0;
                if ($wide) { $row++; $col = 0; } else { $col++; if ($col >= 2) { $col = 0; $row++; } }
            @endphp
            <div class="{{ $wide ? 'col-span-2' : '' }} {{ $isFirstRow ? '' : 'border-t border-gray-100 pt-2' }}">
                @if ($field->system)
                    @include('projekte.partials.system-fields.'.$field->key)
                @else
                    @include('projekte.partials.attribute-field', ['attribute' => $field])
                @endif
            </div>
        @endforeach
        </div>

        </div>
        </div>
        </div>

        @if ($attributes->isNotEmpty())
        <div class="!mt-4">
        <div class="text-[11px] font-medium" style="color: #999">{{ __('Typspezifische Attribute') }}</div>
        <div class="border-t-2" style="border-color: {{ $project->attribute_section_color }}"></div>
        <div class="flex gap-3">
        <div class="w-0.5 shrink-0 rounded-full" style="background-color: {{ $project->attribute_section_color }}" title="{{ __('Typspezifische Attribute') }}"></div>
        <div class="min-w-0 flex-1">
            <div class="grid grid-cols-4 gap-2">
                @foreach ($attributes as $attribute)
                    @include('projekte.partials.attribute-field', ['attribute' => $attribute])
                @endforeach
            </div>
        </div>
        </div>
        </div>
        @endif

        <div class="!mt-4">
        <div class="text-[11px] font-medium" style="color: #999">{{ __('Ablaufdaten') }}</div>
        <div class="border-t-2" style="border-color: #396"></div>
        <div class="flex gap-3">
        <div class="w-0.5 shrink-0 rounded-full" style="background-color: #396" title="{{ __('Ablaufdaten') }}"></div>
        <div class="min-w-0 flex-1">

        <div class="grid grid-cols-2 gap-x-4">
        @php $col = 0; $row = 0; @endphp
        @foreach ($ablaufdatenAttributes as $field)
            @php
                $wide = $isWideField($field);
                if ($wide && $col !== 0) { $row++; $col = 0; }
                $isFirstRow = $row === 0;
                if ($wide) { $row++; $col = 0; } else { $col++; if ($col >= 2) { $col = 0; $row++; } }
            @endphp
            <div class="{{ $wide ? 'col-span-2' : '' }} {{ $isFirstRow ? '' : 'border-t border-gray-100 pt-2' }}">
                @if ($field->system)
                    @include('projekte.partials.system-fields.'.$field->key)
                @else
                    @include('projekte.partials.attribute-field', ['attribute' => $field])
                @endif
            </div>
        @endforeach
        </div>

        </div>
        </div>
        </div>

        @include('projekte.partials.footer')

        @csrf
        @method('patch')
        </form>
        </div>

        @php
            $activityCategories = $project->activities->map(fn ($activity) => $activity->type->category())->unique('value')->sortBy('value')->values();
        @endphp
        <div
            x-show="activeTab === 'vorgaenge'"
            x-cloak
            x-data="{ activeCategories: {{ \Illuminate\Support\Js::from($activityCategories->pluck('value')->all()) }} }"
            class="text-xs text-gray-600"
        >
            @if ($activityCategories->count() > 1)
                <div class="mb-2 flex flex-wrap gap-3">
                    @foreach ($activityCategories as $category)
                        <label class="flex items-center gap-1">
                            <input type="checkbox" value="{{ $category->value }}" x-model="activeCategories" class="rounded border-gray-300">
                            <span class="inline-block h-2 w-2 rounded-full {{ $category->dotClass() }}"></span>
                            {{ $category->label() }}
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="space-y-1">
                @forelse ($project->activities as $activity)
                    <div x-show="activeCategories.includes('{{ $activity->type->category()->value }}')">
                        <span class="inline-block h-2 w-2 rounded-full {{ $activity->type->category()->dotClass() }}" title="{{ $activity->type->category()->label() }}"></span>
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
        </div>

        <div x-show="activeTab === 'workflow_steps'" x-cloak class="text-sm">
            @if (! $project->workflow)
                <div class="text-gray-400">&ndash; {{ __('Kein Workflow zugewiesen') }} &ndash;</div>
            @else
                @php
                    // Alte/andere Workflow-Generationen können noch Schritt-Zeilen für
                    // dieses Projekt haben (siehe Kommentar bei der Zuweisungslogik) -
                    // hier nur die Schritte des AKTUELL zugewiesenen Workflows zeigen.
                    // is_active=false (Vietto: blnIsActiveWFS) markiert einen Schritt
                    // bewusst als "nur Termin, kein WFS" - für die (noch nicht gebaute)
                    // Terminberechnung gedacht, kein echter Prozessschritt zum
                    // Aktivieren (Ralfs Bug-Report: "Markteinführung" erschien trotz
                    // is_active=false als normale Aktivieren-Box, betraf real 7251
                    // bestehende Projekt-Schritt-Zeilen, u.a. "Druck"/"Markteinführung").
                    $currentSteps = $project->projectWorkflowSteps
                        ->filter(fn ($pws) => $pws->workflowStep && $pws->workflowStep->workflow_id === $project->workflow_id && $pws->workflowStep->is_active)
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
                    <div class="flex flex-col items-start">
                        @foreach ($currentSteps as $pws)
                            @php
                                $step = $pws->workflowStep;
                                $isDone = $pws->completed_at !== null;
                            @endphp
                            @php
                                $peopleByGroup = $pws->people->groupBy('function_group_id');
                            @endphp
                            <div class="flex w-full items-start gap-3">
                            <div
                                x-data="{ expanded: false }"
                                class="w-full max-w-2xl rounded-md px-3 py-2 {{ $pws->is_current ? 'border-2 border-blue-500' : 'border border-gray-300' }}"
                                style="background-color: {{ $step->lifecycleColor() }}"
                            >
                                <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 flex-1 items-start gap-2">
                                    <span class="shrink-0 text-lg font-semibold text-gray-400">{{ $loop->iteration }}</span>
                                    <div class="min-w-0 flex-1">
                                        {{-- break-words: lange Titel ohne Leerzeichen (z.B.
                                             "Anleitung/Korrekturexemplar") liefen sonst optisch
                                             über den schmalen Container hinweg in die
                                             Funktionsgruppen-Box (Ralf-Bug-Report). --}}
                                        <div class="break-words font-medium text-gray-900">{{ $step->title }}</div>
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
                                                        :disabled="saving || {{ auth()->user()->can('workflow_step.due_date') ? 'false' : 'true' }}"
                                                        @change="
                                                            saving = true;
                                                            fetch({{ \Illuminate\Support\Js::from(route('projekte.workflow-steps.due-date', [$project, $pws])) }}, {
                                                                method: 'PATCH',
                                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                                                body: JSON.stringify({ due_date: value || null }),
                                                            }).then(() => {
                                                                @if ($pws->effectiveIsStart() || $pws->effectiveIsEnd())
                                                                    window.refreshUnderlyingProject({{ $project->id }});
                                                                @endif
                                                            }).finally(() => saving = false);
                                                        "
                                                        class="rounded border-gray-300 py-0.5 text-xs"
                                                    >
                                                    @can('workflow_step.due_date')
                                                        <button
                                                            type="button"
                                                            @click.stop="window.openProjectSchedule({{ $project->id }}, {{ $pws->id }})"
                                                            class="text-gray-400 hover:text-gray-700"
                                                            title="{{ __('Termine berechnen') }}"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25Z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 11.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm2.498-4.5h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm2.504-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Z" />
                                                            </svg>
                                                        </button>
                                                    @endcan
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

                                        @unless ($pws->is_current)
                                            @if (auth()->user()->can('workflow_step.activate') && (! in_array($step->lifecycle_status, [3, 4], true) || auth()->user()->can('project.complete')))
                                                <button
                                                    type="button"
                                                    @click.stop="window.openActivateWorkflowStep({{ $project->id }}, {{ $pws->id }})"
                                                    class="mt-1 rounded border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                                                >
                                                    {{ __('Aktivieren') }}
                                                </button>
                                            @endif
                                        @endunless
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

                                {{-- Sonderbutton INNERHALB der Schritt-Box, nicht daneben (Ralf:
                                     "In Vietto stehen die Buttons innerhalb des WFS") - eigene volle
                                     Zeile unter Zuständigkeit/Funktionsgruppe. --}}
                                @if ($step->js_function === 'wfs_grafik')
                                    @php
                                        $illuOrders = $project->graphicOrders;
                                        $illuTotal = $illuOrders->count();
                                        $illuOpen = $illuOrders->filter(fn ($o) => $o->status?->isOpen())->count();
                                        $illuDone = $illuOrders->filter(fn ($o) => $o->status && ! $o->status->isOpen() && ! $o->status->isDiscarded())->count();
                                        $illuDiscarded = $illuOrders->filter(fn ($o) => $o->status?->isDiscarded())->count();
                                        $illuAllClosed = $illuTotal > 0 && $illuOpen === 0;
                                    @endphp
                                    <div class="mt-2 flex items-center gap-2 border-t border-black/10 pt-2 text-xs" @click.stop>
                                        <button
                                            type="button"
                                            @click.stop="window.openIllustrationOrders({{ $project->id }})"
                                            class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                                        >
                                            {{ __('Illustrationsauftrag') }}
                                        </button>
                                        <span class="text-gray-700">
                                            @if ($illuTotal === 0)
                                                {{ __('Keine Aufträge vorhanden') }}
                                            @else
                                                {{ $illuTotal }} {{ $illuTotal === 1 ? __('Auftrag') : __('Aufträge') }}, {{ $illuOpen }} {{ __('offen') }}, {{ $illuDone }} {{ __('erledigt') }}, {{ $illuDiscarded }} {{ __('verworfen') }}
                                                @if ($illuAllClosed)
                                                    &middot; <span class="font-medium text-green-800">{{ __('Erledigt') }}</span>
                                                @endif
                                            @endif
                                        </span>
                                    </div>
                                @elseif ($step->js_function === 'wfs_freigabe')
                                    <div
                                        x-data="{ granted: {{ \Illuminate\Support\Js::from($pws->milestone_done_at !== null) }}, saving: false }"
                                        class="mt-2 flex items-center gap-2 border-t border-black/10 pt-2 text-xs"
                                        @click.stop
                                    >
                                        {{-- Milestone-Titel als eigenständiges Label statt in den Satz
                                             eingebaut - milestone_title ist Freitext, dessen Genus wir
                                             nicht kennen ("Redaktionsschluss" bräuchte "kein", nicht
                                             "keine") - grammatisch nur sicher als reines Label, nicht als
                                             Satzobjekt von "erteilen/zurücknehmen". --}}
                                        <span class="font-medium text-gray-700">{{ $step->milestone_title ?: __('Freigabe') }}:</span>
                                        @if ($pws->is_current && auth()->user()->can('workflow_step.activate'))
                                            <button
                                                type="button"
                                                :disabled="saving"
                                                @click="
                                                    saving = true;
                                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.workflow-steps.freigabe', [$project, $pws])) }}, {
                                                        method: 'PATCH',
                                                        headers: { 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                                    }).then(r => r.json()).then(data => { granted = data.milestone_done_at !== null; }).finally(() => saving = false);
                                                "
                                                class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                                                x-text="granted ? {{ \Illuminate\Support\Js::from(__('Freigabe zurücknehmen')) }} : {{ \Illuminate\Support\Js::from(__('Freigabe erteilen')) }}"
                                            ></button>
                                        @else
                                            <span :class="granted ? 'text-green-800' : 'text-gray-500'" x-text="granted ? {{ \Illuminate\Support\Js::from(__('erteilt')) }} : {{ \Illuminate\Support\Js::from(__('noch nicht erteilt')) }}"></span>
                                        @endif
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
                    class="rounded border border-gray-300 bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
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
                    <button type="submit" form="project-detail-form" class="rounded border border-gray-300 bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Speichern') }}
                    </button>
                    <button type="submit" form="project-detail-form" name="close_after_save" value="1" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern und Schließen') }}
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="flex justify-end gap-2 pt-1">
            <button type="submit" form="project-detail-form" class="rounded border border-gray-300 bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Speichern') }}
            </button>
            <button type="submit" form="project-detail-form" name="close_after_save" value="1" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern und Schließen') }}
            </button>
        </div>
    @endif
</div>
