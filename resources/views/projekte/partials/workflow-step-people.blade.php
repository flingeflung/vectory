{{--
    Ralf, 2026-09-12: Wer an diesem Schritt je Funktionsgruppe zuständig
    ist (Override pro Schritt hat Vorrang, sonst Fallback auf die
    projektweite Zuweisung - siehe Task::assignedPeopleFor()), MIT
    Möglichkeit, den Schritt-Override direkt hier zu ändern (Vietto-
    Vorbild: Klick auf die Funktionsgruppe öffnet eine Checkbox-Liste
    aller Gruppenmitglieder). Eigene Partial, damit
    ProjectWorkflowStepController::updatePeople() nach dem Speichern NUR
    diesen einen Schritt-Block neu laden kann, braucht $project/$pws.

    Hört zusätzlich auf "project-people-changed" (wie das
    Projektbeteiligte-Personen-Feld, siehe project_people.blade.php) und
    lädt sich dann selbst neu - sonst bliebe DIESER Schritt stehen, wenn
    sich die projektweite Zuweisung durch eine Änderung an EINEM ANDEREN
    Schritt ändert, obwohl dieser Schritt sie nur über den Fallback
    übernimmt (Ralf-Bug-Report mit Screenshot: Schritt 2 zeigte weiterhin
    "–", obwohl Schritt 4 die Personen per Override bereits hatte und die
    Wechselwirkung project_people schon korrekt aktualisiert hatte).
--}}
@if ($pws->workflowStep->functionGroups->isNotEmpty())
    @php
        // Erstansprechpartner (★) ist ein projektweites Konzept
        // (project_people.is_primary), unabhängig davon, ob die
        // tatsächliche Zuständigkeit an diesem Schritt aus dem Override
        // oder dem Fallback kommt - Ralf, 2026-09-12: fehlte hier bisher
        // ganz, sowohl in dieser Anzeige als auch im Zuweisen-Dialog
        // (siehe workflow-step-people-picker.blade.php).
        $primaryPersonIdByGroup = \App\Models\ProjectPerson::query()
            ->where('project_id', $pws->project_id)
            ->where('is_primary', true)
            ->pluck('person_id', 'function_group_id');
    @endphp
    <div
        class="shrink-0 text-xs"
        id="wfs-people-{{ $pws->id }}"
        x-data="{
            init() {
                this.onChanged = (e) => {
                    if (e.detail.projectId === {{ $project->id }}) {
                        this.refresh();
                    }
                };
                window.addEventListener('project-people-changed', this.onChanged);
            },
            destroy() {
                window.removeEventListener('project-people-changed', this.onChanged);
            },
            async refresh() {
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.workflow-steps.personen.summary', [$project, $pws])) }});
                if (! response.ok) return;
                document.getElementById({{ \Illuminate\Support\Js::from('wfs-people-'.$pws->id) }}).outerHTML = await response.text();
            },
        }"
    >
        @foreach ($pws->workflowStep->functionGroups as $group)
            @php $groupPeople = \App\Models\Task::assignedPeopleFor($pws, $group); @endphp
            <div class="mb-1">
                <div class="flex items-center gap-1">
                    <span class="font-semibold text-gray-800">{{ $group->name }}</span>
                    {{-- Klickbares sieht wie ein Button aus, kein Text-Link
                         (CLAUDE.md-Konvention) - gleiches kompaktes
                         Icon-Button-Muster wie "Termine berechnen" oben. --}}
                    <button
                        type="button"
                        @click.stop="window.openWorkflowStepPeople({{ $project->id }}, {{ $pws->id }}, {{ $group->id }})"
                        class="text-gray-400 hover:text-gray-700"
                        title="{{ __('Zuständige Person(en) ändern') }}"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                    </button>
                </div>
                @forelse ($groupPeople as $person)
                    <div class="text-gray-700">
                        {{ $person->fullName() }}@if ($primaryPersonIdByGroup->get($group->id) === $person->id)<span class="text-amber-500" title="{{ __('Erstansprechpartner') }}">&#9733;</span>@endif
                    </div>
                @empty
                    <div class="text-gray-400">&ndash;</div>
                @endforelse
            </div>
        @endforeach
    </div>
@endif
