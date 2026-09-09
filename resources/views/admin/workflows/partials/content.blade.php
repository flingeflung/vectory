<div id="workflows-content" class="flex flex-1 min-h-0 gap-4">
    {{-- Links: Workflows, per D&D sortierbar (reine Anzeigereihenfolge,
         unabhängig davon ob ein Workflow schon publiziert ist). --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.workflows')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newWorkflow: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Workflows') }}</span>
                <button type="button" @click="newWorkflow = !newWorkflow; if (newWorkflow) $nextTick(() => $refs.newWorkflowName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newWorkflow" x-cloak method="POST" action="{{ route('admin.workflows.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newWorkflowName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Anlegen') }}
                    </button>
                </form>

                @if ($workflows->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Workflows angelegt.') }}</div>
                @else
                    <div
                        x-data="{
                            async saveOrder() {
                                const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                await fetch({{ \Illuminate\Support\Js::from(route('admin.workflows.reorder')) }}, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                    body: JSON.stringify({ workflows: ids }),
                                });
                            },
                        }"
                        x-sort="saveOrder()"
                    >
                        @foreach ($workflows as $workflow)
                            <div x-sort:item="{{ $workflow->id }}" class="flex items-center gap-1 rounded {{ $selectedWorkflow?->id === $workflow->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                <a
                                    :href="navUrl({ workflow: {{ $workflow->id }} })"
                                    onclick="return window.navigateOrConfirm(event)"
                                    @if ($selectedWorkflow?->id === $workflow->id) data-selected @endif
                                    class="flex flex-1 flex-col py-1 pr-2 {{ $selectedWorkflow?->id === $workflow->id ? 'font-medium text-indigo-700' : ($workflow->active ? 'text-gray-700' : 'text-gray-400') }}"
                                >
                                    <span class="flex items-center justify-between">
                                        <span>
                                            {{ $workflow->name }}{{ ! $workflow->active ? ' [i]' : '' }}
                                            @unless ($workflow->published_at)
                                                <span class="rounded bg-amber-100 px-1 py-0.5 text-xs font-normal text-amber-700">{{ __('Entwurf') }}</span>
                                            @endunless
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $workflow->steps_count }}</span>
                                    </span>
                                    @if ($workflow->supersededBy)
                                        <span class="text-xs text-gray-400">{{ __('ersetzt durch: :name (#:id)', ['name' => $workflow->supersededBy->name, 'id' => $workflow->supersededBy->id]) }}</span>
                                    @endif
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: entweder der ausgewählte Workflow (Entwurf: voll editierbar;
         publiziert: nur lesbar + "Neue Version erstellen"), oder - nichts
         ausgewählt - der komplette Katalog nur zur Ansicht. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedWorkflow)
            @if ($isPublished)
                {{-- Publiziert: eingefroren. Nur Aktiv/Inaktiv bleibt änderbar
                     (das betrifft nur die Sichtbarkeit für neue Projekte,
                     keinen Inhalt) - eigenes kleines Formular, damit es
                     unabhängig vom (gesperrten) Rest sofort auto-submitted. --}}
                <div class="shrink-0 border-b border-gray-100 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $selectedWorkflow->name }}</div>
                            @if ($selectedWorkflow->description)
                                <p class="mt-0.5 text-xs text-gray-400">{{ $selectedWorkflow->description }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.workflows.update', $selectedWorkflow) }}" onchange="this.submit()">
                            @csrf
                            <label class="flex shrink-0 items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="active" value="1" @checked($selectedWorkflow->active) class="rounded border-gray-300">
                                {{ __('Aktiv (wählbar für neue Projekte)') }}
                            </label>
                        </form>
                    </div>
                    <div class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {{ __('Dieser Workflow wurde am :date veröffentlicht und ist deshalb eingefroren - Inhalte lassen sich nicht mehr ändern. Für Anpassungen bitte eine neue Version erstellen; bestehende Projekte bleiben unverändert auf dieser Version.', ['date' => $selectedWorkflow->published_at->format('d.m.Y')]) }}
                    </div>
                    <div x-data class="mt-2 flex justify-end">
                        <form method="POST" action="{{ route('admin.workflows.new-version', $selectedWorkflow) }}" x-ref="newVersionForm" class="hidden">
                            @csrf
                        </form>
                        <button
                            type="button"
                            @click="window.deleteWithConfirm($refs.newVersionForm, {
                                message: {{ \Illuminate\Support\Js::from(__('Legt eine Kopie dieses Workflows (inkl. aller Schritte) als neue, frei bearbeitbare Version an. Die bestehende Version bleibt für schon zugewiesene Projekte unverändert erhalten, wird aber für neue Projekte nicht mehr angeboten.')) }},
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('Neue Version erstellen')) }},
                            })"
                            class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover"
                        >
                            {{ __('Neue Version erstellen') }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
                    <div class="text-xs font-semibold text-gray-500">{{ __('Schritte') }}</div>
                    @forelse ($steps as $step)
                        <div class="flex items-center gap-3 rounded-md border border-gray-200 p-2 text-sm">
                            <span class="h-3 w-3 shrink-0 rounded-full border border-gray-300" style="background-color: {{ $lifecycleColors[$step->lifecycle_status] ?? $lifecycleColors[2] }}"></span>
                            <span class="flex-1 {{ $step->is_active ? 'text-gray-700' : 'text-gray-400' }}">{{ $step->title }}{{ ! $step->is_active ? ' [i]' : '' }}</span>
                            @if ($step->functionGroups->isNotEmpty())
                                <span class="text-xs text-gray-400">{{ $step->functionGroups->pluck('name')->join(', ') }}</span>
                            @endif
                            @if ($step->js_function)
                                <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700">{{ $specialButtons[$step->js_function] ?? $step->js_function }}</span>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-md border border-dashed border-gray-200 p-3 text-sm text-gray-400">{{ __('Keine Schritte vorhanden.') }}</div>
                    @endforelse
                </div>
            @else
                {{-- Entwurf: voll editierbar. --}}
                @php
                    // Muss vor dem x-init unten feststehen, das den Alpine-Store
                    // initialisiert - siehe auch die spätere Verwendung beim
                    // Schritte-Formular selbst.
                    $isResubmit = $errors->any();
                @endphp
                <div
                    x-data="{}"
                    x-init="
                        window.adminPageIsDirty = () => window.__workflowsDirtyForms.size > 0;
                        {{-- Geteilter Zustand zwischen dem Schritte-Formular und dem
                             Speichern-Button im Fußbereich (siehe dort) - beide sind
                             unterschiedliche Alpine-Komponenten (Geschwister, kein
                             gemeinsames x-data), $store verbindet sie ohne
                             DOM-Verschachtelung. --}}
                        Alpine.store('workflowStepsDirty', { value: {{ \Illuminate\Support\Js::from($isResubmit ?? false) }} });
                    "
                    class="flex flex-1 min-h-0 flex-col"
                >
                <form
                    method="POST"
                    action="{{ route('admin.workflows.update', $selectedWorkflow) }}"
                    data-row-form
                    class="shrink-0 space-y-2 border-b border-gray-100 p-3"
                    x-data="{ dirty: false }"
                    @input="dirty = true; window.__workflowsDirtyForms.add($el)"
                    @submit="dirty = false; window.__workflowsDirtyForms.delete($el)"
                >
                    @csrf
                    <div class="flex items-center gap-3">
                        <input type="text" name="name" value="{{ $selectedWorkflow->name }}" placeholder="{{ __('Name') }}" required class="flex-1 rounded-md border-gray-300 py-1 text-sm font-medium text-gray-900">
                        <input type="text" name="short_name" value="{{ $selectedWorkflow->short_name }}" maxlength="10" placeholder="{{ __('Kürzel') }}" class="w-24 rounded-md border-gray-300 py-1 text-sm">
                        <label class="flex shrink-0 items-center gap-1.5 text-xs text-gray-600">
                            <input type="checkbox" name="active" value="1" @checked($selectedWorkflow->active) class="rounded border-gray-300">
                            {{ __('Aktiv') }}
                        </label>
                        <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    </div>
                    <textarea name="description" rows="2" placeholder="{{ __('Beschreibung') }}" class="w-full rounded-md border-gray-300 text-sm">{{ $selectedWorkflow->description }}</textarea>
                    <p class="text-xs text-gray-400">{{ __('Entwurf - frei bearbeitbar und beliebig oft zum Testen einem Projekt zuweisbar. Bleibt so, bis du ihn veröffentlichst.') }}</p>
                </form>

                @if ($isResubmit)
                    <div class="shrink-0 border-b border-gray-100 bg-red-50 px-3 py-2 text-xs text-red-600">
                        {{ __('Bitte die rot markierten Felder korrigieren, dann erneut speichern.') }}
                    </div>
                @endif

                <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2" x-data="{ newStep: false }">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-gray-500">{{ __('Schritte') }}</div>
                        <div class="flex items-center gap-1">
                            @if ($steps->isNotEmpty())
                                <button type="button" @click="window.dispatchEvent(new CustomEvent('workflow-steps-expand-all'))" class="inline-flex items-center rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                    {{ __('Alle ausklappen') }}
                                </button>
                                <button type="button" @click="window.dispatchEvent(new CustomEvent('workflow-steps-collapse-all'))" class="inline-flex items-center rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                    {{ __('Alle einklappen') }}
                                </button>
                            @endif
                            <button type="button" @click="newStep = !newStep; if (newStep) $nextTick(() => $refs.newStepTitle.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                + {{ __('Neu') }}
                            </button>
                        </div>
                    </div>

                    <form x-show="newStep" x-cloak method="POST" action="{{ route('admin.workflows.schritte.store') }}" class="flex items-center gap-2 rounded-md border border-gray-200 p-2">
                        @csrf
                        <input type="hidden" name="workflow_id" value="{{ $selectedWorkflow->id }}">
                        <input type="text" name="title" x-ref="newStepTitle" placeholder="{{ __('Titel') }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                        <button type="button" @click="newStep = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Anlegen') }}</button>
                    </form>

                    @if ($steps->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-200 p-3 text-sm text-gray-400">{{ __('Noch keine Schritte in diesem Workflow.') }}</div>
                    @else
                        @php
                            // Alle Schritte werden zusammen in EINEM Formular
                            // gespeichert (Ralf: "für jeden WFS einen eigenen
                            // Speichern-Button" war "mühsam") - bei einem
                            // Validierungsfehler wird NICHTS gespeichert, die
                            // ganze Seite kommt mit alten Eingaben + rotem
                            // Hinweistext zurück (siehe
                            // WorkflowController::stepsBulkUpdate()). $errors
                            // ist dabei entweder leer (normaler Seitenaufruf)
                            // oder mit "steps.<id>.<feld>"-Schlüsseln gefüllt.
                            // ($isResubmit selbst steht schon weiter oben fest,
                            // wird hier nur nochmal referenziert.)
                        @endphp
                        <form
                            id="workflow-steps-form"
                            method="POST"
                            action="{{ route('admin.workflows.schritte.bulk-update') }}"
                            data-steps-form
                            @input="$store.workflowStepsDirty.value = true; window.__workflowsDirtyForms.add($el)"
                            @submit="$store.workflowStepsDirty.value = false; window.__workflowsDirtyForms.delete($el)"
                        >
                            @csrf
                            <input type="hidden" name="workflow_id" value="{{ $selectedWorkflow->id }}">

                            <div
                                x-data="{
                                    async saveOrder() {
                                        const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                        await fetch({{ \Illuminate\Support\Js::from(route('admin.workflows.schritte.reorder')) }}, {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                            body: JSON.stringify({ workflow_id: {{ $selectedWorkflow->id }}, steps: ids }),
                                        });
                                    },
                                }"
                                x-sort="saveOrder()"
                                class="space-y-2"
                            >
                                @foreach ($steps as $step)
                                    @php $stepHasError = $errors->has("steps.{$step->id}.*"); @endphp
                                    <div
                                        x-sort:item="{{ $step->id }}"
                                        x-data="{ expanded: {{ $stepHasError ? 'true' : 'false' }}, sendEmail: {{ \Illuminate\Support\Js::from($isResubmit ? old("steps.{$step->id}.send_email") !== null : $step->send_email) }} }"
                                        x-on:workflow-steps-expand-all.window="expanded = true"
                                        x-on:workflow-steps-collapse-all.window="expanded = false"
                                        class="rounded-md border p-2 {{ $stepHasError ? 'border-red-300' : 'border-gray-200' }}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Sortierung ändern') }}">⠿</span>
                                            <div class="min-w-0 flex-1">
                                                <input
                                                    type="text"
                                                    name="steps[{{ $step->id }}][title]"
                                                    value="{{ old("steps.{$step->id}.title", $step->title) }}"
                                                    required
                                                    class="w-full rounded-md text-sm {{ $errors->has("steps.{$step->id}.title") ? 'border-red-400' : 'border-gray-300' }}"
                                                >
                                                @error("steps.{$step->id}.title")
                                                    <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                                <input type="checkbox" name="steps[{{ $step->id }}][is_active]" value="1" @checked($isResubmit ? old("steps.{$step->id}.is_active") !== null : $step->is_active) class="rounded border-gray-300">
                                                {{ __('Aktiv') }}
                                            </label>
                                            <button type="button" @click="expanded = !expanded" class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800" x-text="expanded ? {{ \Illuminate\Support\Js::from(__('weniger')) }} : {{ \Illuminate\Support\Js::from(__('Details')) }}"></button>
                                        </div>

                                        <div class="pl-6 text-xs text-gray-500">
                                            {{ __('Funktionsgruppe(n)') }}:
                                            <span class="ml-1 inline-flex flex-wrap gap-x-3 gap-y-1 align-middle">
                                                @forelse ($functionGroups as $group)
                                                    <label class="inline-flex items-center gap-1">
                                                        <input type="checkbox" name="steps[{{ $step->id }}][function_groups][{{ $group->id }}]" value="1" @checked($isResubmit ? old("steps.{$step->id}.function_groups.{$group->id}") !== null : $step->functionGroups->contains('id', $group->id)) class="rounded border-gray-300">
                                                        {{ $group->name }}
                                                    </label>
                                                @empty
                                                    <span class="text-gray-300">{{ __('– keine Funktionsgruppen angelegt –') }}</span>
                                                @endforelse
                                            </span>
                                        </div>

                                        <template x-if="expanded">
                                            <div class="grid grid-cols-2 gap-3 rounded-md bg-gray-50 p-3 pl-9 text-xs">
                                                <div>
                                                    <label class="block text-gray-500">{{ __('Kurztitel') }}</label>
                                                    <input type="text" name="steps[{{ $step->id }}][short_title]" value="{{ old("steps.{$step->id}.short_title", $step->short_title) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                    @error("steps.{$step->id}.short_title")
                                                        <p class="mt-0.5 text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500">{{ __('Meilenstein-Titel') }}</label>
                                                    <input type="text" name="steps[{{ $step->id }}][milestone_title]" value="{{ old("steps.{$step->id}.milestone_title", $step->milestone_title) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                    @error("steps.{$step->id}.milestone_title")
                                                        <p class="mt-0.5 text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="col-span-2 flex flex-wrap gap-x-4 gap-y-1">
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][is_start]" value="1" @checked($isResubmit ? old("steps.{$step->id}.is_start") !== null : $step->is_start) class="rounded border-gray-300"> {{ __('Start des Projekts') }}</label>
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][is_end]" value="1" @checked($isResubmit ? old("steps.{$step->id}.is_end") !== null : $step->is_end) class="rounded border-gray-300"> {{ __('Ende des Projekts') }}</label>
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][is_market_launch]" value="1" @checked($isResubmit ? old("steps.{$step->id}.is_market_launch") !== null : $step->is_market_launch) class="rounded border-gray-300"> {{ __('Markteinführung') }}</label>
                                                </div>

                                                <div class="col-span-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][has_due_date]" value="1" @checked($isResubmit ? old("steps.{$step->id}.has_due_date") !== null : $step->has_due_date) class="rounded border-gray-300"> {{ __('Hat Termin') }}</label>
                                                    <label class="inline-flex items-center gap-1">
                                                        {{ __('Dauer (Tage)') }}
                                                        <input type="number" name="steps[{{ $step->id }}][duration_days]" min="0" value="{{ old("steps.{$step->id}.duration_days", $step->duration_days) }}" class="w-16 rounded-md border-gray-300 text-xs">
                                                    </label>
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][duration_editable]" value="1" @checked($isResubmit ? old("steps.{$step->id}.duration_editable") !== null : $step->duration_editable) class="rounded border-gray-300"> {{ __('Dauer änderbar') }}</label>
                                                    @error("steps.{$step->id}.duration_days")
                                                        <span class="text-red-600">{{ $message }}</span>
                                                    @enderror
                                                </div>

                                                <div class="col-span-2 flex flex-wrap gap-x-4 gap-y-1">
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][send_email]" value="1" x-model="sendEmail" class="rounded border-gray-300"> {{ __('E-Mail beim Aktivieren senden') }}</label>
                                                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="steps[{{ $step->id }}][show_in_translation]" value="1" @checked($isResubmit ? old("steps.{$step->id}.show_in_translation") !== null : $step->show_in_translation) class="rounded border-gray-300"> {{ __('In Übersetzungsansicht zeigen') }}</label>
                                                </div>

                                                <div>
                                                    <label class="block text-gray-500">{{ __('Kastenfarbe') }}</label>
                                                    <select name="steps[{{ $step->id }}][lifecycle_status]" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                        @php $lifecycleOld = (string) old("steps.{$step->id}.lifecycle_status", (string) $step->lifecycle_status); @endphp
                                                        <option value="1" @selected($lifecycleOld === '1')>{{ __('Bevorstehend (hell)') }}</option>
                                                        <option value="2" @selected($lifecycleOld === '2')>{{ __('Standard (grün)') }}</option>
                                                        <option value="3" @selected($lifecycleOld === '3')>{{ __('Abgeschlossen (dunkelgrün)') }}</option>
                                                        <option value="4" @selected($lifecycleOld === '4')>{{ __('Sonderfall (grau)') }}</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500">{{ __('Sonderbutton') }}</label>
                                                    <select name="steps[{{ $step->id }}][js_function]" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                        @php $jsFunctionOld = (string) old("steps.{$step->id}.js_function", (string) $step->js_function); @endphp
                                                        <option value="" @selected($jsFunctionOld === '')>{{ __('– keiner –') }}</option>
                                                        @foreach ($specialButtons as $key => $label)
                                                            <option value="{{ $key }}" @selected($jsFunctionOld === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error("steps.{$step->id}.js_function")
                                                        <p class="mt-0.5 text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="col-span-2">
                                                    <label class="block text-gray-500">{{ __('Beschreibung') }}</label>
                                                    <textarea name="steps[{{ $step->id }}][description]" rows="2" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">{{ old("steps.{$step->id}.description", $step->description) }}</textarea>
                                                </div>
                                                <div class="col-span-2" x-show="sendEmail">
                                                    <label class="block text-gray-500">{{ __('E-Mail-Text') }}</label>
                                                    <textarea name="steps[{{ $step->id }}][email_text]" rows="2" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">{{ old("steps.{$step->id}.email_text", $step->email_text) }}</textarea>
                                                </div>
                                            </div>
                                        </template>

                                        <div class="mt-1.5 flex justify-end">
                                            <button
                                                type="button"
                                                @click="window.deleteWithConfirm(document.getElementById('delete-step-form-{{ $step->id }}'), {
                                                    message: {{ \Illuminate\Support\Js::from(__('Diesen Schritt wirklich endgültig löschen?')) }},
                                                })"
                                                class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                            >
                                                {{ __('Löschen') }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </form>

                        @foreach ($steps as $step)
                            <form method="POST" action="{{ route('admin.workflows.schritte.destroy', $step) }}" id="delete-step-form-{{ $step->id }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                    @endif
                </div>
                </div>

                <div class="shrink-0 border-t border-gray-100 p-3">
                    <div x-data="{
                        async publish() {
                            if (await window.confirmDialog({
                                title: {{ \Illuminate\Support\Js::from(__('Workflow veröffentlichen')) }},
                                message: {{ \Illuminate\Support\Js::from(__('Danach lassen sich Name und Schritte nicht mehr ändern, nur noch über eine neue Version. Wirklich veröffentlichen?')) }},
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('Veröffentlichen')) }},
                            })) {
                                $refs.publishForm.submit();
                            }
                        },
                    }" class="flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400">{{ __('Entwurf - kann jederzeit gelöscht werden (auch eventuelle Test-Zuweisungen gehen dabei verloren).') }}</span>
                        <div class="flex items-center gap-2">
                            {{-- Speichert das Schritte-Formular von außerhalb (form=
                                 statt Verschachtelung) - liegt im immer sichtbaren
                                 Fußbereich statt einer schwebenden Leiste innerhalb
                                 der Liste, die dort mit dem letzten Schritt
                                 kollidierte (Ralf-Bug-Report: "Lücke im weißen
                                 Balken"). Geteilter Zustand über den Alpine-Store,
                                 da dieser Button in einer anderen Komponente steckt
                                 als das Formular selbst. --}}
                            <button
                                type="submit"
                                form="workflow-steps-form"
                                x-show="$store.workflowStepsDirty.value"
                                x-cloak
                                class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover"
                            >
                                {{ __('Speichern') }}
                            </button>
                            <form method="POST" action="{{ route('admin.workflows.destroy', $selectedWorkflow) }}" x-ref="deleteWorkflowForm" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                @click="window.deleteWithConfirm($refs.deleteWorkflowForm, {
                                    message: {{ \Illuminate\Support\Js::from(__('Diesen Workflow inklusive aller Schritte wirklich endgültig löschen?')) }},
                                })"
                                class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                            >
                                {{ __('Workflow löschen') }}
                            </button>
                            <form method="POST" action="{{ route('admin.workflows.publish', $selectedWorkflow) }}" x-ref="publishForm" class="hidden">
                                @csrf
                            </form>
                            <button type="button" @click="publish()" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                {{ __('Veröffentlichen') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Workflow-Katalog') }}</div>
                <p class="text-xs text-gray-400">{{ __('Wähle links einen Workflow aus, um seine Schritte zu bearbeiten.') }}</p>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-3">
                @forelse ($workflows as $workflow)
                    <div>
                        <div class="text-sm font-medium {{ $workflow->active ? 'text-gray-700' : 'text-gray-400' }}">
                            {{ $workflow->name }}{{ ! $workflow->active ? ' [i]' : '' }}
                            <span class="ml-1 text-xs text-gray-300">– {{ trans_choice(':count Schritt|:count Schritte', $workflow->steps_count, ['count' => $workflow->steps_count]) }}</span>
                        </div>
                        @if ($workflow->supersededBy)
                            <div class="pl-3 text-xs text-gray-400">{{ __('ersetzt durch: :name (#:id)', ['name' => $workflow->supersededBy->name, 'id' => $workflow->supersededBy->id]) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-400">{{ __('Noch keine Workflows angelegt.') }}</div>
                @endforelse
            </div>
        @endif
    </div>
</div>
