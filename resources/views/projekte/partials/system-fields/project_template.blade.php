{{--
    Ralf, 2026-09-18: einem Projekt eine der Projektschablonen (Step 1 der
    Kapa-Planung) zuordnen können - reiner Verweis, keine Übernahme von
    Merkmalen/Stunden ins Projekt. Gleiches Auswahl-Muster wie
    project_type.blade.php.

    Ralf, 2026-09-19: kleiner Info-Button daneben (nur bei gültiger Auswahl),
    öffnet die Merkmale/Stunden der gewählten Schablone rein lesend im
    globalen Fetch-Overlay (window.openProjectTemplateInfo(), siehe
    layouts/app.blade.php) - reagiert live auf die Auswahl, nicht erst nach
    dem Speichern.

    Ralf, 2026-09-28: sobald die Planstunden-Verbindung im Zeiten-Tab
    "gelöst" wurde (eigene Werte je Funktionsgruppe, siehe
    Project::functionGroupHours()), hätte eine Neuzuweisung hier bisher
    keine sichtbare Wirkung gehabt ("scheint keine Auswirkungen zu haben").
    Statt das stillschweigend wirkungslos zu lassen: Feld wird gesperrt
    (Schloss-Icon, gleiches Muster wie "fixieren" bei der Prozentualen
    Aufteilung), mit Hinweis. Aufschließen fragt vorher explizit nach, weil
    das nächste Speichern dabei die eigenen Planstunden verwirft (siehe
    ProjectController::update(), Feld "relink_template") - das Aufschließen
    selbst ändert noch nichts, erst das bestätigte Speichern danach.
--}}
<div
    x-data="{
        templateId: {{ \Illuminate\Support\Js::from((string) old('project_template_id', $project->project_template_id ?? '')) }},
        locked: {{ \Illuminate\Support\Js::from($project->functionGroupHours->isNotEmpty()) }},
        async toggleLock() {
            if (! this.locked) {
                this.locked = true;
                return;
            }
            if (! await window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Schablonen-Verbindung wiederherstellen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Wenn Sie fortfahren, kann diesem Projekt wieder eine Aufwandsschablone zugewiesen werden. Beim nächsten Speichern werden dabei die eigenen, geänderten Planstunden je Funktionsgruppe überschrieben.')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Fortfahren')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            })) { return; }
            this.locked = false;
        },
    }"
>
    <div class="flex items-center gap-1.5">
        <label class="block text-xs text-gray-500">{{ __('Aufwandsschablone') }}</label>
        <x-info-icon-button
            x-show="templateId"
            x-cloak
            @click="window.openProjectTemplateInfo(templateId)"
            :title="__('Merkmale der gewählten Schablone ansehen')"
        />
        {{-- Schloss-Icon nur relevant, wenn es überhaupt eigene (gelöste) Planstunden gibt. --}}
        @if ($project->functionGroupHours->isNotEmpty())
            <button
                type="button"
                @click="toggleLock()"
                :class="locked ? 'bg-gray-100 text-gray-400 hover:bg-gray-200' : 'bg-indigo-600 text-white'"
                class="flex h-4 w-4 shrink-0 items-center justify-center rounded"
                :title="locked ? {{ \Illuminate\Support\Js::from(__('Von der Schablone gelöst - Auswahl gesperrt. Klicken zum Aufschließen.')) }} : {{ \Illuminate\Support\Js::from(__('Aufgeschlossen - beim Speichern wird die Schablone wieder verknüpft.')) }}"
            >
                <svg x-show="locked" class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v2H5a1 1 0 00-1 1v8a1 1 0 001 1h10a1 1 0 001-1V9a1 1 0 00-1-1h-1V6a4 4 0 00-4-4zm2 6V6a2 2 0 10-4 0v2h4z" clip-rule="evenodd" />
                </svg>
                <svg x-show="! locked" x-cloak class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a4 4 0 00-4 4v2H5a1 1 0 00-1 1v8a1 1 0 001 1h10a1 1 0 001-1V9a1 1 0 00-1-1H8V6a2 2 0 114 0 1 1 0 102 0 4 4 0 00-4-4z" />
                </svg>
            </button>
            <input type="hidden" name="relink_template" :value="locked ? '0' : '1'">
        @endif
    </div>
    <select
        name="project_template_id"
        x-model="templateId"
        :disabled="locked"
        class="mt-0.5 w-full max-w-sm rounded border-gray-300 py-1 text-sm"
        :class="locked ? 'bg-gray-100 text-gray-400' : ''"
    >
        <option value="">{{ __('– nicht zugewiesen –') }}</option>
        @foreach ($availableProjectTemplates as $template)
            <option
                value="{{ $template->id }}"
                {{-- @selected trotz x-model: der Snapshot der Änderungsprüfung (projectOverlayIsDirty) wird
                     vor Alpines Initialisierung genommen - ohne serverseitig gesetzte Auswahl meldete der
                     Dialog "Ungespeicherte Änderungen", obwohl schon gespeichert war. --}}
                @selected((string) old('project_template_id', $project->project_template_id) === (string) $template->id)
                @class(['text-gray-400' => ! $template->active])
            >{{ $template->name }}{{ ! $template->active ? ' [i]' : '' }}</option>
        @endforeach
    </select>
    @if ($project->functionGroupHours->isNotEmpty())
        <p class="mt-0.5 flex items-center gap-1 text-[11px] text-gray-400" x-show="locked">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            {{ __('Verbindung gelöst - Planstunden sind unabhängig (siehe Reiter „Zeiten").') }}
        </p>
    @endif
</div>
