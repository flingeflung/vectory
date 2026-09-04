<?php

namespace App\Providers;

use App\Mail\Transport\FileLogTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('filelog', fn (array $config) => new FileLogTransport($config['path'] ?? storage_path('logs/mails.log')));
    }
}
