{{--
    Ralf, 2026-09-11: "teilt sich eine Zeile mit dem nächsten Feld" -
    koppelt zwei Felder robust aneinander (unabhängig von Zufalls-Parität),
    siehe AttributeController::toggleSameRowAsNext() und detail.blade.php.
    Sofort-Toggle wie beim Projektart-Raster, kein Speichern-Button nötig.
    Erwartet $attribute aus dem einbindenden Kontext.
--}}
<span x-data="{ paired: {{ $attribute->same_row_as_next ? 'true' : 'false' }} }">
    <button
        type="button"
        class="shrink-0"
        :class="paired ? 'text-indigo-600' : 'text-gray-300 hover:text-gray-500'"
        :title="paired ? {{ \Illuminate\Support\Js::from(__('Teilt sich eine Zeile mit dem nächsten Feld - klicken zum Trennen.')) }} : {{ \Illuminate\Support\Js::from(__('Teilt sich eine Zeile mit dem nächsten Feld? - klicken zum Verbinden.')) }}"
        @click="
            paired = !paired;
            fetch({{ \Illuminate\Support\Js::from(route('admin.projektattribute.zeile-teilen.toggle', $attribute)) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
            });
        "
    >🔗</button>
</span>
