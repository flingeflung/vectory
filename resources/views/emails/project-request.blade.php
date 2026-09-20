<p>{{ $requestedBy->fullName() }} {{ __('hat in Vectory eine neue Projektanfrage gestellt:') }}</p>

<p>
    {{ __('Bezeichnung') }}: <strong>{{ $title }}</strong><br>
    @if ($modelOrSystem)
        {{ __('Info zum betroffenen Produkt, z.B. Modellnummer, Produktname o.Ä.') }}: <strong>{{ $modelOrSystem }}</strong><br>
    @endif
    @if ($dueDate)
        {{ __('Geplanter Fertigstellungstermin') }}: <strong>{{ $dueDate }}</strong><br>
    @endif
</p>

@if ($remarks)
    <p>
        {{ __('Bemerkungen') }}:<br>
        {!! nl2br(e($remarks)) !!}
    </p>
@endif
