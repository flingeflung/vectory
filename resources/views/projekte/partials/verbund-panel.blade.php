{{--
    "Projektverbund" (Ralf, 2026-09-14) - Fragment, per fetch() in
    #verbund-panel-body geladen (siehe window.openVerbundPanel() in
    layouts/app.blade.php) und nach jedem Speichern/Auflösen dort erneut
    eingesetzt (Server liefert nach der Aktion einfach den aktuellen Stand
    dieses selben Partials zurück, gleiches Muster wie ProjectGroupController).
--}}
<div
    x-data="{
        selected: {{ \Illuminate\Support\Js::from((string) ($currentHauptprojektId ?? '')) }},
        saving: false,
        async save() {
            if (this.saving || ! this.selected) return;
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('hauptprojekt_id', this.selected);
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projektgruppen.verbund.store', $group)) }}, {
                    method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const body = document.getElementById('verbund-panel-body');
                body.innerHTML = await response.text();
                Alpine.initTree(body);
                if (response.ok) {
                    window.dispatchEvent(new CustomEvent('projekte-refresh'));
                }
            } finally {
                this.saving = false;
            }
        },
        async dissolve() {
            if (! await window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verbund auflösen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Alle Projekte dieser Gruppe werden wieder zu normalen Projekten - die Gruppe selbst bleibt bestehen.')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Auflösen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            })) return;
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projektgruppen.verbund.destroy', $group)) }}, {
                method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            const body = document.getElementById('verbund-panel-body');
            body.innerHTML = await response.text();
            Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('projekte-refresh'));
        },
    }"
    class="space-y-3"
>
    <div class="text-xs text-gray-400">{{ __('Gruppe: :name', ['name' => $group->name]) }}</div>

    @if (! empty($formError))
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $formError }}</div>
    @endif

    @if ($conflicts->isNotEmpty())
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <div class="mb-1 font-medium">{{ __('Speichern nicht möglich - folgende Projekte sind bereits Teil eines anderen Verbunds:') }}</div>
            <ul class="list-outside list-disc space-y-0.5 pl-4">
                @foreach ($conflicts as $conflict)
                    <li>{{ $conflict['project']->source_pn }} – {{ $conflict['project']->title }}: {{ $conflict['reason'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <div class="mb-1 text-xs text-gray-500">{{ __('Hauptprojekt auswählen:') }}</div>
        <div class="max-h-64 space-y-1 overflow-auto rounded-md border border-gray-200 p-2">
            @forelse ($members as $member)
                <label class="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-gray-50">
                    <input type="radio" x-model="selected" value="{{ $member->id }}" class="border-gray-300 text-indigo-600">
                    <span>{{ $member->source_pn }} – {{ $member->title }}</span>
                    @if ($member->verbund_rolle === 1)
                        <span class="text-xs text-indigo-600">{{ __('(aktuell Hauptprojekt)') }}</span>
                    @elseif ($member->verbund_rolle === 2)
                        <span class="text-xs text-gray-400">{{ __('(aktuell Unterprojekt)') }}</span>
                    @endif
                </label>
            @empty
                <div class="text-xs text-gray-400">{{ __('Diese Gruppe hat keine Projekte.') }}</div>
            @endforelse
        </div>
    </div>

    <div class="flex items-center justify-between gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            @click="dissolve()"
            x-show="{{ $hasActiveVerbund ? 'true' : 'false' }}"
            class="rounded border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
        >
            {{ __('Verbund auflösen') }}
        </button>
        <button
            type="button"
            @click="save()"
            :disabled="saving || ! selected || {{ $conflicts->isNotEmpty() ? 'true' : 'false' }}"
            class="ml-auto rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Speichern') }}
        </button>
    </div>
</div>
