{{--
    Weißer Rahmen mit den Meilensteinen eines Workflow-Schritts, direkt am grünen Kasten des Schritts (Ralf, 2026-10-09): Raute, Name und Datum.
    Erwartet $milestones (Name, Datum, Regel), $position ('above' oder 'below') und $current (Schritt ist der aktuelle, dessen Kasten hat den blauen Rand).
--}}
<div class="bg-white px-3 py-1.5 {{ $current ? 'border-2 border-blue-500' : 'border border-gray-300' }} {{ $position === 'above' ? 'rounded-t-md border-b-0' : 'rounded-b-md border-t-0' }}">
    @foreach ($milestones as $milestone)
        <div class="flex items-center gap-1.5 text-xs text-gray-700" title="{{ $milestone['rule'] }}">
            <span class="inline-block h-2.5 w-2.5 shrink-0 rotate-45 bg-indigo-600"></span>
            <span class="font-medium">{{ $milestone['name'] }}:</span>
            <span>{{ $milestone['date']->format('d.m.Y') }}</span>
        </div>
    @endforeach
</div>
