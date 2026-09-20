{{--
    Projektkategorie/-art (Ralf, 2026-09-10: "fehlt noch bei den
    Stammdaten") - schreibt project_type_sub_id, project_type_main_id wird
    serverseitig daraus abgeleitet (siehe ProjectController::update()).

    Ralf, 2026-09-20: das Symbol der gewählten Art steht rechts neben dem
    Pulldown (schmaleres Dropdown dafür) und wechselt beim Umschalten mit.
    Bewusst OHNE x-model am select: Alpine würde den Wert beim Start setzen
    und damit den Snapshot der Ungespeicherte-Änderungen-Prüfung stören - der
    Anfangswert kommt deshalb serverseitig (@selected) und Alpine liest nur mit.
--}}
@php
    $typeSymbols = $projectTypeCategories
        ->flatMap(fn ($category) => $category->subs)
        ->mapWithKeys(fn ($sub) => [$sub->id => $sub->symbol ? asset('images/project-type-icons/'.$sub->symbol) : null]);
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Projektkategorie/-art') }}</label>
    <div
        class="mt-0.5 flex items-center gap-2"
        x-data="{ symbols: {{ \Illuminate\Support\Js::from($typeSymbols) }}, sub: {{ \Illuminate\Support\Js::from((string) old('project_type_sub_id', $project->project_type_sub_id)) }} }"
    >
        <select name="project_type_sub_id" @change="sub = $event.target.value" class="w-full max-w-[15rem] rounded border-gray-300 py-1 text-sm">
            <option value="">{{ __('– nicht zugewiesen –') }}</option>
            @foreach ($projectTypeCategories as $category)
                @if ($category->subs->isNotEmpty())
                    <optgroup label="{{ $category->name }}">
                        @foreach ($category->subs as $sub)
                            <option value="{{ $sub->id }}" @selected(old('project_type_sub_id', $project->project_type_sub_id) == $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        </select>
        <template x-if="symbols[sub]">
            <img :src="symbols[sub]" alt="" class="h-6 w-auto shrink-0">
        </template>
    </div>
</div>
