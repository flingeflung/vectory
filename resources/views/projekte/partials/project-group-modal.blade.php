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

{{--
    Ralf-Bug-Report, 2026-09-13: im Übersichts-Modus ($project === null)
    soll man Projekte per Häkchen in der Tabelle markieren können,
    WÄHREND dieses Panel offen ist (Gruppe auswählen im Panel, dann
    Häkchen im Hintergrund setzen) - deshalb dort "blocking" aus (siehe
    <x-modal>-Doku). Im Einzelprojekt-Modus gibt's dahinter keine
    Häkchen-Spalte, bleibt beim normalen (blockierenden) Verhalten.
--}}
<x-modal name="{{ $modalName }}" max-width="sm" :blocking="$project !== null" :draggable="true">
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
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projektgruppen.store')) }}, {
                    method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const html = await response.text();
                // Neue Gruppe direkt auswählen (Ralf, 2026-09-13) - Store VOR
                // dem Einfügen setzen, damit das frisch eingefügte <select>
                // (x-model auf den Store) sie gleich als ausgewählt anzeigt.
                $store.projectGrouping.groupId = response.headers.get('X-Created-Group-Id') || '';
                document.getElementById('project-group-panel-body-{{ $project?->id ?? 'uebersicht' }}').innerHTML = html;
                this.newGroupName = '';
                @if (! $project)
                    await $store.projectGrouping.loadMembers();
                @endif
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
        {{-- Ralf (nach Vietto-Vorbild): Häkchen-Spalte blendet sich aus,
             sobald das Panel geschlossen wird - nicht nur beim Öffnen an.
             Das $watch hier greift auf "show" der äußeren <x-modal>-
             Komponente zu (Alpines verschachtelte x-data-Scopes reichen
             Eltern-Properties automatisch an Kind-Scopes durch). --}}
        {{-- Ralf (nach Vietto-Vorbild): Häkchen-Spalte blendet sich aus,
             sobald das Panel geschlossen wird - nicht nur beim Öffnen an.
             Das $watch hier greift auf "show" der äußeren <x-modal>-
             Komponente zu (Alpines verschachtelte x-data-Scopes reichen
             Eltern-Properties automatisch an Kind-Scopes durch). --}}
        @if (! $project)
            x-init="$watch('show', (value) => { if (! value) { $store.projectGrouping.active = false; } })"
        @endif
        @open-modal.window="$event.detail === '{{ $modalName }}' && refresh()"
    >
        <div class="flex shrink-0 cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-2" data-drag-handle title="{{ __('Ziehen zum Verschieben') }}">
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
