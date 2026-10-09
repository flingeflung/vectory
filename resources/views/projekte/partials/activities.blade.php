{{--
    Reiter "Vorgänge" eines Projekts (Ralf, 2026-10-09, Vorbild Vietto): automatisch protokollierte Vorgänge und von Hand erfasste Notizen mit
    eigenem Datum, Hervorhebung (gelb) und Änderungsvermerk. Daten: App\Support\ProjectActivityBlock. Nach jeder Aktion wird dieser Block neu
    gerendert und ersetzt (siehe ProjectActivityController); bewusst kein <form>, weil die globalen Absende-Routinen des Projekt-Overlays jedes
    Formular im Overlay als Projektänderung senden würden.
--}}
@php
    $categoryValues = $categories->pluck('value')->all();
    $today = now()->local()->format('Y-m-d');
@endphp
<div
    x-data="{
        cats: {{ \Illuminate\Support\Js::from($selectedCategories) }},
        newEntry: false,
        text: '',
        date: {{ \Illuminate\Support\Js::from($today) }},
        highlight: false,
        busy: false,
        async call(method, url, payload = {}) {
            if (this.busy) return;
            this.busy = true;
            try {
                const response = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                    body: method === 'GET' ? undefined : JSON.stringify({ ...payload, categories: this.cats.join(',') }),
                });
                if (! response.ok) {
                    const error = response.status === 422 ? Object.values((await response.json()).errors || {})[0]?.[0] : null;
                    await window.notifyDialog(error || {{ \Illuminate\Support\Js::from(__('Das hat nicht geklappt. Bitte versuchen Sie es erneut.')) }});
                    return;
                }
                const box = document.getElementById({{ \Illuminate\Support\Js::from('project-activities-'.$project->id) }});
                box.innerHTML = (await response.json()).html;
                Alpine.initTree(box);
            } finally {
                this.busy = false;
            }
        },
        save() {
            if (this.text.trim() === '') return;
            this.call('POST', {{ \Illuminate\Support\Js::from(route('projekte.vorgaenge.store', $project)) }}, { message: this.text, occurred_on: this.date, is_highlighted: this.highlight });
        },
    }"
    class="text-xs text-gray-600"
>
    <div class="mb-2 flex flex-wrap items-center gap-x-5 gap-y-1">
        @if ($categories->count() > 1)
            @foreach ($categories as $category)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" value="{{ $category->value }}" x-model="cats" class="rounded border-gray-300">
                    <span class="inline-block h-2 w-2 rounded-full {{ $category->dotClass() }}"></span>
                    {{ $category->label() }}
                </label>
            @endforeach
        @endif
        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                @click="call('POST', {{ \Illuminate\Support\Js::from(route('projekte.vorgaenge.order', $project)) }}, { newest_first: {{ $newestFirst ? 'false' : 'true' }} })"
                title="{{ $newestFirst ? __('Zeigt gerade die neuesten Vorgänge oben. Klicken, um die ältesten oben zu zeigen. Die Einstellung gilt nur für Sie.') : __('Zeigt gerade die ältesten Vorgänge oben. Klicken, um die neuesten oben zu zeigen. Die Einstellung gilt nur für Sie.') }}"
                class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 font-medium text-gray-700 hover:bg-gray-200"
            >{{ $newestFirst ? __('Neueste zuerst') : __('Älteste zuerst') }} {{ $newestFirst ? '↓' : '↑' }}</button>
            @if ($canEdit)
                <button
                    type="button"
                    x-show="! newEntry"
                    @click="newEntry = true; setTimeout(() => $refs.newText.focus(), 30)"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 font-medium text-gray-700 hover:bg-gray-200"
                >+ {{ __('Neuer Vorgang') }}</button>
            @endif
        </div>
    </div>

    @if ($canEdit)
        <div x-show="newEntry" x-cloak class="mb-3 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-2">
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-1.5">{{ __('Datum') }}
                    <input type="date" x-model="date" class="rounded-md border-gray-300 py-0.5 text-xs" title="{{ __('Das Datum, auf das sich der Vorgang bezieht. Standard ist heute; so lassen sich auch Ereignisse nachtragen.') }}">
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" x-model="highlight" class="rounded border-gray-300">
                    {{ __('Hervorheben') }}
                </label>
            </div>
            <textarea x-ref="newText" x-model="text" rows="3" maxlength="5000" placeholder="{{ __('Notiz zum Projektablauf …') }}" class="w-full rounded-md border-gray-300 text-xs"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" @click="newEntry = false; text = ''; highlight = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                <button type="button" x-show="text.trim() !== ''" x-cloak @click="save()" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
            </div>
        </div>
    @endif

    <div class="space-y-1">
        @forelse ($activities as $activity)
            @php
                $category = $activity->type->category();
                $isNote = $activity->isNote();
                $mayChange = $canEdit && $isNote && ($isAdmin || $activity->user_id === $userId);
                $created = $activity->created_at->local();
                $rowUrl = route('projekte.vorgaenge.update', [$project, $activity]);
            @endphp
            <div
                x-show="cats.includes('{{ $category->value }}')"
                x-data="{ editing: false, text: {{ \Illuminate\Support\Js::from($activity->message) }}, date: {{ \Illuminate\Support\Js::from(($activity->occurred_on ?? $created)->format('Y-m-d')) }}, highlight: {{ $activity->is_highlighted ? 'true' : 'false' }} }"
                class="rounded px-1 py-0.5 {{ $activity->is_highlighted ? 'bg-yellow-100' : '' }}"
            >
                <div x-show="! editing" class="flex items-start gap-1.5">
                    <span class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full {{ $category->dotClass() }}" title="{{ $category->label() }}"></span>
                    <div class="min-w-0 flex-1">
                        @if ($isNote)
                            <span class="rounded bg-orange-100 px-1 text-gray-700" title="{{ __('Erfasst am :date von :name', ['date' => $created->format('d.m.Y H:i'), 'name' => $activity->user?->name ?? '–']) }}">{{ $activity->occurred_on?->format('d.m.Y') ?? $created->format('d.m.Y') }}</span>
                        @else
                            <span class="text-gray-400">{{ $created->format('d.m.Y H:i') }}</span>
                        @endif
                        <span class="whitespace-pre-line">{{ $activity->message }}</span>
                        @if ($activity->user)
                            <span class="text-gray-400">({{ $activity->user->name }})</span>
                        @endif
                        @if ($activity->edited_at)
                            <span class="text-gray-400" title="{{ __('Geändert am :date von :name', ['date' => $activity->edited_at->local()->format('d.m.Y H:i'), 'name' => $activity->editor?->name ?? '–']) }}">· {{ __('geändert') }}</span>
                        @endif
                    </div>
                    @if ($canEdit)
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" @click="call('POST', {{ \Illuminate\Support\Js::from(route('projekte.vorgaenge.highlight', [$project, $activity])) }}, { highlighted: {{ $activity->is_highlighted ? 'false' : 'true' }} })" title="{{ $activity->is_highlighted ? __('Hervorhebung entfernen') : __('Hervorheben') }}" aria-label="{{ $activity->is_highlighted ? __('Hervorhebung entfernen') : __('Hervorheben') }}" class="rounded p-0.5 {{ $activity->is_highlighted ? 'text-yellow-600' : 'text-gray-400 hover:text-yellow-600' }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
                            </button>
                            @if ($mayChange)
                                <button type="button" @click="editing = true; setTimeout(() => $refs.editText.focus(), 30)" title="{{ __('Ändern') }}" aria-label="{{ __('Ändern') }}" class="rounded p-0.5 text-gray-400 hover:text-indigo-600">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                </button>
                                <button
                                    type="button"
                                    @click="if (await window.confirmDialog({ signal: 'achtung', title: {{ \Illuminate\Support\Js::from(__('Vorgang löschen?')) }}, message: {{ \Illuminate\Support\Js::from(__('Dieser Vorgang wird gelöscht.')) }}, consequence: {{ \Illuminate\Support\Js::from(__('Das lässt sich nicht rückgängig machen.')) }}, confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }}, cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }} })) call('DELETE', {{ \Illuminate\Support\Js::from($rowUrl) }})"
                                    title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}" class="rounded p-0.5 text-gray-400 hover:text-red-600"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($mayChange)
                    <div x-show="editing" x-cloak class="space-y-2 py-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-1.5">{{ __('Datum') }}
                                <input type="date" x-model="date" class="rounded-md border-gray-300 py-0.5 text-xs">
                            </label>
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" x-model="highlight" class="rounded border-gray-300">
                                {{ __('Hervorheben') }}
                            </label>
                        </div>
                        <textarea x-ref="editText" x-model="text" rows="3" maxlength="5000" class="w-full rounded-md border-gray-300 text-xs"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editing = false; text = {{ \Illuminate\Support\Js::from($activity->message) }}; date = {{ \Illuminate\Support\Js::from(($activity->occurred_on ?? $created)->format('Y-m-d')) }}; highlight = {{ $activity->is_highlighted ? 'true' : 'false' }}" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                            <button type="button" x-show="text.trim() !== ''" @click="call('PUT', {{ \Illuminate\Support\Js::from($rowUrl) }}, { message: text, occurred_on: date, is_highlighted: highlight })" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-gray-400">&ndash; {{ __('Keine Vorgänge') }} &ndash;</div>
        @endforelse
    </div>
</div>
