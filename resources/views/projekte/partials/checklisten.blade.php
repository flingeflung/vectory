{{--
    Checklisten-Reiter im Projekt (Ralf, 2026-09-12, nach Vietto-Analyse) -
    Zuordnung bleibt rein manuell, neues Projekt startet ohne aktivierte
    Checkliste. Wiederverwendbares Partial: initial in detail.blade.php
    eingebunden, danach per fetch() ausgetauscht (siehe
    ProjectChecklistController), gleiches Muster wie workflow-step-people.
--}}
<div id="project-checklisten-{{ $project->id }}" class="space-y-3">
    <div>
        <button
            type="button"
            @click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'checklisten-auswaehlen-{{ $project->id }}' }))"
            class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Checklisten auswählen') }}
        </button>
    </div>

    @forelse ($project->projectChecklists as $projectChecklist)
        @php($checklist = $projectChecklist->checklist)
        <div class="rounded-md border border-gray-200 p-3">
            <div class="mb-1 flex items-center justify-between">
                <div class="font-medium text-gray-900">
                    {{ $checklist->name }}
                    @if (! $checklist->active)
                        <span class="text-xs font-normal text-amber-600">{{ __('(Checkliste inaktiv)') }}</span>
                    @endif
                </div>
                @if ($projectChecklist->activatedBy)
                    <span class="text-xs text-gray-400">{{ __('aktiviert von :name am :date', ['name' => $projectChecklist->activatedBy->fullName(), 'date' => $projectChecklist->activated_at?->format('d.m.Y')]) }}</span>
                @endif
            </div>

            @foreach ($checklist->sections as $section)
                <div class="mt-2">
                    <div class="mb-0.5 text-xs font-semibold text-gray-500">{{ $section->title }}</div>
                    <div class="space-y-0.5 pl-1">
                        @foreach ($section->points as $point)
                            @php($state = $project->projectChecklistPoints->firstWhere('checklist_point_id', $point->id))
                            <label class="flex items-start gap-1.5 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    class="mt-0.5 shrink-0 rounded border-gray-300"
                                    @checked($state?->done)
                                    @change="
                                        fetch({{ \Illuminate\Support\Js::from(route('projekte.checklisten.punkte.toggle', [$project, $point])) }}, {
                                            method: 'PATCH',
                                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                            body: 'done=' + ($event.target.checked ? '1' : '0'),
                                        }).then(r => r.text()).then(html => {
                                            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('project-checklisten-{{ $project->id }}');
                                            const current = document.getElementById('project-checklisten-{{ $project->id }}');
                                            if (fresh && current) { current.replaceWith(fresh); }
                                        })
                                    "
                                >
                                <span>
                                    {{ $point->title }}
                                    @if ($state?->done && $state->doneBy)
                                        <span class="text-xs text-gray-400">{{ __(', erledigt von :name am :date um :time', ['name' => $state->doneBy->fullName(), 'date' => $state->done_at?->format('d.m.Y'), 'time' => $state->done_at?->format('H:i')]) }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="text-gray-400">&ndash; {{ __('Keine Checklisten für dieses Projekt aktiviert') }} &ndash;</div>
    @endforelse
</div>

<x-modal name="checklisten-auswaehlen-{{ $project->id }}" max-width="sm">
    <div class="flex max-h-[70vh] flex-col">
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Checkliste aktivieren') }}</h3>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'checklisten-auswaehlen-{{ $project->id }}' }))"
                class="text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Schließen') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form
            method="POST"
            action="{{ route('projekte.checklisten.update', $project) }}"
            x-data="{
                saving: false,
                async save(e) {
                    this.saving = true;
                    const ids = [...this.$refs.list.querySelectorAll('input:checked')].map(el => el.value);
                    try {
                        const response = await fetch(e.target.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Overlay': '1',
                                'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }},
                            },
                            body: new URLSearchParams(ids.map(id => ['checklist_ids[]', id])),
                        });
                        const html = await response.text();
                        const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('project-checklisten-{{ $project->id }}');
                        const current = document.getElementById('project-checklisten-{{ $project->id }}');
                        if (! response.ok || ! fresh) {
                            await window.notifyDialog({{ \Illuminate\Support\Js::from(__('Speichern hat nicht funktioniert, bitte nochmal versuchen.')) }});
                            return;
                        }
                        current.replaceWith(fresh);
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'checklisten-auswaehlen-{{ $project->id }}' }));
                    } finally {
                        this.saving = false;
                    }
                },
            }"
            @submit.prevent="save($event)"
            class="min-h-0 flex-1 overflow-y-auto p-4"
        >
            @if ($allChecklists->isEmpty())
                <div class="text-sm text-gray-400">{{ __('Für diesen Kunden sind noch keine Checklisten angelegt (Admin > Checklisten).') }}</div>
            @else
                <div x-ref="list" class="space-y-1.5 text-sm">
                    @foreach ($allChecklists as $checklist)
                        <label class="flex items-center gap-2 {{ $checklist->active ? 'text-gray-700' : 'text-gray-400' }}">
                            <input type="checkbox" value="{{ $checklist->id }}" class="rounded border-gray-300" @checked($project->projectChecklists->contains('checklist_id', $checklist->id))>
                            {{ $checklist->name }}{{ ! $checklist->active ? ' [i]' : '' }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" :disabled="saving" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:opacity-50">
                        {{ __('Übernehmen') }}
                    </button>
                </div>
            @endif
        </form>
    </div>
</x-modal>
