<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Schreibt jede "versendete" Mail in eine gut lesbare Textdatei statt sie
 * wirklich zu verschicken - solange kein echter SMTP-Server konfiguriert
 * ist (lokale Entwicklung/Tests), aber ohne die DB mit Test-Mails
 * zuzumüllen. Umstellung auf echten Versand später = nur MAIL_MAILER in
 * der .env ändern, kein Code-Umbau im Feature-Code nötig (der nutzt
 * überall die normale Mail-Fassade).
 *
 * Neueste Mail steht oben - dafür wird die Datei bei jeder Mail komplett
 * neu geschrieben (Voranstellen geht bei Dateien nicht anders). Bei den
 * im Testbetrieb zu erwartenden Mengen unproblematisch; die Datei wird
 * ohnehin von Zeit zu Zeit manuell geleert.
 */
class FileLogTransport extends AbstractTransport
{
    public function __construct(private readonly string $path)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $to = collect($email->getTo())->map(fn ($address) => $address->toString())->implode(', ');
        $cc = collect($email->getCc())->map(fn ($address) => $address->toString())->implode(', ');
        $body = $email->getTextBody() ?? strip_tags((string) $email->getHtmlBody());

        $entry = 'Zeitstempel: '.now()->format('d.m.Y H:i:s')."\n";
        $entry .= "An: {$to}\n";
        if ($cc !== '') {
            $entry .= "Kopie: {$cc}\n";
        }
        $entry .= 'Betreff: '.$email->getSubject()."\n\n";
        $entry .= trim($body)."\n";

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $existing = file_exists($this->path) ? file_get_contents($this->path) : '';
        $separator = "\n".str_repeat('=', 20)."\n\n";

        file_put_contents($this->path, $entry.($existing !== '' ? $separator.$existing : ''));
    }

    public function __toString(): string
    {
        return 'filelog';
    }
}
