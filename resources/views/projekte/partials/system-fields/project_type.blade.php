{{--
    Projektkategorie/-art (Ralf, 2026-09-10: "fehlt noch bei den
    Stammdaten") - schreibt project_type_sub_id, project_type_main_id wird
    serverseitig daraus abgeleitet (siehe ProjectController::update()).
--}}
<div>
    <label class="block text-xs text-gray-500">{{ __('Projektkategorie/-art') }}</label>
    <select name="project_type_sub_id" class="mt-0.5 w-full max-w-sm rounded border-gray-300 py-1 text-sm">
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
</div>
