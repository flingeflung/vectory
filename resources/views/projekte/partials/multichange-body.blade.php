{{--
    Multichange (Ralf, 2026-09-13, nach Vietto-Analyse - Konzept in der
    Backlog-Memory) - drei vom Server bestimmte Zustände in EINEM Partial
    (gleiches Muster wie schedule-body.blade.php): $result gesetzt ->
    Ergebnis, sonst $preview gesetzt -> Vorschau/Bestätigen, sonst
    Formular (Gruppe+Feld+Wert wählen). Kein lokaler Alpine-Zustands-
    automat - der Server entscheidet, welcher Zustand gerade dran ist.

    Ralf, 2026-09-13 (Nachtrag): "Das Gruppieren soll losgelöst sein
    davon, quasi die Grundlage... Multichange kann immer mal wieder
    dazwischen vorkommen, daher muss das prominenter sichtbar sein."
    Deshalb eigener Button in der Übersicht statt im Gruppieren-Panel -
    die Gruppen-Auswahl ist deshalb jetzt Teil DIESES Formulars.
--}}
@if (isset($result))
    <div class="space-y-3">
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-green-800">
            {{ $result['resultText'] }}
        </div>

        @if (! empty($result['unchangedNote']))
            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                {{ $result['unchangedNote'] }}
            </div>
        @endif

        @if (! empty($result['excludedCount']))
            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                {{ trans_choice(':count Projekt wurde von Ihnen ausgenommen und nicht geändert.|:count Projekte wurden von Ihnen ausgenommen und nicht geändert.', $result['excludedCount'], ['count' => $result['excludedCount']]) }}
            </div>
        @endif

        @if ($result['skipped']->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <div class="flex items-center justify-between gap-2">
                    <div class="font-medium">
                        {{ $result['skipReason'] }}
                    </div>
                    <x-copy-button
                        :text="$result['skipped']->map(fn ($p) => $p->source_pn.' – '.$p->title)->implode(PHP_EOL)"
                        :label="__('Übersprungene Projekte in die Zwischenablage kopieren')"
                    />
                </div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($result['skipped'] as $project)
                        <li>{{ $project->source_pn }} – {{ $project->title }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{--
            Ralf-Bug-Report: "habe die Bezeichnungen geändert, aber die
            Übersicht wird nicht aktualisiert" - die Tabelle dahinter ist
            normales, einmal beim Seitenaufruf gerendertes HTML, kein
            reaktiver Zustand. Erster Fix war ein kompletter Seiten-Reload -
            Ralf: "ich verstehe nicht, warum du nicht per Ajax nur die
            Übersicht lädst, sondern die komplett neue Seite." Jetzt per
            Event ('projekte-refresh', siehe refreshRows() in projekte/
            index.blade.php) - lädt nur die schon sichtbaren Zeilen per Ajax
            neu, kein Reload mehr nötig.
        --}}
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('projekte-refresh')); window.dispatchEvent(new CustomEvent('close-modal', { detail: 'multichange' }))"
            class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
        >
            {{ __('Schließen') }}
        </button>
    </div>
@elseif (isset($preview))
    {{--
        Ralf, 2026-09-19: "eine Checkbox je Projekt ... Nur alle angehakten werden bei
        Anwenden auch wirklich geändert ... Beim An- und Abhaken muss die Prüfung
        neu erfolgen." - excluded = abgehakte (ausgenommene) Projekt-IDs. Jedes
        Umschalten fragt die Vorschau serverseitig neu an (dort laufen die fachlichen
        Prüfungen), "Anwenden" schickt dieselbe Liste mit. Scrollposition der
        Tabelle bleibt erhalten.
    --}}
    <div
        class="space-y-3"
        x-data="{
            excluded: {{ \Illuminate\Support\Js::from($excludedIds ?? []) }},
            async toggle(id, checked) {
                // Beide Scrollpositionen merken: die Tabellen-Box UND den ganzen Dialog-Inhalt -
                // sonst finge man nach jeder Änderung wieder oben an zu scrollen.
                const tableTop = document.querySelector('[data-mc-scroll]')?.scrollTop ?? 0;
                const bodyTop = document.getElementById('multichange-body')?.scrollTop ?? 0;
                this.excluded = checked ? this.excluded.filter((x) => x !== id) : [...this.excluded, id];
                await window.reloadMultichange({{ \Illuminate\Support\Js::from(route('projekte.multichange.preview')) }}, {
                    group_id: {{ $group->id }},
                    field: {{ \Illuminate\Support\Js::from($field['key']) }},
                    value: {{ \Illuminate\Support\Js::from($value) }},
                    overwrite_different_workflow: {{ $overwriteDifferentWorkflow ? 1 : 0 }},
                    multi_mode: {{ \Illuminate\Support\Js::from($multiMode ?? 'add') }},
                    function_group_id: {{ \Illuminate\Support\Js::from((string) ($functionGroupId ?? '')) }},
                    exclude: this.excluded,
                });
                const box = document.querySelector('[data-mc-scroll]');
                if (box) box.scrollTop = tableTop;
                const body = document.getElementById('multichange-body');
                if (body) body.scrollTop = bodyTop;
            },
        }"
    >
        <div class="text-xs text-gray-400">{{ __('Gruppe: :name', ['name' => $group->name]) }}</div>

        <div class="text-gray-700">
            {{ $actionText }}
        </div>

        @php
            $applicableCount = $preview['applicable']->count();
            $totalCount = $applicableCount + $preview['skipped']->count() + $preview['unchanged']->count() + $preview['excluded']->count();
        @endphp
        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-700">
            {{ trans_choice(
                ':applicable von :total Projekt dieser Gruppe wird geändert.|:applicable von :total Projekten dieser Gruppe werden geändert.',
                $totalCount,
                ['applicable' => $applicableCount, 'total' => $totalCount]
            ) }}
        </div>

        {{--
            Ralf, 2026-09-14: "das ja ein wirklich mächtiges und auch
            gefährliches Instrument ist" - vor dem unwiderruflichen Anwenden
            je Projekt genau zeigen, was sich ändert. Gilt für alle Felder,
            nicht nur Workflow (siehe describeChangeRows()) - deshalb ist
            der Modal-Dialog jetzt auch breiter (max-width 2xl statt lg,
            siehe layouts/app.blade.php). Eigene, von der restlichen
            Vorschau unabhängige Scroll-Box (max-h-64), damit "Zurück"/
            "Anwenden" bei vielen Projekten nicht erst nach langem Scrollen
            erreichbar sind.

            Nachtrag, gleicher Tag: "Nimm die Projekte, die von einer
            Änderung ausgeschlossen sind, mit in die Tabelle rein (zusätzlich
            zur gesammelten Anzeige), mit einer klaren Kennzeichnung +
            Bemerkung, dass das Projekt nicht geändert wird, weil..." -
            deshalb jetzt ALLE drei Gruppen als Zeilen (ausgeschlossene
            grau/bernstein hinterlegt, "Neuer Wert" bleibt bei denen leer),
            die gesammelten Kurz-Hinweise darunter bleiben zusätzlich stehen,
            aber ohne die frühere (jetzt redundante) Aufzählung.
        --}}
        @if (! empty($changeRows))
            <div data-mc-scroll class="max-h-64 overflow-auto rounded-md border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="sticky top-0 bg-gray-50">
                        <tr>
                            <th class="w-8 px-2 py-1.5" title="{{ __('Angehakte Projekte werden geändert') }}"></th>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Projekt') }}</th>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Alter Wert') }}</th>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">{{ __('Neuer Wert') }}</th>
                            <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    {{ __('Bemerkungen') }}
                                    @if (! empty($changeRowsNoteCopyText))
                                        <x-copy-button
                                            :text="$changeRowsNoteCopyText"
                                            :label="__('Bemerkungen in die Zwischenablage kopieren')"
                                        />
                                    @endif
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($changeRows as $row)
                            <tr @class(['bg-gray-50' => in_array($row['status'], ['unchanged', 'excluded'], true), 'bg-amber-50' => $row['status'] === 'skipped'])>
                                <td class="px-2 py-1.5 align-top">
                                    {{-- Nur betroffene Projekte lassen sich an-/abhaken; übersprungene/unveränderte werden ohnehin nicht geändert. --}}
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 disabled:opacity-40"
                                        @checked($row['status'] === 'applicable')
                                        @disabled(! in_array($row['status'], ['applicable', 'excluded'], true))
                                        @change="toggle({{ $row['id'] }}, $event.target.checked)"
                                        title="{{ in_array($row['status'], ['applicable', 'excluded'], true) ? __('Angehakt: Projekt wird geändert') : __('Wird ohnehin nicht geändert') }}"
                                    >
                                </td>
                                <td class="px-2 py-1.5 align-top {{ $row['status'] === 'applicable' ? 'text-gray-700' : 'text-gray-400' }}">{{ $row['pn'] }} – {{ $row['title'] }}</td>
                                <td class="px-2 py-1.5 align-top {{ $row['status'] === 'applicable' ? 'text-gray-500' : 'text-gray-400' }}">{{ $row['old'] }}</td>
                                <td class="px-2 py-1.5 align-top {{ $row['status'] === 'applicable' ? 'font-medium text-gray-900' : 'text-gray-400' }}">{{ $row['new'] }}</td>
                                <td class="px-2 py-1.5 align-top {{ $row['status'] === 'skipped' ? 'text-amber-800' : 'text-gray-500' }}">{{ $row['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Ralf, 2026-09-19: Sternchen im Kurzformat der Märkte erklären (wie in den
             Projektdetails, siehe system-fields/markets.blade.php) - sonst versteht man
             die Kennzeichnung in der Vorschau nicht. Nur wenn wirklich eines vorkommt. --}}
        @if (($field['key'] ?? null) === 'markets' && (str_contains($actionText, '*') || collect($changeRows ?? [])->contains(fn ($row) => str_contains($row['old'].$row['new'], '*'))))
            <div class="text-xs text-gray-400">* {{ __('Es wird keine Übersetzung für diesen Markt durchgeführt') }}</div>
        @endif

        @if (! empty($unchangedNote))
            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                {{ $unchangedNote }}
            </div>
        @endif

        @if ($preview['skipped']->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                {{ $skipReason }}
            </div>
        @endif

        @if ($preview['applicable']->isEmpty())
            <div class="text-xs text-gray-400">{{ __('Keine Projekte übrig, auf die dies angewendet werden könnte.') }}</div>
        @endif

        <div
            class="flex gap-2"
            x-data="{
                async apply() {
                    const ok = await window.confirmDialog({
                        title: {{ \Illuminate\Support\Js::from(__('Wirklich anwenden?')) }},
                        message: {{ \Illuminate\Support\Js::from(__(':count Projekt(e) werden jetzt unwiderruflich geändert. :action', ['count' => $preview['applicable']->count(), 'action' => $actionText])) }},
                        confirmLabel: {{ \Illuminate\Support\Js::from(__('Anwenden')) }},
                        cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                    });
                    if (! ok) return;
                    await window.reloadMultichange(
                        {{ \Illuminate\Support\Js::from(route('projekte.multichange.apply')) }},
                        {
                            group_id: {{ $group->id }},
                            field: {{ \Illuminate\Support\Js::from($field['key']) }},
                            value: {{ \Illuminate\Support\Js::from($value) }},
                            overwrite_different_workflow: {{ $overwriteDifferentWorkflow ? 1 : 0 }},
                            multi_mode: {{ \Illuminate\Support\Js::from($multiMode ?? 'add') }},
                            function_group_id: {{ \Illuminate\Support\Js::from((string) ($functionGroupId ?? '')) }},
                            exclude: excluded,
                        }
                    );
                },
            }"
        >
            <button
                type="button"
                onclick="window.openMultichange({{ $group->id }}, {{ \Illuminate\Support\Js::from($field['key']) }}, {{ \Illuminate\Support\Js::from(in_array($field['type'], ['attribute_select_multiple', 'markets'], true) ? (array) $value : (string) $value) }}, {{ $overwriteDifferentWorkflow ? 'true' : 'false' }}, {{ \Illuminate\Support\Js::from($multiMode ?? 'add') }}, {{ \Illuminate\Support\Js::from((string) ($functionGroupId ?? '')) }})"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Zurück') }}
            </button>
            @if ($preview['applicable']->isNotEmpty())
                <button
                    type="button"
                    @click="apply()"
                    class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
                >
                    {{ __('Anwenden') }}
                </button>
            @endif
        </div>
    </div>
@else
    @php
        $selectedValueIsArray = is_array($selectedValue ?? null);
    @endphp
    <form
        x-data="{
            groupId: {{ \Illuminate\Support\Js::from((string) ($selectedGroupId ?? '')) }},
            field: {{ \Illuminate\Support\Js::from($selectedField ?? '') }},
            value: {{ \Illuminate\Support\Js::from($selectedValueIsArray ? '' : ($selectedValue ?? '')) }},
            multiValue: {{ \Illuminate\Support\Js::from($selectedValueIsArray ? $selectedValue : []) }},
            multiMode: {{ \Illuminate\Support\Js::from($selectedMultiMode ?? 'add') }},
            functionGroupId: {{ \Illuminate\Support\Js::from((string) ($selectedFunctionGroupId ?? '')) }},
            overwrite: {{ ($selectedOverwriteDifferentWorkflow ?? false) ? 'true' : 'false' }},
            // Ralf, 2026-09-15: Mehrfachauswahl-Pulldown-Zusatzfelder
            // schicken multiValue statt value (siehe unten) - fieldTypes
            // ist eine einmalig mitgegebene Nachschlagetabelle, damit das
            // Formular beim Abschicken weiß, welches der beiden gemeint ist.
            fieldTypes: {{ \Illuminate\Support\Js::from(collect($fields)->pluck('type', 'key')) }},
            get isMultiField() { return ['attribute_select_multiple', 'markets'].includes(this.fieldTypes[this.field]); },
        }"
        @submit.prevent="window.reloadMultichange({{ \Illuminate\Support\Js::from(route('projekte.multichange.preview')) }}, { group_id: groupId, field, value: isMultiField ? multiValue : value, overwrite_different_workflow: overwrite ? 1 : 0, multi_mode: multiMode, function_group_id: functionGroupId })"
        {{--
            Ralf-Bug-Report, 2026-09-18: Klick auf "Prüfen" tat scheinbar
            nichts bzw. zeigte "Neuer Wert ist erforderlich", obwohl Werte
            sichtbar gewählt waren - Ursache: alle Feldtypen teilen sich
            EIN "value"-Alpine-Property (siehe x-data oben). Jeder Tausch
            von "value" (z.B. Personen-ID 168) landet per x-model auch in
            den unsichtbaren <input>s der anderen, gerade nicht gezeigten
            Felder (nur per x-show/CSS ausgeblendet, nicht aus dem DOM
            entfernt) - z.B. in einem Zahlenfeld mit max="2". Die native
            Browser-Validierung blockiert dann das ganze Formular still
            (kein Fokus möglich, da kein "name"-Attribut), noch bevor
            @submit.prevent überhaupt läuft. novalidate überlässt die
            Validierung komplett dem Server/$formErrors oben - passt auch
            zur bestehenden Konvention, keine nativen Browser-Dialoge zu
            verwenden.
        --}}
        novalidate
        class="space-y-3"
    >
        @if (isset($formErrors))
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                @foreach ($formErrors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <div>
            <label class="block text-xs text-gray-500">{{ __('Projektgruppe') }}</label>
            <select x-model="groupId" @change="field = ''; value = ''" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                <option value="">{{ __('– Gruppe wählen –') }}</option>
                @foreach ($groups as $g)
                    <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->projects_count }})</option>
                @endforeach
            </select>
            @if ($groups->isEmpty())
                <p class="mt-1 text-xs text-gray-400">{{ __('Noch keine Projektgruppe vorhanden - über „Gruppieren" in der Übersicht anlegen.') }}</p>
            @endif
        </div>

        <div x-show="groupId" x-cloak class="space-y-3 border-t border-gray-100 pt-3">
            <div>
                <label class="block text-xs text-gray-500">{{ __('Feld') }}</label>
                <select x-model="field" @change="value = ''; multiValue = []; multiMode = 'add'; functionGroupId = ''" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('– Feld wählen –') }}</option>
                    @foreach ($fields as $f)
                        <option value="{{ $f['key'] }}">{{ $f['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @foreach ($fields as $f)
                <div x-show="field === {{ \Illuminate\Support\Js::from($f['key']) }}" x-cloak>
                    @if (! empty($f['hint']))
                        @if (is_array($f['hint']))
                            <ul class="mb-1 list-outside list-disc space-y-0.5 pl-4 text-xs text-amber-700">
                                @foreach ($f['hint'] as $hintLine)
                                    <li>{{ $hintLine }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mb-1 text-xs text-amber-700">{{ $f['hint'] }}</p>
                        @endif
                    @endif
                    @unless (in_array($f['type'], ['workflow_step', 'attribute_select_multiple', 'project_people', 'markets'], true))
                        <label class="block text-xs text-gray-500">{{ __('Neuer Wert') }}</label>
                    @endunless
                    @if (in_array($f['type'], ['text', 'attribute_text'], true))
                        <input
                            type="text"
                            x-model="value"
                            @if ($f['max_length'] ?? null) maxlength="{{ $f['max_length'] }}" @endif
                            class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                        >
                        @if ($f['max_length'] ?? null)
                            <p class="mt-0.5 text-xs text-gray-400">{{ __('Max. :max Zeichen', ['max' => $f['max_length']]) }}</p>
                        @endif
                    @elseif (in_array($f['type'], ['textarea', 'attribute_textarea'], true))
                        <textarea
                            x-model="value"
                            rows="3"
                            @if ($f['max_length'] ?? null) maxlength="{{ $f['max_length'] }}" @endif
                            class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                        ></textarea>
                        @if ($f['max_length'] ?? null)
                            <p class="mt-0.5 text-xs text-gray-400">{{ __('Max. :max Zeichen', ['max' => $f['max_length']]) }}</p>
                        @endif
                    @elseif (in_array($f['type'], ['date', 'attribute_date'], true))
                        <input type="date" x-model="value" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    @elseif (in_array($f['type'], ['select', 'attribute_select', 'attribute_boolean'], true))
                        <select x-model="value" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– auswählen –') }}</option>
                            @foreach ($f['options'] as $optValue => $optLabel)
                                <option value="{{ $optValue }}">{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    @elseif ($f['type'] === 'attribute_number')
                        @php
                            $numberStep = $f['number_decimals'] !== null ? (1 / (10 ** $f['number_decimals'])) : 'any';
                        @endphp
                        <input
                            type="number"
                            x-model="value"
                            step="{{ $numberStep }}"
                            @if ($f['number_min'] !== null) min="{{ rtrim(rtrim((string) $f['number_min'], '0'), '.') }}" @endif
                            @if ($f['number_max'] !== null) max="{{ rtrim(rtrim((string) $f['number_max'], '0'), '.') }}" @endif
                            class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                        >
                        @if ($f['number_min'] !== null || $f['number_max'] !== null)
                            <p class="mt-0.5 text-xs text-gray-400">
                                @if ($f['number_min'] !== null && $f['number_max'] !== null)
                                    {{ __('Bereich :min – :max', ['min' => $f['number_min'], 'max' => $f['number_max']]) }}
                                @elseif ($f['number_min'] !== null)
                                    {{ __('Mindestens :min', ['min' => $f['number_min']]) }}
                                @else
                                    {{ __('Höchstens :max', ['max' => $f['number_max']]) }}
                                @endif
                            </p>
                        @endif
                    @elseif ($f['type'] === 'attribute_select_multiple')
                        {{--
                            Ralf, 2026-09-15 (Konzept mit Ralf abgestimmt):
                            Mehrfachauswahl-Pulldown - Nutzer wählt sowohl die
                            Werte als auch den Modus (Ergänzen/Überschreiben).
                            Im Prüfen-Dialog sieht er dann je Projekt genau,
                            was sich dadurch ändert.
                        --}}
                        <label class="block text-xs text-gray-500">{{ __('Werte') }}</label>
                        <select x-model="multiValue" multiple size="{{ min(6, max(3, count($f['options']))) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            @foreach ($f['options'] as $optValue => $optLabel)
                                <option value="{{ $optValue }}">{{ $optLabel }}</option>
                            @endforeach
                        </select>
                        <div class="mt-2 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-select" x-model="multiMode" value="add" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Ergänzen') }}
                                    <span class="block text-gray-400">{{ __('Die gewählten Werte werden bei jedem Projekt zu den bestehenden hinzugefügt, nichts geht verloren.') }}</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-select" x-model="multiMode" value="overwrite" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Überschreiben') }}
                                    <span class="block text-gray-400">{{ __('Die bisherige Auswahl wird bei jedem Projekt komplett durch die hier gewählten Werte ersetzt.') }}</span>
                                </span>
                            </label>
                        </div>
                    @elseif ($f['type'] === 'markets')
                        {{--
                            Ralf, 2026-09-19: "Märkte per MC zuweisen" - Auswahl als
                            scrollbare Checkbox-Liste (wie beim Bearbeiten der
                            Märkte im Projekt selbst) statt eines Mehrfach-Pulldowns,
                            dazu drei Modi (Hinzufügen/Entfernen/Überschreiben) über
                            multiMode. Werte laufen als Liste (multiValue).
                        --}}
                        <label class="block text-xs text-gray-500">{{ __('Märkte') }}</label>
                        <div class="mt-0.5 max-h-48 space-y-0.5 overflow-y-auto rounded-md border border-gray-300 p-1.5">
                            @foreach ($f['options'] as $marketId => $marketLabel)
                                <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                    <input type="checkbox" value="{{ $marketId }}" x-model="multiValue" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                    <span>{{ $marketLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-markets" x-model="multiMode" value="add" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Hinzufügen') }}
                                    <span class="block text-gray-400">{{ __('Die gewählten Märkte werden bei allen Projekten ergänzt, bestehende bleiben erhalten.') }}</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-markets" x-model="multiMode" value="remove" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Entfernen') }}
                                    <span class="block text-gray-400">{{ __('Die gewählten Märkte werden bei allen Projekten entfernt.') }}</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-markets" x-model="multiMode" value="overwrite" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Überschreiben') }}
                                    <span class="block text-gray-400">{{ __('Bei allen Projekten werden die gespeicherten Märkte entfernt und stattdessen die gewählten gespeichert.') }}</span>
                                </span>
                            </label>
                        </div>
                    @elseif ($f['type'] === 'project_people')
                        {{--
                            Ralf, 2026-09-18: "Wir brauchen Projektbeteiligte
                            Personen bei MC" - Ziel ist die Funktionsgruppe
                            (nicht das Projekt als Ganzes), plus eigene
                            Hinzufügen/Entfernen-Aktion ("mach Entfernen und
                            Hinzufügen separat, dann kann der Benutzer selber
                            wählen") - wiederverwendet multiMode wie beim
                            Mehrfachauswahl-Pulldown oben, hier mit den Werten
                            add/remove statt add/overwrite.

                            Ralf-Korrektur, gleicher Tag: "Das darf doch nur
                            dort passieren, wo die Person auch Mitglied in der
                            gewählten Funktionsgruppe ist" - Personen-Auswahl
                            ist deshalb doch eine Kaskade (Fktgrp zuerst),
                            aber clientseitig gefiltert statt per fetch()
                            nachgeladen wie beim "Von anderem Kunden
                            importieren"-Dialog: die komplette Personen-Liste
                            + Mitgliederzuordnung je Fktgrp steckt schon im
                            Feld (siehe MultichangeFieldCatalog), ein Wechsel
                            der Funktionsgruppe braucht also keinen weiteren
                            Serverkontakt. x-if statt x-show auf den
                            <option>-Elementen (Ralf-Bug-Report vom
                            Projektschablonen-Import-Dialog, gleicher Tag:
                            x-show verhält sich auf <option> unzuverlässig).
                        --}}
                        <label class="block text-xs text-gray-500">{{ __('Funktionsgruppe') }}</label>
                        <select x-model="functionGroupId" @change="value = ''" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– Funktionsgruppe wählen –') }}</option>
                            @foreach ($f['function_groups'] as $fgId => $fgName)
                                <option value="{{ $fgId }}">{{ $fgName }}</option>
                            @endforeach
                        </select>
                        <div
                            x-data="{
                                allPeople: {{ \Illuminate\Support\Js::from(collect($f['options'])->map(fn ($label, $id) => ['id' => (string) $id, 'label' => $label])->values()) }},
                                functionGroupMembers: {{ \Illuminate\Support\Js::from(collect($f['function_group_members'])->map(fn ($ids) => collect($ids)->map(fn ($id) => (string) $id)->all())) }},
                                get filteredPeople() {
                                    const memberIds = this.functionGroupMembers[this.functionGroupId] || [];
                                    return this.allPeople.filter((p) => memberIds.includes(p.id));
                                },
                            }"
                        >
                            <label class="mt-2 block text-xs text-gray-500">{{ __('Person') }}</label>
                            <select x-model="value" :disabled="!functionGroupId" class="mt-0.5 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-400">
                                <template x-if="!functionGroupId">
                                    <option value="">{{ __('– zuerst Funktionsgruppe wählen –') }}</option>
                                </template>
                                <template x-if="functionGroupId && filteredPeople.length === 0">
                                    <option value="">{{ __('– keine Mitglieder in dieser Funktionsgruppe –') }}</option>
                                </template>
                                <template x-if="functionGroupId && filteredPeople.length > 0">
                                    <option value="">{{ __('– Person wählen –') }}</option>
                                </template>
                                <template x-for="p in filteredPeople" :key="p.id">
                                    <option :value="p.id" x-text="p.label"></option>
                                </template>
                            </select>
                        </div>
                        <div class="mt-2 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-people" x-model="multiMode" value="add" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Hinzufügen') }}
                                    <span class="block text-gray-400">{{ __('Die Person wird der gewählten Funktionsgruppe bei allen Projekten zugeordnet.') }}</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-1.5 text-xs text-gray-700">
                                <input type="radio" name="multi-mode-people" x-model="multiMode" value="remove" class="mt-0.5 text-indigo-600">
                                <span>
                                    {{ __('Entfernen') }}
                                    <span class="block text-gray-400">{{ __('Die Person wird aus der gewählten Funktionsgruppe bei allen Projekten entfernt.') }}</span>
                                </span>
                            </label>
                        </div>
                    @elseif ($f['type'] === 'workflow_step')
                        {{--
                            Ralf, 2026-09-14: "Zunächst muss man einen WF
                            auswählen, dann dort den entsprechenden WFS" -
                            zweistufige Kaskade, aber übermittelt wird nur
                            die eine WorkflowStep-ID (siehe value unten).
                            wfId ist reine Client-Anzeigehilfe, kein
                            Formularfeld - bei "Zurück" aus der Vorschau
                            serverseitig aus dem schon gewählten Schritt
                            zurückgerechnet, damit die Kaskade nicht wieder
                            bei "– Workflow wählen –" anfängt.
                        --}}
                        @php
                            $initialWfId = collect($f['steps'])->firstWhere('id', (int) ($selectedValue ?? 0))['workflow_id'] ?? '';
                        @endphp
                        <div x-data="{ wfId: {{ \Illuminate\Support\Js::from((string) $initialWfId) }} }">
                            <label class="block text-xs text-gray-500">{{ __('Workflow') }}</label>
                            <select x-model="wfId" @change="value = ''" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('– Workflow wählen –') }}</option>
                                @foreach ($f['workflows'] as $wf)
                                    <option value="{{ $wf['id'] }}">{{ $wf['name'] }}</option>
                                @endforeach
                            </select>

                            {{--
                                Ralf-Bug-Report beim Testen: ein verschachteltes
                                x-show auf diesem inneren Wrapper reagierte
                                nicht zuverlässig auf wfId-Änderungen (Alpine-
                                Effekt band sich offenbar einmalig an den
                                initialen leeren Wert). Immer sichtbar statt
                                bedingt gerendert umgeht das Problem robust -
                                die Auswahlliste ist ohnehin leer/nur
                                Platzhalter, solange kein Workflow gewählt ist.
                            --}}
                            <div class="mt-2">
                                <label class="block text-xs text-gray-500">{{ __('Workflow-Schritt') }}</label>
                                <select x-model="value" :disabled="! wfId" class="mt-0.5 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-400">
                                    <option value="">{{ __('– auswählen –') }}</option>
                                    <template x-for="step in {{ \Illuminate\Support\Js::from($f['steps']) }}.filter(s => String(s.workflow_id) === String(wfId))" :key="step.id">
                                        <option :value="step.id" x-text="step.title"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    @endif

                    @if ($f['key'] === 'workflow_id')
                        <label class="mt-2 flex items-start gap-2 text-xs text-gray-600">
                            <input type="checkbox" x-model="overwrite" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            <span>
                                {{ __('Andere Workflows überschreiben') }}
                                <span class="block text-gray-400">{{ __('Achtung! Wenn diese Option angehakt ist, wird bei Projekten mit einem anderen Workflow dieser und damit auch der bisherige Projektfortschritt überschrieben. Wenn die Option nicht angehakt ist, bleiben Projekte mit einem anderen Workflow unangetastet.') }}</span>
                            </span>
                        </label>
                    @endif
                </div>
            @endforeach

            <button
                type="submit"
                :disabled="! field"
                class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ __('Prüfen') }}
            </button>
        </div>
    </form>
@endif
