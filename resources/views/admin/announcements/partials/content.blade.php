<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Mitteilungen, analog zur Liste der Mail-Vorlagen. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.mitteilungen')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newAnnouncement: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Mitteilungen') }}</span>
                <button type="button" @click="newAnnouncement = !newAnnouncement; if (newAnnouncement) $nextTick(() => $refs.newAnnouncementText.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => window.keepListScroll($el, 'list-scroll:announcements'))">
                <form x-show="newAnnouncement" x-cloak method="POST" action="{{ route('admin.mitteilungen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="title" x-ref="newAnnouncementText" maxlength="120" placeholder="{{ __('Titel der Mitteilung') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </form>

                @if ($announcements->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Mitteilungen angelegt.') }}</div>
                @else
                    @foreach ($announcements as $announcement)
                        <a
                            :href="navUrl({ announcement: {{ $announcement->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedAnnouncement?->id === $announcement->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selectedAnnouncement?->id === $announcement->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            <span class="truncate">{{ $announcement->title }}</span>
                            <span class="text-xs font-normal text-gray-400">
                                @if (! $announcement->isActive())
                                    {{ __('abgelaufen') }}
                                @elseif ($announcement->tenants->isEmpty())
                                    {{ __('ohne Empfänger') }}
                                @elseif ($announcement->ends_on)
                                    {{ __('bis :date', ['date' => $announcement->ends_on->format('d.m.Y')]) }}
                                @else
                                    {{ __('unbefristet') }}
                                @endif
                            </span>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: die gewählte Mitteilung zum Bearbeiten, oder ein Hinweis, wenn keine ausgewählt ist. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedAnnouncement)
            <div class="flex min-h-0 flex-1 flex-col" x-data="{ dirty: false }">
                {{-- Speichern- und Lösch-Formular sind Geschwister, nicht verschachtelt (siehe Mail-Vorlagen). --}}
                <form
                    id="announcement-form-{{ $selectedAnnouncement->id }}"
                    data-row-form
                    method="POST"
                    action="{{ route('admin.mitteilungen.update', $selectedAnnouncement) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    @input="dirty = window.formIsDirty($el, window.__announcementsDirtyForms)"
                    @submit="dirty = false; window.__announcementsDirtyForms.delete($el)"
                >
                @csrf
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Titel') }}</label>
                        <input type="text" name="title" value="{{ $selectedAnnouncement->title }}" maxlength="120" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Text (optional)') }}</label>
                        <textarea name="text" rows="4" maxlength="500" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ $selectedAnnouncement->text }}</textarea>
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Titel und Text erscheinen auf der Startseite in der Kachel „Meldungen“. Bitte kurz halten.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500" title="{{ __('Am letzten Tag wird die Mitteilung noch angezeigt, danach nicht mehr. Ohne Datum bleibt sie, bis Sie sie löschen.') }}">{{ __('Anzeigen bis (einschließlich, optional)') }}</label>
                        <input type="date" name="ends_on" value="{{ $selectedAnnouncement->ends_on?->format('Y-m-d') }}" class="mt-0.5 rounded-md border-gray-300 text-sm">
                    </div>
                    @if ($chooseOrganizations)
                        <div x-ref="organizations">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs text-gray-500">{{ __('Empfänger-Organisationen') }}</label>
                                <span class="flex gap-1">
                                    <button type="button" @click="$refs.organizations.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true); $dispatch('input')" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Alle') }}</button>
                                    <button type="button" @click="$refs.organizations.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false); $dispatch('input')" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Keiner') }}</button>
                                </span>
                            </div>
                            <div class="mt-1 max-h-56 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 text-sm">
                                @foreach ($organizations as $organization)
                                    <label class="flex items-center gap-2 text-gray-700">
                                        <input type="checkbox" name="tenant_ids[]" value="{{ $organization->id }}" class="rounded border-gray-300" @checked($selectedAnnouncement->tenants->contains('id', $organization->id))>
                                        {{ $organization->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-0.5 text-xs text-gray-400">{{ __('Die Mitteilung sehen die Benutzer dieser Organisationen. Ohne Haken sieht sie niemand.') }}</p>
                        </div>
                    @endif
                    @if ($selectedAnnouncement->tenants->isEmpty())
                        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">{{ __('Diese Mitteilung hat noch keine Empfänger und wird niemandem angezeigt.') }}</p>
                    @endif
                </div>
                </form>

                <form x-ref="deleteForm" method="POST" action="{{ route('admin.mitteilungen.destroy', $selectedAnnouncement) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Mitteilung wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                    <button type="submit" form="announcement-form-{{ $selectedAnnouncement->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wählen Sie links eine Mitteilung aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
