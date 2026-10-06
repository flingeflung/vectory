{{-- Streifen oben, solange der Super-Admin gemorpht ist (Ralf, 2026-10-06): zeigt die Rolle und beendet das Morphen. --}}
@if (\App\Support\Morph::active(auth()->user()))
    <div class="flex shrink-0 flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-1.5 text-sm font-medium text-white">
        <span>{{ __('Gemorpht als') }}: {{ \App\Support\Morph::label() }}</span>
        <button type="button" onclick="window.endMorph()" class="rounded-md border border-white/70 bg-white/15 px-2.5 py-0.5 text-xs font-semibold hover:bg-white/30">{{ __('Morphen beenden') }}</button>
    </div>
@endif
