{{-- Ralf, 2026-09-12 ("analog zu Vietto"): Checkbox-Liste aller
     Mitglieder EINER Funktionsgruppe, umschaltbar für EINEN Schritt
     (project_workflow_step_people - Vietto-Vorbild: workflow_pers_cx2,
     ajax_workflow_editperson.php/ajax_workflow_getfktpers.php). Jeder
     Klick speichert sofort (kein Speichern-Button), lädt danach den
     Zuständigkeits-Block dieses Schritts im Hintergrund neu. --}}
<div
    x-data="{
        loading: false,
        async toggle(personId) {
            this.loading = true;
            try {
                const response = await fetch(`/projekte/{{ $project->id }}/workflow-steps/{{ $pws->id }}/personen/{{ $group->id }}/${personId}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Änderung fehlgeschlagen. Bitte erneut versuchen.')) }});
                    return;
                }
                document.getElementById({{ \Illuminate\Support\Js::from('wfs-people-'.$pws->id) }}).outerHTML = await response.text();
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <div class="mb-2 text-xs text-gray-500">{{ __(':group – Zuständige für diesen Schritt', ['group' => $group->name]) }}</div>

    @if ($usesProjectDefault)
        <div class="mb-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-600">
            @if ($projectDefaultPeople->isEmpty())
                {{ __('Aktuell niemand zuständig - es gilt die projektweite Zuweisung, die für diese Funktionsgruppe aber noch niemanden enthält.') }}
            @else
                {{-- Semikolon statt Komma zwischen Personen: "Nachname, Vorname"
                     je Person macht ein Komma als Trenner sonst zweideutig
                     lesbar (gleiche Konvention wie project_people.blade.php). --}}
                {{ __('Aktuell zuständig über die projektweite Zuweisung: :names. Ein Häkchen hier legt stattdessen eine eigene Auswahl NUR für diesen Schritt fest.', ['names' => $projectDefaultPeople->map->fullName()->join('; ')]) }}
            @endif
        </div>
    @endif

    @if ($members->isEmpty())
        <div class="text-xs text-gray-400">{{ __('Diese Funktionsgruppe hat noch keine Mitglieder.') }}</div>
    @else
        <div class="space-y-1">
            @foreach ($members as $person)
                <label class="flex items-center gap-1.5 text-sm {{ $person->active ? 'text-gray-700' : 'text-gray-400' }}">
                    <input
                        type="checkbox"
                        :disabled="loading"
                        @change="toggle({{ $person->id }})"
                        @checked($currentPersonIds->contains($person->id))
                        class="shrink-0 rounded border-gray-300"
                    >
                    {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                </label>
            @endforeach
        </div>
    @endif
</div>
