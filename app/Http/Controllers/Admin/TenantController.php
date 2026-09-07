<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Tenant::query()->create([
            'name' => $name,
            'project_path' => $this->normalizedProjectPath($request),
        ]);

        return redirect()->route('admin.config');
    }

    /**
     * Ohne Mandantenfähigkeit gibt es keine "Kunden verwalten"-Liste, aber
     * der einzige Mandant braucht trotzdem eine Stelle, um seinen eigenen
     * Projektpfad zu setzen (siehe ConfigController::index(),
     * currentTenant) - deshalb hier zusätzlich zum eigenen Mandanten
     * erlaubt, nicht nur wenn Mandantenfähigkeit an ist.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled() || $tenant->id === CurrentTenant::id(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant->update([
            'name' => $name,
            'project_path' => $this->normalizedProjectPath($request),
        ]);

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

    private function normalizedProjectPath(Request $request): ?string
    {
        $path = trim((string) $request->string('project_path'));

        return $path === '' ? null : $path;
    }
}
