<!doctype html>
<html lang="de">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
        {{-- Logo als eingebettetes PNG (SVG zeigen viele Mailprogramme nicht an); erscheint, sobald public/images/vectory_logo_mail.png vorhanden ist --}}
        @if (is_file(public_path('images/vectory_logo_mail.png')))
            <p style="margin:0 0 20px;text-align:center;"><img src="{{ $message->embed(public_path('images/vectory_logo_mail.png')) }}" alt="Vectory" width="150" style="max-width:150px;height:auto;"></p>
        @endif
        <p style="margin:0 0 16px;font-size:15px;">{{ __('Guten Tag :name,', ['name' => $firstName]) }}</p>
        <p style="margin:0 0 16px;font-size:15px;">{{ __('für Sie wurde ein Zugang zu Vectory vorbereitet. Mit dem folgenden Link wählen Sie Benutzernamen und Passwort und aktivieren Ihr Konto.') }}</p>
        <p style="margin:24px 0;text-align:center;">
            <a href="{{ $url }}" style="display:inline-block;background:#1f3a5f;color:#ffffff;text-decoration:none;font-size:15px;padding:10px 20px;border-radius:6px;">{{ __('Konto aktivieren') }}</a>
        </p>
        <p style="margin:0 0 8px;font-size:13px;color:#4b5563;">{{ __('Der Link ist :hours Stunden gültig und lässt sich nur einmal verwenden.', ['hours' => $validHours]) }}</p>
        <p style="margin:0 0 8px;font-size:13px;color:#4b5563;">{{ __('Wenn die Schaltfläche nicht funktioniert, kopieren Sie diese Adresse in die Adresszeile Ihres Browsers:') }}</p>
        <p style="margin:0 0 16px;font-size:12px;word-break:break-all;color:#4b5563;">{{ $url }}</p>
        <p style="margin:0;font-size:12px;color:#6b7280;">{{ __('Falls Sie diesen Zugang nicht erwartet haben, können Sie diese E-Mail ignorieren.') }}</p>
    </div>
</body>
</html>
