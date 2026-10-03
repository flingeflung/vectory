{{--
    Merkmale der Schablone (Format, Produktkomplexität, Entwicklungsstand usw.) - Ralf, 2026-10-03:
    auf Anleitungen ausgerichtet, für Verwaltungs- und andere Projekte unpassend. Deshalb pro
    Schablone optional und eingeklappt im Hintergrund. Abgewählte Werte bleiben gespeichert, werden
    aber nirgends mehr angezeigt oder abgefragt.
--}}
<div class="mt-4 border-t border-gray-100 pt-3" x-data="{ on: {{ \Illuminate\Support\Js::from((bool) ($template->use_characteristics ?? false)) }} }">
    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
        <input type="checkbox" name="use_characteristics" value="1" x-model="on" class="rounded border-gray-300">
        {{ __('Merkmale dieser Schablone erfassen') }}
    </label>
    <p x-show="!on" class="mt-0.5 text-xs text-gray-400">{{ __('Optional: Format, Komplexität, Entwicklungsstand u. Ä. beschreiben ein typisches Projekt. Für Verwaltungs- und andere Projekte meist nicht nötig.') }}</p>

    <div x-show="on" x-cloak class="mt-3">
        <div class="mb-3">
            <label class="block text-xs text-gray-500">{{ __('Format') }}</label>
            <select name="format" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
                <option value="">{{ __('– nicht festgelegt –') }}</option>
                @foreach (\App\Models\ProjectTemplate::formatOptions() as $value => $label)
                    <option value="{{ $value }}" @selected(($template->format ?? null) == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {{--
            Farbige Umrandung/Hintergrund je gewähltem Wert (grün = günstig für
            die Dauer, rot = ungünstig) - Viettos gakat.php zeigte das rein
            lesend über die Badge-Farben (get_anteilXX()), hier zusätzlich live
            am bearbeitbaren Pulldown selbst, damit man ein Schablonen-Profil
            auch im (hier editierbaren) Zustand auf einen Blick erfassen kann.
        --}}
        @foreach (\App\Models\ProjectTemplate::characteristicFields() as $field => $meta)
            <div
                x-data="{
                    value: {{ \Illuminate\Support\Js::from((string) ($template->$field ?? '')) }},
                    colors: {{ \Illuminate\Support\Js::from(collect($meta['options'])->mapWithKeys(fn ($option, $value) => [(string) $value => $option['color']])) }},
                }"
            >
                {{--
                    Ralf, 2026-09-28: "die Dropdowns hüpfen bei 1- oder
                    2-zeiligen Captions" - ohne feste Mindesthöhe richtet sich
                    die Select-Position nach der Zeilenzahl des EIGENEN Labels,
                    nicht nach der längsten Caption in derselben Grid-Zeile (die
                    wechselt je nach Spaltenzahl/Breakpoint ohnehin). min-h-12
                    (3 Zeilen bei text-xs) deckt die längste Caption ("Anteil
                    wiederverwendbarer Inhalt", bricht in der schmalsten Spalte
                    auf 3 Zeilen) ab und macht die Starthöhe unabhängig von der
                    tatsächlichen Zeilenzahl der übrigen Captions.
                --}}
                <label class="block min-h-12 text-xs text-gray-500">{{ $meta['label'] }}</label>
                <select
                    name="{{ $field }}"
                    :required="on"
                    x-model="value"
                    :class="{
                        'border-green-300 bg-green-50': colors[value] === 'green',
                        'border-lime-300 bg-lime-50': colors[value] === 'lime',
                        'border-amber-300 bg-amber-50': colors[value] === 'amber',
                        'border-red-300 bg-red-50': colors[value] === 'red',
                        'border-gray-300 bg-gray-50': colors[value] === 'gray',
                        'border-gray-300': !colors[value],
                    }"
                    class="mt-0.5 w-full rounded-md py-1 text-sm"
                >
                    <option value="" disabled>{{ __('– auswählen –') }}</option>
                    @foreach ($meta['options'] as $value => $option)
                        <option value="{{ $value }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>
    </div>
</div>
