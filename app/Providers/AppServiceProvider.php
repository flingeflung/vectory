<?php

namespace App\Providers;

use App\Mail\Transport\FileLogTransport;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

        // Rechtekonzept: Nur Super-Admin darf immer alles. Jede andere Rolle
        // (auch Admin) läuft über Person::hasPermission() - deren Rechte
        // kommen ausschließlich aus dem einen PermissionTemplate ("Rechte-
        // Set"), das ihr zugewiesen ist (siehe Person-Modell). Jede Ability,
        // die im Rechte-Katalog als Recht existiert, wird so geprüft - für
        // ein neues Recht muss nur eine Katalog-Zeile ergänzt werden, kein
        // neues Gate::define() hier.
        Gate::before(function (User $user, string $ability) {
            if ($user->role === 'super_admin') {
                return true;
            }

            if (! Permission::query()->where('key', $ability)->exists()) {
                return null;
            }

            return $user->person?->hasPermission($ability) ?? false;
        });

        Gate::define('access-admin', fn (User $user) => in_array($user->role, ['admin', 'super_admin'], true));
    }
}
