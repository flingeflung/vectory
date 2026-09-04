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

        $body = trim($email->getTextBody() ?? strip_tags((string) $email->getHtmlBody()));
        $subject = $email->getSubject();
        $timestamp = now()->format('d.m.Y H:i:s');

        // Jeder Empfänger (An/Kopie/Blindkopie) bekommt einen eigenen,
        // vollständigen Block - nicht eine gemeinsame Zeile pro Mail. Das
        // entspricht eher dem, was beim echten Versand tatsächlich als
        // einzelne Zustellung rausgeht, und macht z.B. "Kopie an mich" beim
        // Testen sofort als eigenen Eintrag sichtbar statt als Kopfzeile in
        // einem fremden Eintrag.
        $recipients = [
            ...collect($email->getTo())->map(fn ($a) => ['label' => 'An', 'address' => $a->toString()]),
            ...collect($email->getCc())->map(fn ($a) => ['label' => 'Kopie', 'address' => $a->toString()]),
            ...collect($email->getBcc())->map(fn ($a) => ['label' => 'Blindkopie', 'address' => $a->toString()]),
        ];

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $separator = "\n".str_repeat('=', 20)."\n\n";

        // In umgekehrter Reihenfolge voranstellen, damit nach allen Inserts
        // "An" oben steht, dann "Kopie", dann "Blindkopie" - wie in der
        // Bestätigungsmaske.
        foreach (array_reverse($recipients) as $recipient) {
            $entry = "Zeitstempel: {$timestamp}\n";
            $entry .= "{$recipient['label']}: {$recipient['address']}\n";
            $entry .= "Betreff: {$subject}\n\n";
            $entry .= $body."\n";

            $existing = file_exists($this->path) ? file_get_contents($this->path) : '';
            file_put_contents($this->path, $entry.($existing !== '' ? $separator.$existing : ''));
        }
    }

    public function __toString(): string
    {
        return 'filelog';
    }
}
