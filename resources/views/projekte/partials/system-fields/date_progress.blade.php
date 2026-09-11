{{--
    Ralf, 2026-09-11: zeitbasierter Fortschrittsbalken (Start-/Enddatum vs.
    heute, Vietto-Vorbild "Zeitbalken") - rein informativ, nicht editierbar,
    unabhängig vom manuell/berechnet gepflegten "Projektfortschritt"
    darunter. Ohne Start UND Ende (oder nur eins von beiden gesetzt) lässt
    sich kein sinnvoller Prozentwert bilden -> Platzhalter statt Balken.
    Ist das Enddatum überschritten, wird der Balken statt blau rot
    eingefärbt ("überfällig"), rein informativ ohne Konsequenz.
--}}
@php
    $start = $project->start_date;
    $end = $project->end_date;
    $dateProgress = null;
    $overdue = false;

    if ($start && $end) {
        $today = now()->startOfDay();
        $totalDays = $start->diffInDays($end);
        $overdue = $today->gt($end);

        if ($totalDays <= 0) {
            $dateProgress = $today->gte($start) ? 100 : 0;
        } else {
            $elapsedDays = $start->diffInDays($today, false);
            $dateProgress = (int) round(max(0, min(100, ($elapsedDays / $totalDays) * 100)));
        }
    }
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Datumsfortschritt') }}</label>
    @if ($dateProgress !== null)
        <div class="mt-0.5">
            <div class="mb-0.5 text-xs text-gray-500">
                {{ $dateProgress }} %
                @if ($overdue)
                    <span class="text-red-600">({{ __('überfällig') }})</span>
                @endif
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full {{ $overdue ? 'bg-red-500' : 'bg-blue-500' }}" style="width: {{ $dateProgress }}%"></div>
            </div>
        </div>
    @else
        <div class="mt-0.5 text-gray-400" title="{{ __('Benötigt sowohl Start- als auch Enddatum.') }}">–</div>
    @endif
</div>
