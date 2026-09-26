<p>{{ $triggeredBy ? $triggeredBy->fullName().' '.__('bittet Sie in Vectory um Ihre Freigabe:') : __('Sie werden in Vectory um Ihre Freigabe gebeten:') }}</p>

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

@if ($attachedFileName)
    <p>{{ __('Die zu prüfende Datei finden Sie im Anhang:') }} <strong>{{ $attachedFileName }}</strong></p>
@elseif ($sourcePathText)
    <p>{{ __('Die zu prüfenden Daten liegen im Arbeitsverzeichnis:') }}<br><code>{{ $sourcePathText }}</code></p>
@endif

<p>{{ __('Bitte wählen Sie:') }}</p>

<p>
    <a href="{{ $freigabeUrl }}" style="display:inline-block;padding:8px 16px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:4px;">{{ __('Freigabe erteilen') }}</a>
    &nbsp;
    <a href="{{ $korrekturUrl }}" style="display:inline-block;padding:8px 16px;background:#d97706;color:#ffffff;text-decoration:none;border-radius:4px;">{{ __('Korrekturen einarbeiten') }}</a>
</p>

<p style="font-size:12px;color:#6b7280;">
    @if ($afterStepTitle)
        {{ __('Mit der Freigabe wird automatisch der nächste Schritt „:title“ ausgelöst.', ['title' => $afterStepTitle]) }}<br>
    @endif
    @if ($previousStepTitle)
        {{ __('Bei „Korrekturen einarbeiten“ laden Sie Ihre korrigierte PDF hoch; danach wird der Schritt „:title“ erneut ausgelöst.', ['title' => $previousStepTitle]) }}<br>
    @endif
    {{ __('Die Links sind :days Tage gültig, funktionieren nur einmal und erfordern keine Anmeldung. Bitte leiten Sie diese Mail nicht weiter.', ['days' => $validityDays]) }}
</p>
