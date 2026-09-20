{{--
    Raster "Projektart x Attribut" (Zuordnung/Geltung nach Projektart). Gemeinsam für den Bereich
    Typspezifisch (Zuordnung) und Stammdaten/Ablaufdaten (Geltung, mit Kopf-Schalter "alle").

    Ralf, 2026-09-20: die Tabelle kann sehr groß werden - beim Scrollen bleiben Kopfzeile und
    Projektart-Spalte stehen. Und wie in Vietto (adm_projattr.php, markrow/markcol): ein Klick auf
    eine Spaltenüberschrift oder eine Projektart markiert die ganze Spalte/Zeile blau, damit man
    sie beim Scrollen im Blick behält (zweiter Klick hebt die Markierung auf).

    Erwartet: $matrixAttributes, $title, $description, $withAllSwitch, $categories, $assignments.
--}}
<div class="rounded-lg border border-gray-200 bg-white p-4">
    <div class="mb-2 text-xs font-semibold text-gray-500">{{ $title }}</div>
    <p class="mb-2 text-xs text-gray-400">{{ $description }}</p>
    <div x-data="{ markRow: null, markCol: null }" class="max-h-[calc(100vh-17rem)] min-h-[12rem] overflow-auto rounded-md border border-gray-100">
        <table class="w-full border-separate border-spacing-0 text-left text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 top-0 z-30 border-b border-gray-200 bg-white px-2 pb-2 pt-1 pr-3">{{ __('Projektart') }}</th>
                    @foreach ($matrixAttributes as $attribute)
                        <th
                            class="sticky top-0 z-20 whitespace-nowrap border-b border-gray-200 px-2 pb-2 pt-1 text-center font-medium text-gray-600"
                            :class="markCol === {{ $attribute->id }} ? 'bg-blue-200' : 'bg-white'"
                        >
                            <div
                                @click="markCol = markCol === {{ $attribute->id }} ? null : {{ $attribute->id }}"
                                class="cursor-pointer rounded px-1 hover:bg-blue-100"
                                title="{{ __('Klicken markiert die ganze Spalte') }}"
                            >{{ $attribute->label }}</div>
                            @if ($withAllSwitch)
                                <form method="POST" action="{{ route('admin.projektattribute.alle-projektarten.toggle', $attribute) }}">
                                    @csrf
                                    <label class="mt-0.5 inline-flex items-center gap-1 font-normal text-gray-500">
                                        <input type="checkbox" @checked($attribute->applies_to_all_types) onchange="this.form.submit()" class="rounded border-gray-300">
                                        {{ __('alle') }}
                                    </label>
                                </form>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    @foreach ($category->subs as $sub)
                        <tr>
                            <td
                                class="sticky left-0 z-10 whitespace-nowrap border-t border-gray-100 py-1.5 pr-3 pl-2"
                                :class="markRow === {{ $sub->id }} ? 'bg-blue-200' : 'bg-white'"
                            >
                                <div
                                    @click="markRow = markRow === {{ $sub->id }} ? null : {{ $sub->id }}"
                                    class="cursor-pointer rounded px-1 text-gray-700 hover:bg-blue-100"
                                    title="{{ __('Klicken markiert die ganze Zeile') }}"
                                >{{ $category->name }}: {{ $sub->name }}</div>
                            </td>
                            @foreach ($matrixAttributes as $attribute)
                                <td
                                    class="border-t border-gray-100 px-2 py-1.5 text-center"
                                    :class="(markRow === {{ $sub->id }} || markCol === {{ $attribute->id }}) ? 'bg-blue-200' : ''"
                                >
                                    <input
                                        type="checkbox"
                                        @if ($withAllSwitch)
                                            @disabled($attribute->applies_to_all_types)
                                            @checked($attribute->applies_to_all_types || in_array($sub->id, $assignments->get($attribute->id, []), true))
                                            @class(['opacity-40' => $attribute->applies_to_all_types])
                                        @else
                                            @checked(in_array($sub->id, $assignments->get($attribute->id, []), true))
                                        @endif
                                        title="{{ $sub->name }} – {{ $attribute->label }}"
                                        @click="
                                            fetch({{ \Illuminate\Support\Js::from(route('admin.projektattribute.projektart.toggle', $attribute)) }}, {
                                                method: 'POST',
                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                                                body: 'project_type_sub_id={{ $sub->id }}',
                                            });
                                        "
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
