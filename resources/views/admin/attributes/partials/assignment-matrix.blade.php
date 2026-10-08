{{--
    Raster "Projektart x Attribut" (Zuordnung/Geltung nach Projektart). Gemeinsam für den Bereich
    Typspezifisch (Zuordnung) und Stammdaten/Ablaufdaten (Geltung, mit Kopf-Schalter "alle").

    Ralf, 2026-09-20: die Tabelle kann sehr groß werden - beim Scrollen bleiben Kopfzeile und
    Projektart-Spalte stehen. Und wie in Vietto (adm_projattr.php, markrow/markcol): ein Klick auf
    eine Spaltenüberschrift oder eine Projektart markiert die ganze Spalte/Zeile blau, damit man
    sie beim Scrollen im Blick behält (zweiter Klick hebt die Markierung auf).

    Erwartet: $matrixAttributes, $title, $description, $withAllSwitch, $categories, $assignments.
--}}
@php $matrixId = 'attr-matrix-'.$matrixAttributes->first()->section; @endphp
{{-- Seit 2026-10-08 (Ralf): das Raster speichert gemeinsam über "Speichern" statt jeden Klick sofort. --}}
<div class="rounded-lg border border-gray-200 bg-white p-4" x-data="{ dirty: false }">
    <div class="mb-2 flex items-start justify-between gap-3">
        <div>
            <div class="text-xs font-semibold text-gray-500">{{ $title }}</div>
            <p class="mt-0.5 text-xs text-gray-400">{{ $description }}</p>
        </div>
        <button type="submit" form="{{ $matrixId }}" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Speichern') }}
        </button>
    </div>
    <form
        id="{{ $matrixId }}"
        method="POST"
        action="{{ route('admin.projektattribute.matrix.save') }}"
        x-data="{
            // Ist alle gewählt, sind die Zellen der Spalte gesperrt und gelten als angehakt
            syncAll(event) {
                const box = event.target;
                if (box.dataset.matrixAll === undefined) return;
                this.$el.querySelectorAll('[data-matrix-cell=\'' + box.dataset.matrixAll + '\']').forEach((cell) => {
                    cell.disabled = box.checked;
                    if (box.checked) cell.checked = true;
                    cell.classList.toggle('opacity-40', box.checked);
                });
            },
        }"
        @change="syncAll($event); dirty = window.formIsDirty($el, window.__attributesDirtyForms)"
        @submit="dirty = false; window.__attributesDirtyForms.delete($el)"
    >
    @csrf
    <input type="hidden" name="section" value="{{ $matrixAttributes->first()->section }}">
    @foreach ($matrixAttributes as $attribute)
        <input type="hidden" name="attributes[]" value="{{ $attribute->id }}">
    @endforeach
    {{-- Höhe passt sich dem verbleibenden Platz im Fenster an (Ralf, 2026-09-20: die Kopfzeile darf beim
         Scrollen nie wegrutschen - dafür darf die Tabelle nicht höher sein als der sichtbare Bereich, sonst
         scrollt zusätzlich die Seite und die Kopfzeile verschwindet oben). Neu berechnet beim Einblenden und Verändern der Fenstergröße. --}}
    <div
        x-data="{
            markRow: null,
            markCol: null,
            fit() {
                const top = this.$el.getBoundingClientRect().top;
                if (top > 0) { this.$el.style.maxHeight = Math.max(240, window.innerHeight - top - 28) + 'px'; }
            },
            init() {
                this.fit();
                // Mehrfach absichern: beim Start ist der Bereich oft noch ausgeblendet (Ansicht/Reiter), dann
                // passt fit() beim Einblenden, bei Größenänderung und spätestens beim ersten Überfahren nach.
                setTimeout(() => this.fit(), 150);
                new IntersectionObserver(() => this.fit()).observe(this.$el);
                window.addEventListener('resize', () => this.fit());
                window.addEventListener('matrix-shown', () => this.fit());
            },
        }"
        @mouseenter="fit()"
        class="max-h-[calc(100vh-28rem)] min-h-[12rem] overflow-auto rounded-md border border-gray-100"
    >
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
                                <label class="mt-0.5 inline-flex items-center gap-1 font-normal text-gray-500">
                                    <input type="checkbox" name="all[]" value="{{ $attribute->id }}" data-matrix-all="{{ $attribute->id }}" @checked($attribute->applies_to_all_types) class="rounded border-gray-300">
                                    {{ __('alle') }}
                                </label>
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
                                        name="assign[{{ $attribute->id }}][]"
                                        value="{{ $sub->id }}"
                                        data-matrix-cell="{{ $attribute->id }}"
                                        title="{{ $sub->name }} – {{ $attribute->label }}"
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    </form>
</div>
