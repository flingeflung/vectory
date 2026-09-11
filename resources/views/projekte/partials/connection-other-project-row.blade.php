{{-- Eine Zeile "Andere Projekte" - eigene Partial, damit sowohl der
     Erstaufbau (connection-add-body.blade.php) als auch das Nachladen
     beim Scrollen (connection-other-project-rows.blade.php,
     ProjectConnectionController::moreOtherProjects()) dieselbe Zeile
     rendern. Braucht $project (Kontext-Projekt) und $p (die Zeile) aus
     der Alpine-Umgebung des einbindenden Modals (loading/addingId/...). --}}
<div class="border-b border-gray-100 py-1 last:border-0" id="other-row-{{ $p->id }}">
    <div class="flex items-start gap-1.5 text-xs text-gray-700">
        <input type="checkbox" :disabled="loading" @click.prevent="addingId === {{ $p->id }} ? (addingId = null) : startAdd({{ $p->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
        <span>{{ $p->source_pn }} &ndash; {{ $p->title }}</span>
    </div>
    {{-- Ralf, 2026-09-11 (S1, Vietto-Vorbild): "Was ist X aus Sicht von Y?"
         statt abstrakter "Richtung"-Begriffe - macht für den Benutzer direkt
         klar, was einzutragen ist, ohne Fachbegriffe wie "Richtung"/
         "Rückrichtung". --}}
    <div x-show="addingId === {{ $p->id }}" x-cloak class="ml-5 mt-1 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
        <div>
            <label class="block text-[11px] text-gray-500">{{ __('Was ist :other aus Sicht von :this?', ['other' => $p->source_pn, 'this' => $project->source_pn]) }}</label>
            <input type="text" x-model="addLabel" list="connection-label-suggestions" :disabled="loading" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
        </div>
        <div>
            <label class="block text-[11px] text-gray-500">{{ __('Was ist :this aus Sicht von :other?', ['this' => $project->source_pn, 'other' => $p->source_pn]) }}</label>
            <input type="text" x-model="addLabelReverse" list="connection-label-suggestions" :disabled="loading" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" :disabled="loading" @click="addingId = null" class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Abbrechen') }}</button>
            <button type="button" :disabled="loading" @click="confirmAdd({{ $p->id }})" class="rounded bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Verknüpfen') }}</button>
        </div>
    </div>
</div>
