<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Tenant::query()->create(['name' => $name]);

        return redirect()->route('admin.config');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant->update(['name' => $name]);

        return redirect()->route('admin.config');
    }

    /**
     * Löschen nur, solange noch keine echten Daten (Personen/Projekte) für
     * den Mandanten angelegt wurden - Ralf: "sofern noch keine Daten dafür
     * angelegt sind". Verhindert versehentliches Löschen eines bereits
     * genutzten Kunden.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);
        abort_if($tenant->hasData(), 422, 'Dieser Kunde hat bereits Daten und kann nicht gelöscht werden.');

        $tenant->delete();

        return redirect()->route('admin.config');
    }
}
