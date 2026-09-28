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

    Ralf-Bug-Report, 2026-09-28: "wenn ich bei Zeiten gelöst habe, wird
    Details nicht aktualisiert" - das Schloss hier wird nur beim initialen
    Laden serverseitig gerendert (Blade @if), das Lösen im Zeiten-Tab tauscht
    aber nur seinen eigenen Block (#project-zeiten-body) aus, ohne dass
    Details davon erfährt. Fix: Schloss-Block immer im DOM (Alpine
    <template x-if>, nicht mehr Blade @if), reagiert per globalem Event
    "planstunden-linked-state-changed" (ausgelöst von zeiten-body.blade.php
    nach erfolgreichem Lösen) - gleiches Live-Sync-Prinzip wie bei den
    anderen geteilten Fallback-Werten im Projekt-Overlay.
--}}
<div
    x-data="{
        templateId: {{ \Illuminate\Support\Js::from((string) old('project_template_id', $project->project_template_id ?? '')) }},
        hasOwnHours: {{ \Illuminate\Support\Js::from($project->functionGroupHours->isNotEmpty()) }},
        locked: {{ \Illuminate\Support\Js::from($project->functionGroupHours->isNotEmpty()) }},
        // Ralf-Bug-Report, 2026-09-28: der Speichern-Button kommt nicht mehr, wenn das Dropdown
        // entsperrt wird - das Schloss ändert 'locked' rein per Alpine, ohne ein echtes
        // input/change-Event auf #project-detail-form. Der Footer-Speichern-Button (siehe
        // detail.blade.php) hört aber genau darauf, um seinen eigenen Dirty-Check erneut
        // auszuführen (fiel vorher nicht auf, weil der Button wegen des Dirty-Snapshot-Bugs
        // ohnehin dauerhaft sichtbar war). Fix: nach jeder Sperren/Entsperren-Änderung ein
        // echtes change-Event auf dem Formular auslösen. Wichtig: erst NACH einem Makrotask-Tick
        // (setTimeout statt direkt) - sonst hat Alpine die eigene :value/:disabled-Reaktion auf
        // 'locked' noch gar nicht verarbeitet, der Dirty-Check sähe noch den alten DOM-Stand und
        // hielte den Button fälschlich für nicht nötig. Gleiches Timing-Prinzip wie an anderer
        // Stelle in app.blade.php dokumentiert (Alpine.nextTick() ist dafür nicht geeignet).
        async notifyFormChanged() {
            await new Promise((resolve) => setTimeout(resolve, 0));
            document.getElementById('project-detail-form')?.dispatchEvent(new Event('change', { bubbles: true }));
        },
        async toggleLock() {
            if (! this.locked) {
                this.locked = true;
                await this.notifyFormChanged();
                return;
            }
            if (! await window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Schablonen-Verbindung wiederherstellen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Wenn Sie fortfahren, kann diesem Projekt wieder eine Aufwandsschablone zugewiesen werden. Beim nächsten Speichern werden dabei die eigenen, geänderten Planstunden je Funktionsgruppe überschrieben.')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Fortfahren')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            })) { return; }
            this.locked = false;
            await this.notifyFormChanged();
        },
        // Ralf-Bug-Report, 2026-09-28: nach 'Lösen' im Zeiten-Tab (eigene SHA dort, bereits
        // serverseitig gespeichert) meldete 'Schließen'/Blättern dauerhaft 'ungespeicherte
        // Änderungen'. Ursache: dieses 'locked = true' hier ändert reaktiv den versteckten
        // relink_template-Wert zurück auf '0' - aber der Dirty-Snapshot (projectOverlayIsDirty,
        // app.blade.php) wurde nicht neu genommen, obwohl der neue Stand bereits gespeichert ist.
        // Gleicher Fall wie der bestehende window.resnapshotProjectOverlay-Mechanismus (WFS-
        // Personen-Picker): ein Hintergrund-Sync ändert Formularwerte ohne Nutzerzutun, also muss
        // der Snapshot mitgezogen werden. Erst NACH einem Makrotask-Tick aufrufen, aus demselben
        // Grund wie bei notifyFormChanged() oben - Alpines :value-Reaktion auf 'locked' muss den
        // DOM zuerst tatsächlich gepatcht haben.
        async syncAfterExternalRelink() {
            this.hasOwnHours = true;
            this.locked = true;
            await new Promise((resolve) => setTimeout(resolve, 0));
            window.resnapshotProjectOverlay?.();
        },
    }"
    x-on:planstunden-linked-state-changed.window="syncAfterExternalRelink()"
>
    <div class="flex items-center gap-1.5">
        <label class="block text-xs text-gray-500">{{ __('Aufwandsschablone') }}</label>
        <x-info-icon-button
            x-show="templateId"
            x-cloak
            @click="window.openProjectTemplateInfo(templateId)"
            :title="__('Merkmale der gewählten Schablone ansehen')"
        />
        {{-- Schloss-Icon nur relevant, wenn es überhaupt eigene (gelöste) Planstunden gibt -
             als <template x-if>, damit es auch ohne Neuladen erscheinen kann (siehe oben). --}}
        <template x-if="hasOwnHours">
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
        </template>
        {{-- Ralf-Bug-Report, 2026-09-28: "ungespeicherte Änderungen"-Meldung erschien schon direkt
             nach dem Öffnen, ganz ohne Eingabe. Ursache: der Dirty-Snapshot (projectOverlayIsDirty,
             siehe app.blade.php) wird SYNCHRON direkt nach dem innerHTML-Einfügen genommen - noch
             bevor Alpine <template x-if> auswertet. Innerhalb eines x-if existiert dieses Feld zu
             dem Zeitpunkt im echten DOM noch gar nicht (nur inert im <template>), taucht danach
             aber in der FormData auf - macht das Formular für immer fälschlich "dirty". Deshalb
             hier bewusst KEIN x-if: das Feld existiert immer. Der statische value-Fallback muss
             dabei GENAU denselben PHP-Ausgangswert spiegeln, den Alpines :value-Bindung nach der
             Initialisierung berechnet (nicht einfach "0" hart codieren - ein zweiter, subtilerer
             Fund derselben Sitzung: bei einem NICHT gelösten Projekt startet "locked" mit false,
             Alpine berechnet dann "1", ein hartes value="0" hätte also bei jedem normalen Projekt
             denselben Dirty-Fehlalarm ausgelöst). Gleiches Muster wie der bereits dokumentierte
             Fall bei "Projektbeteiligte Personen". --}}
        <input type="hidden" name="relink_template" value="{{ $project->functionGroupHours->isNotEmpty() ? '0' : '1' }}" :value="locked ? '0' : '1'">
    </div>
    {{-- Ralf-Bug-Report, 2026-09-28 (zweiter Fund derselben Ursache): ein disabled-Feld fehlt
         komplett in der FormData (siehe project-percentage-body.blade.php, dieselbe Lektion) -
         ohne statischen @disabled-Fallback stand project_template_id im zu frühen Dirty-Snapshot
         noch drin (Alpines :disabled war noch nicht ausgewertet), fehlte aber danach. --}}
    <select
        name="project_template_id"
        x-model="templateId"
        @disabled($project->functionGroupHours->isNotEmpty())
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
    <template x-if="hasOwnHours">
        <p class="mt-0.5 flex items-center gap-1 text-[11px] text-gray-400" x-show="locked">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            {{ __('Verbindung gelöst - Planstunden sind unabhängig (siehe Reiter „Zeiten").') }}
        </p>
    </template>
</div>
