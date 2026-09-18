<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function index(Request $request): View
    {
        $multiTenantEnabled = SystemSetting::multiTenantEnabled();

        return view('admin.config.index', [
            'multiTenantEnabled' => $multiTenantEnabled,
            // Ohne Mandantenfähigkeit gibt's keine "Kunden verwalten"-Liste -
            // der Projektpfad des einzigen Mandanten braucht trotzdem eine
            // Stelle zum Bearbeiten (siehe TenantController::update()).
            // Bei aktiver MF ist "Kunden verwalten" auf den eigenen
            // Admin-Tab "Kunden" umgezogen (siehe TenantController::index())
            // - bei 100+ echten Kunden eine andere Größenordnung als die
            // Konfig des aktiven Kunden (Ralf: "oben die 4 Buttons und
            // drunter die vielen Kunden").
            'currentTenant' => $multiTenantEnabled ? null : Tenant::query()->find(CurrentTenant::id()),
        ]);
    }

}
