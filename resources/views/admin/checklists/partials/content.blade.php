<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: Checklisten, per D&D sortierbar (gleiches Muster wie Workflows). --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.checklisten')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newChecklist: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Checklisten') }}</span>
                <button type="button" @click="newChecklist = !newChecklist; if (newChecklist) $nextTick(() => $refs.newChecklistName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newChecklist" x-cloak method="POST" action="{{ route('admin.checklisten.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newChecklistName" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </form>

                @if ($checklists->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Checklisten angelegt.') }}</div>
                @else
                    <div
                        x-data="{
                            async saveOrder() {
                                const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                await fetch({{ \Illuminate\Support\Js::from(route('admin.checklisten.reorder')) }}, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                    body: JSON.stringify({ checklists: ids }),
                                });
                            },
                        }"
                        x-sort="saveOrder()"
                    >
                        @foreach ($checklists as $checklist)
                            <div x-sort:item="{{ $checklist->id }}" class="flex items-center gap-1 rounded {{ $selectedChecklist?->id === $checklist->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                <a
                                    :href="navUrl({ checklist: {{ $checklist->id }} })"
                                    onclick="return window.navigateOrConfirm(event)"
                                    @if ($selectedChecklist?->id === $checklist->id) data-selected @endif
                                    class="flex flex-1 items-center justify-between py-1 pr-2 {{ $selectedChecklist?->id === $checklist->id ? 'font-medium text-indigo-700' : ($checklist->active ? 'text-gray-700' : 'text-gray-400') }}"
                                >
                                    <span>{{ $checklist->name }}{{ ! $checklist->active ? ' [i]' : '' }}</span>
                                    <span class="text-xs text-gray-400">{{ $checklist->pointsCount() }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: die gewählte Checkliste (Name/Aktiv, Abschnitte+Punkte, Kopieren-zu-Kunde). --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedChecklist)
            <div class="shrink-0 space-y-2 border-b border-gray-100 p-3">
                <form
                    method="POST"
                    action="{{ route('admin.checklisten.update', $selectedChecklist) }}"
                    data-row-form
                    x-data="{ dirty: false }"
                    @input="dirty = window.formIsDirty($el, window.__checklistsDirtyForms)"
                    @submit="dirty = false; window.__checklistsDirtyForms.delete($el)"
                    class="flex items-center gap-3"
                >
                    @csrf
                    <input type="text" name="name" value="{{ $selectedChecklist->name }}" placeholder="{{ __('Name') }}" required class="flex-1 rounded-md border-gray-300 py-1 text-sm font-medium text-gray-900">
                    <label class="flex shrink-0 items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" name="active" value="1" @checked($selectedChecklist->active) class="rounded border-gray-300">
                        {{ __('Aktiv (wählbar für neue Zuordnungen)') }}
                    </label>
                    <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>

                <div class="flex items-center justify-between gap-2" x-data>
                    @if ($otherTenants->isNotEmpty())
                        <form
                            method="POST"
                            action="{{ route('admin.checklisten.copy-to-tenant', $selectedChecklist) }}"
                            x-data="{
                                targetTenantId: '',
                                async confirmAndSubmit(e) {
                                    if (await window.confirmDialog({
                                        title: {{ \Illuminate\Support\Js::from(__('Zu anderem Kunden kopieren')) }},
                                        message: {{ \Illuminate\Support\Js::from(__('Legt eine eigenständige Kopie dieser Checkliste (inkl. aller Abschnitte und Punkte) beim gewählten Kunden an. Die Kopie startet inaktiv.')) }},
                                        confirmLabel: {{ \Illuminate\Support\Js::from(__('Kopieren')) }},
                                    })) {
                                        e.target.submit();
                                    }
                                },
                            }"
                            @submit.prevent="confirmAndSubmit($event)"
                            class="flex items-center gap-1"
                        >
                            @csrf
                            <select name="target_tenant_id" x-model="targetTenantId" required class="rounded-md border-gray-300 text-xs">
                                <option value="">{{ __('– Kunde wählen –') }}</option>
                                @foreach ($otherTenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" :disabled="!targetTenantId" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:opacity-40">
                                {{ __('Zu Kunde kopieren') }}
                            </button>
                        </form>
                    @else
                        <span></span>
                    @endif

                    <form method="POST" action="{{ route('admin.checklisten.destroy', $selectedChecklist) }}" x-ref="deleteChecklistForm" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteChecklistForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Checkliste inklusive aller Abschnitte und Punkte wirklich endgültig löschen? Zuordnungen und Abhak-Stände bei Projekten gehen dabei ebenfalls verloren.')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Checkliste löschen') }}
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-3" x-data="{ newSection: false }">
                @foreach ($selectedChecklist->sections as $section)
                    <div class="rounded-md border border-gray-200 p-2" x-data="{ newPoint: false }">
                        <div class="mb-1.5 flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.checklisten.abschnitte.update', $section) }}" data-row-form class="min-w-0 flex-1" x-data="{ value: {{ \Illuminate\Support\Js::from($section->title) }} }">
                                @csrf
                                <input
                                    type="text"
                                    name="title"
                                    x-model="value"
                                    @blur="if (value.trim() !== '' && value !== {{ \Illuminate\Support\Js::from($section->title) }}) $el.form.requestSubmit()"
                                    class="w-full rounded-md border-transparent bg-transparent py-0.5 text-sm font-medium text-gray-900 hover:border-gray-300 focus:border-gray-300"
                                >
                            </form>
                            <form method="POST" action="{{ route('admin.checklisten.abschnitte.destroy', $section) }}" x-ref="deleteSectionForm{{ $section->id }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                @click="window.deleteWithConfirm($refs.deleteSectionForm{{ $section->id }}, {
                                    message: {{ \Illuminate\Support\Js::from(__('Diesen Abschnitt inklusive aller Punkte wirklich endgültig löschen?')) }},
                                })"
                                class="shrink-0 rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-600"
                                title="{{ __('Abschnitt löschen') }}"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div
                            x-data="{
                                async saveOrder() {
                                    const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                    await fetch({{ \Illuminate\Support\Js::from(route('admin.checklisten.punkte.reorder')) }}, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                        body: JSON.stringify({ checklist_section_id: {{ $section->id }}, points: ids }),
                                    });
                                },
                            }"
                            x-sort="saveOrder()"
                            class="space-y-0.5 pl-1"
                        >
                            @forelse ($section->points as $point)
                                <div x-sort:item="{{ $point->id }}" class="flex items-center gap-1">
                                    <span x-sort:handle class="cursor-move px-1 text-xs text-gray-300 hover:text-gray-500">⠿</span>
                                    <form method="POST" action="{{ route('admin.checklisten.punkte.update', $point) }}" data-row-form class="min-w-0 flex-1" x-data="{ value: {{ \Illuminate\Support\Js::from($point->title) }} }">
                                        @csrf
                                        <input
                                            type="text"
                                            name="title"
                                            x-model="value"
                                            @blur="if (value.trim() !== '' && value !== {{ \Illuminate\Support\Js::from($point->title) }}) $el.form.requestSubmit()"
                                            class="w-full rounded-md border-transparent bg-transparent py-0.5 text-xs text-gray-700 hover:border-gray-300 focus:border-gray-300"
                                        >
                                    </form>
                                    <form method="POST" action="{{ route('admin.checklisten.punkte.destroy', $point) }}" x-ref="deletePointForm{{ $point->id }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <button
                                        type="button"
                                        @click="window.deleteWithConfirm($refs.deletePointForm{{ $point->id }}, { message: {{ \Illuminate\Support\Js::from(__('Diesen Punkt wirklich endgültig löschen?')) }} })"
                                        class="shrink-0 rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-600"
                                        title="{{ __('Punkt löschen') }}"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            @empty
                                <div class="pl-5 text-xs text-gray-400">{{ __('Noch keine Punkte in diesem Abschnitt.') }}</div>
                            @endforelse
                        </div>

                        <form x-show="newPoint" x-cloak method="POST" action="{{ route('admin.checklisten.punkte.store') }}" class="mt-1 flex items-center gap-1 pl-1">
                            @csrf
                            <input type="hidden" name="checklist_section_id" value="{{ $section->id }}">
                            <input type="text" name="title" x-ref="newPointTitle{{ $section->id }}" placeholder="{{ __('Punkt') }}" required class="min-w-0 flex-1 rounded-md border-gray-300 text-xs">
                            <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </form>
                        <button
                            type="button"
                            x-show="!newPoint"
                            @click="newPoint = true; $nextTick(() => $refs['newPointTitle{{ $section->id }}'].focus())"
                            class="mt-1 inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                        >
                            + {{ __('Punkt') }}
                        </button>
                    </div>
                @endforeach

                <form x-show="newSection" x-cloak method="POST" action="{{ route('admin.checklisten.abschnitte.store') }}" class="flex items-center gap-2 rounded-md border border-gray-200 p-2">
                    @csrf
                    <input type="hidden" name="checklist_id" value="{{ $selectedChecklist->id }}">
                    <input type="text" name="title" x-ref="newSectionTitle" placeholder="{{ __('Abschnitt') }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                    <button type="button" @click="newSection = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>
                <button
                    type="button"
                    x-show="!newSection"
                    @click="newSection = true; $nextTick(() => $refs.newSectionTitle.focus())"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    + {{ __('Abschnitt') }}
                </button>

                @if ($selectedChecklist->sections->isEmpty())
                    <div class="rounded-md border border-dashed border-gray-200 p-3 text-sm text-gray-400">{{ __('Noch keine Abschnitte in dieser Checkliste.') }}</div>
                @endif
            </div>
        @else
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Checklisten-Katalog') }}</div>
                <p class="text-xs text-gray-400">{{ __('Wähle links eine Checkliste aus, um sie zu bearbeiten.') }}</p>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
                @forelse ($checklists as $checklist)
                    <div class="text-sm font-medium {{ $checklist->active ? 'text-gray-700' : 'text-gray-400' }}">
                        {{ $checklist->name }}{{ ! $checklist->active ? ' [i]' : '' }}
                        <span class="ml-1 text-xs text-gray-300">– {{ trans_choice(':count Punkt|:count Punkte', $checklist->pointsCount(), ['count' => $checklist->pointsCount()]) }}</span>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">{{ __('Noch keine Checklisten angelegt.') }}</div>
                @endforelse
            </div>
        @endif
    </div>
</div>
