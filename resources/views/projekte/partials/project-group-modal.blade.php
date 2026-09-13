{{--
    "Meine Projektgruppen" (Ralf, 2026-09-13, analog Viettos gruppen/
    gruppen_cx/gruppen_pers_cx) - EIN Modal-Partial für zwei Kontexte:
    $project === null -> Übersichts-Modus (Checkbox-Spalte + Bulk-Aktionen,
    siehe projekte/index.blade.php), $project gesetzt -> Einzelprojekt-Modus
    (zwei Buttons, siehe projekte/partials/detail.blade.php). Die
    ausgewählte Gruppe (groupId) lebt bewusst im globalen
    $store.projectGrouping (layouts/app.blade.php) statt in einem lokalen
    x-data - das Übersichts-Häkchen-Spalten-Toggle und dieses Modal müssen
    sich denselben Auswahl-Zustand teilen, auch wenn sie an verschiedenen
    Stellen im DOM sitzen.
--}}
@php($modalName = 'projektgruppen-panel-'.($project?->id ?? 'uebersicht'))

<x-modal name="{{ $modalName }}" max-width="sm">
    <div
        class="flex max-h-[70vh] flex-col"
        x-data="{
            loading: false,
            sharing: false,
            renameValue: '',
            newGroupName: '',
            async refresh() {
                this.loading = true;
                try {
                    const url = {{ \Illuminate\Support\Js::from(route('projektgruppen.panel')) }}{{ $project ? " + '?project_id={$project->id}'" : '' }};
                    const html = await fetch(url).then((r) => r.text());
                    document.getElementById('project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}').innerHTML = html;
                } finally {
                    this.loading = false;
                }
            },
            async onGroupChange() {
                @if (! $project)
                    await $store.projectGrouping.loadMembers();
                @endif
                await this.refresh();
            },
            async createGroup() {
                if (! this.newGroupName.trim()) return;
                const fd = new FormData();
                fd.append('name', this.newGroupName.trim());
                const html = await fetch({{ \Illuminate\Support\Js::from(route('projektgruppen.store')) }}, {
                    method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then((r) => r.text());
                document.getElementById('project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}').innerHTML = html;
                this.newGroupName = '';
            },
            async renameGroup() {
                if (! $store.projectGrouping.groupId || ! this.renameValue.trim()) return;
                const fd = new FormData();
                fd.append('name', this.renameValue.trim());
                fd.append('_method', 'PATCH');
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId, {
                    method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                await this.refresh();
            },
            async deleteGroup() {
                const select = document.querySelector('#project-group-panel-body-{{ $project?->id ?? 'uebersicht' }} select');
                const viewerCount = parseInt(select?.selectedOptions[0]?.dataset.viewers || '1', 10);
                const otherCount = viewerCount - 1;
                const message = otherCount > 1
                    ? {{ \Illuminate\Support\Js::from(__('Diese Gruppe wird auch für :count weitere Personen gelöscht, die sie sehen können. Wirklich endgültig löschen?')) }}.replace(':count', otherCount)
                    : otherCount === 1
                        ? {{ \Illuminate\Support\Js::from(__('Diese Gruppe wird auch für eine weitere Person gelöscht, die sie sehen kann. Wirklich endgültig löschen?')) }}
                        : {{ \Illuminate\Support\Js::from(__('Diese Gruppe wirklich endgültig löschen?')) }};
                if (! await window.confirmDialog({ title: {{ \Illuminate\Support\Js::from(__('Gruppe löschen?')) }}, message, confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }}, cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }} })) return;
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId, {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                $store.projectGrouping.groupId = '';
                @if (! $project) await $store.projectGrouping.loadMembers(); @endif
                await this.refresh();
            },
            async leaveGroup() {
                if (! await window.confirmDialog({{ \Illuminate\Support\Js::from(__('Diese Gruppe wirklich verlassen? Andere Personen, die sie sehen, behalten weiter Zugriff.')) }})) return;
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/verlassen', {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                $store.projectGrouping.groupId = '';
                @if (! $project) await $store.projectGrouping.loadMembers(); @endif
                await this.refresh();
            },
            async clearGroup() {
                if (! await window.confirmDialog({{ \Illuminate\Support\Js::from(__('Wirklich alle Projekte aus dieser Gruppe entfernen? Die Gruppe selbst bleibt bestehen.')) }})) return;
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/leeren', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                @if (! $project) await $store.projectGrouping.loadMembers(); @endif
                await this.refresh();
            },
            @if ($project)
            async addThisProject() {
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/projekte/{{ $project->id }}', {
                    method: 'PUT', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                await this.refresh();
            },
            async removeThisProject() {
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/projekte/{{ $project->id }}', {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                await this.refresh();
            },
            @else
            showUrl() {
                return $store.projectGrouping.groupId ? '/projektgruppen/' + $store.projectGrouping.groupId + '/anzeigen' : '#';
            },
            async addAllFiltered() {
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/alle' + window.location.search, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                await $store.projectGrouping.loadMembers();
                await this.refresh();
            },
            async removeAllFiltered() {
                await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/alle' + window.location.search, {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                await $store.projectGrouping.loadMembers();
                await this.refresh();
            },
            @endif
            async openShare() {
                this.sharing = true;
                const html = await fetch('/projektgruppen/' + $store.projectGrouping.groupId + '/personen').then((r) => r.text());
                document.getElementById('project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}').innerHTML = html;
            },
            closeShare() {
                this.sharing = false;
                this.refresh();
            },
            async toggleShare(userId, checked) {
                const url = '/projektgruppen/' + $store.projectGrouping.groupId + '/personen/' + userId;
                const html = await fetch(url, {
                    method: checked ? 'POST' : 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then((r) => r.text());
                document.getElementById('project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}').innerHTML = html;
            },
        }"
        @open-modal.window="$event.detail === '{{ $modalName }}' && refresh()"
    >
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ $project ? __('Projekt gruppieren') : __('Projekte gruppieren') }}</h3>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: '{{ $modalName }}' }))"
                class="text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Schließen') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4 text-sm">
            <div id="project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}">{{ __('Lädt…') }}</div>
        </div>
    </div>
</x-modal>
