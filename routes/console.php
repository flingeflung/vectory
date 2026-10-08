<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Erinnerungsmails (Mail-Timer) täglich früh; auf dem Server muss der Laravel-Zeitplan laufen (php artisan schedule:run jede Minute)
Illuminate\Support\Facades\Schedule::command('mail-timers:send')->dailyAt('06:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
