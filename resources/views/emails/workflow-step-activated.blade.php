@if ($viaInfoAddress)
    {{-- Ralf, 2026-09-27: Rückfall an die Info-Adresse des Kunden - niemandem persönlich zugewiesen. --}}
    <p>{{ __('Zur Information: Für diesen Workflow-Schritt ist niemand mit E-Mail-Adresse zuständig, deshalb geht diese Mitteilung an die Info-Adresse des Kunden.') }}</p>
    <p>{{ __('Folgender Workflow-Schritt wurde aktiviert:') }}</p>
@else
    <p>{{ $triggeredBy ? $triggeredBy->fullName().' '.__('hat Ihnen in Vectory einen Workflow-Schritt zugewiesen:') : __('Ihnen wurde in Vectory ein Workflow-Schritt zugewiesen:') }}</p>
@endif

<p>
    {{ __('Projekt') }} <strong>{{ $project->source_pn }}</strong> ({{ $project->title }})<br>
    {{ __('Workflow-Schritt') }}: <strong>{{ $step->title }}</strong>
</p>

@if ($step->email_text)
    <p>{!! nl2br(e($step->email_text)) !!}</p>
@endif

@if ($personalMessage)
    <p>
        {{ $triggeredBy ? __('Persönliche Nachricht von :name:', ['name' => $triggeredBy->fullName()]) : __('Persönliche Nachricht:') }}<br>
        <em>{{ $personalMessage }}</em>
    </p>
@endif

<p><a href="{{ route('projekte.show', $project) }}">{{ route('projekte.show', $project) }}</a></p>
