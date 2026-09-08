<p>{{ $requestedBy->fullName() }} {{ __('hat in Vectory eine neue Projektanfrage gestellt:') }}</p>

<p>
    {{ __('Bezeichnung') }}: <strong>{{ $title }}</strong><br>
    @if ($modelOrSystem)
        {{ __('Modellnummer(n)/Systemname') }}: <strong>{{ $modelOrSystem }}</strong><br>
    @endif
    @if ($dueDate)
        {{ __('Termin') }}: <strong>{{ $dueDate }}</strong><br>
    @endif
</p>

@if ($remarks)
    <p>
        {{ __('Bemerkungen') }}:<br>
        {!! nl2br(e($remarks)) !!}
    </p>
@endif
