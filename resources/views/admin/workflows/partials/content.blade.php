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
                <button type="button" @click="newWorkflow = !newWorkflow; if (newWorkflow) $nextTick(() => $refs.newWorkflowName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newWorkflow" x-cloak method="POST" action="{{ route('admin.workflows.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newWorkflowName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">
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
                            class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700"
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
                <div
                    x-data="{}"
                    x-init="
                        window.adminPageIsDirty = () => window.__workflowsDirtyForms.size > 0;
                        window.addEventListener('beforeunload', (e) => { if (window.__workflowsDirtyForms.size > 0) { e.preventDefault(); e.returnValue = ''; } });
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
                        <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">{{ __('Speichern') }}</button>
                    </div>
                    <textarea name="description" rows="2" placeholder="{{ __('Beschreibung') }}" class="w-full rounded-md border-gray-300 text-sm">{{ $selectedWorkflow->description }}</textarea>
                    <p class="text-xs text-gray-400">{{ __('Entwurf - frei bearbeitbar und beliebig oft zum Testen einem Projekt zuweisbar. Bleibt so, bis du ihn veröffentlichst.') }}</p>
                </form>

                <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2" x-data="{ newStep: false }">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-gray-500">{{ __('Schritte') }}</div>
                        <button type="button" @click="newStep = !newStep; if (newStep) $nextTick(() => $refs.newStepTitle.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                            + {{ __('Neu') }}
                        </button>
                    </div>

                    <form x-show="newStep" x-cloak method="POST" action="{{ route('admin.workflows.schritte.store') }}" class="flex items-center gap-2 rounded-md border border-gray-200 p-2">
                        @csrf
                        <input type="hidden" name="workflow_id" value="{{ $selectedWorkflow->id }}">
                        <input type="text" name="title" x-ref="newStepTitle" placeholder="{{ __('Titel') }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                        <button type="button" @click="newStep = false" class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">{{ __('Anlegen') }}</button>
                    </form>

                    @if ($steps->isNotEmpty())
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
                    @endif
                    @forelse ($steps as $step)
                        <div x-sort:item="{{ $step->id }}" x-data="{ rowDirty: false, expanded: false }" class="rounded-md border border-gray-200 p-2">
                            <form method="POST" action="{{ route('admin.workflows.schritte.update', $step) }}" data-row-form class="space-y-2" @input="rowDirty = true; window.__workflowsDirtyForms.add($el)" @submit="rowDirty = false; window.__workflowsDirtyForms.delete($el)">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Sortierung ändern') }}">⠿</span>
                                    <input type="text" name="title" value="{{ $step->title }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                                    <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                        <input type="checkbox" name="is_active" value="1" @checked($step->is_active) class="rounded border-gray-300">
                                        {{ __('Aktiv') }}
                                    </label>
                                    <button type="button" @click="expanded = !expanded" class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800" x-text="expanded ? {{ \Illuminate\Support\Js::from(__('weniger')) }} : {{ \Illuminate\Support\Js::from(__('Details')) }}"></button>
                                    <button type="submit" x-show="rowDirty" x-cloak class="shrink-0 rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        {{ __('Speichern') }}
                                    </button>
                                </div>

                                <div class="pl-6 text-xs text-gray-500">
                                    {{ __('Funktionsgruppe(n)') }}:
                                    <span class="ml-1 inline-flex flex-wrap gap-x-3 gap-y-1 align-middle">
                                        @forelse ($functionGroups as $group)
                                            <label class="inline-flex items-center gap-1">
                                                <input type="checkbox" name="function_groups[{{ $group->id }}]" value="1" @checked($step->functionGroups->contains('id', $group->id)) class="rounded border-gray-300">
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
                                            <input type="text" name="short_title" value="{{ $step->short_title }}" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-gray-500">{{ __('Meilenstein-Titel') }}</label>
                                            <input type="text" name="milestone_title" value="{{ $step->milestone_title }}" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                        </div>

                                        <div class="col-span-2 flex flex-wrap gap-x-4 gap-y-1">
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="is_start" value="1" @checked($step->is_start) class="rounded border-gray-300"> {{ __('Start des Projekts') }}</label>
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="is_end" value="1" @checked($step->is_end) class="rounded border-gray-300"> {{ __('Ende des Projekts') }}</label>
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="is_market_launch" value="1" @checked($step->is_market_launch) class="rounded border-gray-300"> {{ __('Markteinführung') }}</label>
                                        </div>

                                        <div class="col-span-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="has_due_date" value="1" @checked($step->has_due_date) class="rounded border-gray-300"> {{ __('Hat Termin') }}</label>
                                            <label class="inline-flex items-center gap-1">
                                                {{ __('Dauer (Tage)') }}
                                                <input type="number" name="duration_days" min="0" value="{{ $step->duration_days }}" class="w-16 rounded-md border-gray-300 text-xs">
                                            </label>
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="duration_editable" value="1" @checked($step->duration_editable) class="rounded border-gray-300"> {{ __('Dauer änderbar') }}</label>
                                        </div>

                                        <div class="col-span-2 flex flex-wrap gap-x-4 gap-y-1">
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="send_email" value="1" @checked($step->send_email) class="rounded border-gray-300"> {{ __('E-Mail beim Aktivieren senden') }}</label>
                                            <label class="inline-flex items-center gap-1"><input type="checkbox" name="show_in_translation" value="1" @checked($step->show_in_translation) class="rounded border-gray-300"> {{ __('In Übersetzungsansicht zeigen') }}</label>
                                        </div>

                                        <div>
                                            <label class="block text-gray-500">{{ __('Kastenfarbe') }}</label>
                                            <select name="lifecycle_status" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                <option value="1" @selected($step->lifecycle_status === 1)>{{ __('Bevorstehend (hell)') }}</option>
                                                <option value="2" @selected($step->lifecycle_status === 2)>{{ __('Standard (grün)') }}</option>
                                                <option value="3" @selected($step->lifecycle_status === 3)>{{ __('Abgeschlossen (dunkelgrün)') }}</option>
                                                <option value="4" @selected($step->lifecycle_status === 4)>{{ __('Sonderfall (grau)') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-gray-500">{{ __('Sonderbutton') }}</label>
                                            <select name="js_function" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                                <option value="">{{ __('– keiner –') }}</option>
                                                @foreach ($specialButtons as $key => $label)
                                                    <option value="{{ $key }}" @selected($step->js_function === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-span-2">
                                            <label class="block text-gray-500">{{ __('Beschreibung') }}</label>
                                            <textarea name="description" rows="2" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">{{ $step->description }}</textarea>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-gray-500">{{ __('E-Mail-Text') }}</label>
                                            <textarea name="email_text" rows="2" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">{{ $step->email_text }}</textarea>
                                        </div>
                                    </div>
                                </template>
                            </form>

                            <form method="POST" action="{{ route('admin.workflows.schritte.destroy', $step) }}" x-ref="deleteForm" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <div class="mt-1.5 flex justify-end">
                                <button
                                    type="button"
                                    @click="window.deleteWithConfirm($refs.deleteForm, {
                                        message: {{ \Illuminate\Support\Js::from(__('Diesen Schritt wirklich endgültig löschen?')) }},
                                    })"
                                    class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    {{ __('Löschen') }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-md border border-dashed border-gray-200 p-3 text-sm text-gray-400">{{ __('Noch keine Schritte in diesem Workflow.') }}</div>
                    @endforelse
                    @if ($steps->isNotEmpty())
                        </div>
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
                            <button type="button" @click="publish()" class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
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
