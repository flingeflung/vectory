<?php

namespace App\Providers;

use App\Mail\Transport\FileLogTransport;
use App\Models\Permission;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
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

        // Ralf-Bug-Report, 2026-09-13: Zeitstempel wurden roh in UTC
        // angezeigt (Speicherung bleibt bewusst UTC), z.B. "Angelegt am"
        // im Projekt-Footer 2 Std. hinter Ralfs tatsächlicher Uhrzeit.
        // Zentraler Umrechnungspunkt statt Einzelfixes je Anzeigestelle -
        // ->format(...) an Anzeigestellen wird zu ->local()->format(...).
        // Feste Zeitzone für jetzt (Option 2, echte pro-Nutzer/-Mandant-
        // Zeitzone, ist auf dem Backlog, siehe Internationalisierung).
        Carbon::macro('local', function () {
            /** @var CarbonInterface $this */
            return $this->copy()->setTimezone(config('app.display_timezone'));
        });

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

        // Installationsweite Einstellungen (Superadmin-Reiter) - bewusst
        // strenger als access-admin, normale Admins sehen den Reiter nicht.
        Gate::define('access-superadmin', fn (User $user) => $user->role === 'super_admin');

        // {person}-Routenbindung ohne den automatischen Tenant-Scope: eine
        // per Kundenzugriff freigegebene Person (siehe person_tenant) gehört
        // einem ANDEREN Mandanten als dem aktiven Kunden - mit dem Scope
        // würde die Bindung sie schon vor jedem Controller-Code mit 404
        // aussortieren. Die eigentliche Zugriffsprüfung bleibt explizit in
        // den Controllern (PersonController::personVisibleInCurrentTenant()
        // ist bewusst lockerer als PermissionController/FunctionGroupController,
        // die weiterhin strikt auf den aktiven Mandanten prüfen).
        Route::bind('person', fn ($value) => Person::withoutGlobalScope('tenant')->findOrFail($value));
    }
}
