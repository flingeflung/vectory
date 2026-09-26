<p>{{ $triggeredBy ? $triggeredBy->fullName().' '.__('hat Ihnen in Vectory einen Workflow-Schritt zugewiesen:') : __('Ihnen wurde in Vectory ein Workflow-Schritt zugewiesen:') }}</p>

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
