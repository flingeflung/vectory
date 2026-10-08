@php
    $sectionLabels = ['stammdaten' => __('Stammdaten'), 'ablaufdaten' => __('Ablaufdaten'), 'typspezifisch' => __('Typspezifische Attribute')];

    // Ralf, 2026-09-11: "die müssen auch verstehen, was da passiert, ohne
    // ein Handbuch durchlesen zu müssen" - bei Feldern, deren Verhalten
    // beim Kopieren nicht einfach "1:1 übernommen oder nicht" ist, steht
    // die tatsächliche Auswirkung direkt als Tooltipp dabei, nicht nur im
    // Kopf der Entwickler. Die eigentliche Kopierlogik selbst ist Phase 2 -
    // hier schon mal die Erklärung, damit die Vorlage von Anfang an
    // verständlich bedienbar ist.
    $fieldHints = [
        'title' => __('Angehakt: wird 1:1 übernommen, bei mehreren Kopien mit Zusatz „Kopie 1/2/…“. Nicht angehakt: bleibt im neuen Projekt leer, muss dort per Hand nachgetragen werden.'),
        'status' => __('Status: wird beim Kopieren immer auf „Geplant“ gesetzt, der Haken wirkt hier nicht. Erstellungsstatus: angehakt = wird übernommen, nicht angehakt = bleibt leer.'),
        'workflow_id' => __('Wenn der ursprüngliche Workflow nicht mehr aktuell ist, wird automatisch die neueste Version verknüpft.'),
        'project_people' => __('Angehakt: Projektbeteiligte Personen werden mitkopiert. Wenn eine davon inaktiv ist, wird vor dem Kopieren gewarnt.'),
    ];
@endphp

<div class="mb-4 shrink-0 rounded-lg border border-gray-200 bg-white p-3" x-data="{ dirty: false }">
    <form
        method="POST"
        action="{{ route('admin.projektkopie-vorlagen.max-kopien.update') }}"
        class="flex items-end gap-2"
        @input="dirty = window.formIsDirty($el, window.__copyTemplatesDirtyForms)"
        @submit="dirty = false; window.__copyTemplatesDirtyForms.delete($el)"
    >
        @csrf
        <div>
            <label class="block text-xs text-gray-500">{{ __('Projekte kopieren: max. Anzahl Kopien') }}</label>
            <input type="number" name="max_project_copies" min="1" max="50" value="{{ $tenant->max_project_copies }}" required class="mt-0.5 w-24 rounded-md border-gray-300 py-1 text-sm">
        </div>
        <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Speichern') }}
        </button>
    </form>
</div>

<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Vorlagen, analog zu den Mail-Vorlagen. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.projektkopie-vorlagen')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newTemplate: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Vorlagen') }}</span>
                <button type="button" @click="newTemplate = !newTemplate; if (newTemplate) $nextTick(() => $refs.newTemplateName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => window.keepListScroll($el, 'list-scroll:copy-templates'))">
                <form x-show="newTemplate" x-cloak method="POST" action="{{ route('admin.projektkopie-vorlagen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newTemplateName" placeholder="{{ __('Name der Vorlage') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </form>

                @if ($templates->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Vorlagen angelegt.') }}</div>
                @else
                    @foreach ($templates as $template)
                        <a
                            :href="navUrl({ vorlage: {{ $template->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedTemplate?->id === $template->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selectedTemplate?->id === $template->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $template->name }}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: gewählte Vorlage - Name, Feld-Auswahl je Bereich. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedTemplate)
            {{-- Name, Planungs-Bereiche und Felder werden gemeinsam über "Speichern" gespeichert (Ralf, 2026-10-08, vorher wirkte jeder Haken sofort);
                 Änderungsprüfung wie bei den übrigen Verwaltungsformularen (window.__copyTemplatesDirtyForms). --}}
            <div class="flex min-h-0 flex-1 flex-col" x-data="{ dirty: false }">
                <form
                    id="copy-template-form"
                    method="POST"
                    action="{{ route('admin.projektkopie-vorlagen.update', $selectedTemplate) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    @input="dirty = window.formIsDirty($el, window.__copyTemplatesDirtyForms)"
                    @submit="dirty = false; window.__copyTemplatesDirtyForms.delete($el)"
                >
                    @csrf
                    <div class="shrink-0 border-b border-gray-100 p-3">
                        <label class="block text-xs text-gray-500">{{ __('Name der Vorlage') }}</label>
                        <input type="text" name="name" value="{{ $selectedTemplate->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-3" x-ref="grid">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-xs text-gray-400">{{ __('Markierte Attribute werden beim Kopieren von Projekten vom Quell- in das Zielprojekt übernommen. Änderungen gelten erst nach „Speichern“.') }}</p>
                            <div class="flex shrink-0 gap-3">
                                <button type="button" @click="$refs.grid.querySelectorAll('input[type=checkbox]').forEach((box) => box.checked = true); $el.closest('form').dispatchEvent(new Event('input', { bubbles: true }))" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">{{ __('Alle markieren') }}</button>
                                <button type="button" @click="$refs.grid.querySelectorAll('input[type=checkbox]').forEach((box) => box.checked = false); $el.closest('form').dispatchEvent(new Event('input', { bubbles: true }))" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">{{ __('Keinen markieren') }}</button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            {{-- Planungs-Bereiche (Ralf, 2026-10-08): zusätzlich zu den Feldern --}}
                            <div>
                                <div class="mb-1.5 text-xs font-semibold text-gray-500">{{ __('Planung') }}</div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    @foreach (\App\Services\PlanningTransfer::parts() as $partKey => $partLabel)
                                        @continue(! in_array($partKey, \App\Models\CopyTemplate::COPYABLE_PLANNING_PARTS, true))
                                        <label class="flex items-center gap-1.5 text-gray-700">
                                            <input type="checkbox" name="planning_parts[]" value="{{ $partKey }}" class="rounded border-gray-300" @checked(in_array($partKey, $selectedTemplate->planningParts(), true))>
                                            {{ $partLabel }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-gray-400">{{ __('Dauern, Sperren und Termine werden nur kopiert, wenn auch der Workflow kopiert wird und dieselbe Workflow-Version gilt. Workflow, Projektbeteiligte und Aufwandsprofil (mit eigenen Planstunden) stehen bei den Feldern („Ablaufdaten“).') }}</p>
                            </div>
                            @foreach ($sectionLabels as $section => $sectionLabel)
                                @if ($attributesBySection->get($section, collect())->isNotEmpty())
                                    <div>
                                        <div class="mb-1.5 text-xs font-semibold text-gray-500">{{ $sectionLabel }}</div>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                            @foreach ($attributesBySection->get($section) as $attribute)
                                                <label class="flex items-center gap-1.5 text-gray-700">
                                                    <input type="checkbox" name="fields[]" value="{{ $attribute->id }}" class="rounded border-gray-300" @checked(in_array($attribute->id, $selectedFieldIds, true))>
                                                    {{ $attribute->label }}
                                                    @if ($attribute->system)
                                                        <span class="text-gray-300" title="{{ __('Festes Feld') }}">🔒</span>
                                                    @endif
                                                    @if (isset($fieldHints[$attribute->key]))
                                                        <span class="cursor-help text-gray-300" title="{{ $fieldHints[$attribute->key] }}">ⓘ</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </form>

                <form x-ref="deleteForm" method="POST" action="{{ route('admin.projektkopie-vorlagen.destroy', $selectedTemplate) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Vorlage wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                    <button type="submit" form="copy-template-form" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links eine Vorlage aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
