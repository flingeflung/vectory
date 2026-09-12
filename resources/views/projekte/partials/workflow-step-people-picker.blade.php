{{-- Ralf, 2026-09-12 ("analog zu Vietto"): Checkbox-Liste aller
     Mitglieder EINER Funktionsgruppe, umschaltbar für EINEN Schritt
     (project_workflow_step_people - Vietto-Vorbild: workflow_pers_cx2,
     ajax_workflow_editperson.php/ajax_workflow_getfktpers.php). Vorangehakt
     ist, wer AKTUELL zuständig ist (siehe ProjectWorkflowStepController::
     peopleForm()). Speichert sofort bei jeder Änderung, übernimmt dabei
     immer den KOMPLETTEN angehakten Zustand (kein Einzel-Toggle). --}}
<div
    x-data="{
        saving: false,
        async save() {
            this.saving = true;
            try {
                const personIds = Array.from(this.$refs.list.querySelectorAll('input:checked')).map((el) => el.value);
                const body = new URLSearchParams();
                personIds.forEach((id) => body.append('person_ids[]', id));
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.workflow-steps.personen.update', [$project, $pws, $group])) }}, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body,
                });
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Änderung fehlgeschlagen. Bitte erneut versuchen.')) }});
                    return;
                }
                document.getElementById({{ \Illuminate\Support\Js::from('wfs-people-'.$pws->id) }}).outerHTML = await response.text();
                // Ralf, 2026-09-12: eine hier neu zugewiesene Person landet
                // sofort auch in project_people (siehe updatePeople()) - das
                // Projektbeteiligte-Personen-Feld in Stammdaten muss das
                // mitbekommen, sitzt aber in einem anderen Tab.
                window.dispatchEvent(new CustomEvent('project-people-changed', { detail: { projectId: {{ $project->id }} } }));
            } finally {
                this.saving = false;
            }
        },
    }"
>
    <div class="mb-2 text-xs text-gray-500">{{ __(':group – Zuständige für diesen Schritt', ['group' => $group->name]) }}</div>

    {{-- Ralf, 2026-09-12: "Wenn Pauline inaktiv ist, möchte ich sie auch
         nicht mehr zur Zuweisung sehen" - eine inaktive Person taucht hier
         nur noch auf, wenn sie diesem Schritt schon zugewiesen ist (dann
         bleibt sie sichtbar/entfernbar, sonst würde ein Speichern sie
         stillschweigend rauswerfen). Alle anderen inaktiven Mitglieder
         werden komplett ausgeblendet, nicht nur gesperrt. --}}
    @php $visibleMembers = $members->filter(fn ($person) => $person->active || $currentPersonIds->contains($person->id)); @endphp

    @if ($visibleMembers->isEmpty())
        <div class="text-xs text-gray-400">{{ __('Diese Funktionsgruppe hat aktuell keine wählbaren Mitglieder.') }}</div>
    @else
        <div class="space-y-1" x-ref="list">
            @foreach ($visibleMembers as $person)
                <label class="flex items-center gap-1.5 text-sm {{ $person->active ? 'text-gray-700' : 'text-gray-400' }}">
                    <input
                        type="checkbox"
                        value="{{ $person->id }}"
                        :disabled="saving"
                        @change="save()"
                        @checked($currentPersonIds->contains($person->id))
                        class="shrink-0 rounded border-gray-300"
                    >
                    {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-absence-icon :person="$person" />@if ($primaryPersonId === $person->id)<span class="text-amber-500" title="{{ __('Erstansprechpartner') }}">&#9733;</span>@endif
                </label>
            @endforeach
        </div>
    @endif
</div>
