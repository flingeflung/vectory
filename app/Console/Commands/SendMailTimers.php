<?php

namespace App\Console\Commands;

use App\Services\MailTimerService;
use Illuminate\Console\Command;

/** Verschickt die heute fälligen Erinnerungsmails (täglich per Zeitplan, siehe routes/console.php). */
class SendMailTimers extends Command
{
    protected $signature = 'mail-timers:send';

    protected $description = 'Verschickt fällige Mail-Timer (Erinnerungsmails zu Projekten)';

    public function handle(MailTimerService $service): int
    {
        $result = $service->sendDue();
        $this->info(sprintf('gesendet %d, übersprungen %d, wartend %d, fehlgeschlagen %d', $result['sent'], $result['skipped'], $result['waiting'], $result['failed']));

        return self::SUCCESS;
    }
}
