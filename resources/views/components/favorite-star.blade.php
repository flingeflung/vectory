@props(['project', 'isFavorite', 'size' => 'h-4 w-4'])

<button
    type="button"
    x-data="{
        isFavorite: {{ $isFavorite ? 'true' : 'false' }},
        async toggle() {
            this.isFavorite = ! this.isFavorite;
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.favorite', $project)) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
            });
            if (! response.ok) {
                this.isFavorite = ! this.isFavorite;

                return;
            }

            // Betrifft ggf. den Favorit-Filter der Liste (Projekt könnte sich
            // dafür gerade disqualifiziert haben) sowie die Favoriten-Kachel
            // auf der Startseite - beide global verfügbaren Funktionen sind
            // No-ops, wenn die jeweilige Seite gerade nicht aktiv ist.
            window.refreshProjekteListInBackground?.();
            window.refreshDashboardTiles?.();
        },
    }"
    @click.stop="toggle()"
    {{ $attributes->merge(['class' => 'text-amber-400 hover:text-amber-500']) }}
    :title="isFavorite ? {{ \Illuminate\Support\Js::from(__('Favorit entfernen')) }} : {{ \Illuminate\Support\Js::from(__('Als Favorit speichern')) }}"
>
    <svg class="{{ $size }}" :fill="isFavorite ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5" viewBox="0 0 20 20">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.363 1.118l1.286 3.958c.3.921-.755 1.688-1.538 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.783.57-1.838-.197-1.538-1.118l1.285-3.958a1 1 0 00-.363-1.118L2.075 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.285-3.958z" />
    </svg>
</button>
