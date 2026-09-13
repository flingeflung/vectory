{{--
    Modell/System (Ralf, 2026-09-13): System-Feld, aber mit pro Kunde
    änderbarer Bezeichnung (siehe Attribute::LABEL_EDITABLE_SYSTEM_FIELDS) -
    der Wert liegt wie bei einem normalen Zusatzfeld im attributes-JSON,
    deshalb Wiederverwendung von attribute-field.blade.php statt eigener
    Eingabelogik.
--}}
@include('projekte.partials.attribute-field', ['attribute' => $field])
