<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\TenantConfigCloner;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly TenantConfigCloner $configCloner) {}

    /**
     * Eigener Admin-Tab statt Teil der Konfig-Seite - Ralf: bei 100+
     * echten Kunden ist "Kunden verwalten" eine andere Größenordnung als
     * die Konfig des gerade aktiven Kunden ("oben die 4 Buttons und
     * drunter die vielen Kunden"). Nur bei aktiver Mandantenfähigkeit
     * sichtbar/erreichbar - ohne MF ist der eine Mandant keine "Kunden"-
     * Liste, sondern die eigene Firma, die bleibt auf der Konfig-Seite.
     */
    public function index(Request $request): View
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $tenants = Tenant::query()->orderBy('name')->get();

        return view('admin.kunden.index', [
            'tenants' => $tenants,
            'selectedTenant' => $request->filled('tenant') ? $tenants->firstWhere('id', (int) $request->query('tenant')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $sourceTenant = $request->filled('source_tenant_id')
            ? Tenant::query()->find($request->integer('source_tenant_id'))
            : null;

        $tenant = Tenant::query()->create([
            'name' => $name,
            'short_name' => $this->normalizedShortName($request, $name),
            'project_path' => $this->normalizedProjectPath($request),
            'notification_email' => $this->normalizedNotificationEmail($request),
        ]);

        if ($sourceTenant) {
            // Ralf: "für den TR-DL brauchen wir eine Funktion, um Dinge von
            // einem Kunden zum anderen kopieren zu können" (über 100 Kunden
            // beim ehemaligen Arbeitgeber - jedes Mal alles neu anlegen wäre
            // unzumutbar). Kopiert NUR Struktur/Konfiguration (Funktions-
            // gruppen, Abteilungen, Geschäftsbereiche, Rollen, Workflows,
            // Projektarten, Märkte), KEINE echten Daten.
            $this->configCloner->clone($sourceTenant, $tenant);
        }

        // Feste Projektattribut-Felder (Bezeichnung, Start, Workflow, ...) -
        // jeder Mandant braucht sie, unabhängig davon, ob von einem
        // Quell-Mandanten geklont wurde (Ralf, 2026-09-10: "frei mischbar
        // mit Zusatzfeldern").
        $this->configCloner->seedSystemAttributes($tenant);

        $this->seedDefaultPermissionTemplates($tenant, $sourceTenant);

        // Ralf: sonst könnte man unbemerkt im falschen (vorher aktiven)
        // Kunden weiterarbeiten - direkt nach dem Anlegen zum neuen Kunden
        // umschalten, statt beim bisherigen aktiven Kunden zu bleiben.
        CurrentTenant::switchTo($tenant->id);

        return redirect()->route('admin.kunden', ['tenant' => $tenant->id]);
    }

    /**
     * Ohne Mandantenfähigkeit gibt es keine "Kunden verwalten"-Liste, aber
     * der einzige Mandant braucht trotzdem eine Stelle, um seinen eigenen
     * Projektpfad zu setzen (siehe ConfigController::index(),
     * currentTenant) - deshalb hier zusätzlich zum eigenen Mandanten
     * erlaubt, nicht nur wenn Mandantenfähigkeit an ist. redirect()->back()
     * statt einer festen Route, weil dieses Formular von zwei Stellen aus
     * aufgerufen wird (Konfig-Seite ohne MF, Kunden-Seite mit MF).
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled() || $tenant->id === CurrentTenant::id(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant->update([
            'name' => $name,
            'short_name' => $this->normalizedShortName($request, $name),
            'project_path' => $this->normalizedProjectPath($request),
            'notification_email' => $this->normalizedNotificationEmail($request),
        ]);

        return redirect()->back()->with('status', 'tenant-updated');
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

        return redirect()->route('admin.kunden');
    }

    private function normalizedProjectPath(Request $request): ?string
    {
        $path = trim((string) $request->string('project_path'));

        return $path === '' ? null : $path;
    }

    /**
     * Ziel für Vectory-generierte Mails an diesen Kunden (z.B.
     * Projektanfragen, siehe ProjectController::submitRequest()) - Ralf:
     * "Mail-Adresse für Infos von vectory", pro Kunde in der
     * Konfiguration hinterlegbar.
     */
    private function normalizedNotificationEmail(Request $request): ?string
    {
        $email = trim((string) $request->string('notification_email'));
        abort_if($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL), 422, __('Ungültige E-Mail-Adresse.'));

        return $email === '' ? null : $email;
    }

    /**
     * Kürzel für schmale Anzeigen (Personenliste-Spalte, Kunde-Filter,
     * Kunde-Feld im Personen-Overlay) - "Sanitär & Söhne sprengt gerade
     * unser Layout". Ohne Eingabe automatisch aus dem Namen abgeleitet,
     * gleiches Prinzip wie bei Company::short_name.
     */
    private function normalizedShortName(Request $request, string $name): string
    {
        $shortName = trim((string) $request->string('short_name')) ?: $name;

        return mb_substr($shortName, 0, 10);
    }

    /**
     * Ohne das hier hätte ein frischer Kunde GAR KEIN Rechte-Set -
     * "Neues Set anlegen" in der Rechte-Verwaltung braucht zwingend ein
     * bestehendes Set als Basis ("Auf Basis von…" ist Pflichtfeld), die
     * Liste wäre also leer und das UI eine Sackgasse. Kopiert die aktuelle
     * Rechte-Belegung eines bestehenden Referenz-Kunden (ältester Tenant
     * mit einem Set dieser Rolle) statt eine zweite, eigene Liste zu
     * pflegen, die bei jedem neuen "Alltags-Recht" (siehe die project.*-
     * Migrationen) separat aktualisiert werden müsste - bleibt so von
     * selbst konsistent mit dem, was bestehende Kunden tatsächlich haben.
     * Fallback (keine bestehenden Kunden, z.B. Erstinstallation): Admin
     * bekommt alle aktuellen Rechte, User startet leer - gleiches
     * Verhalten wie die ursprüngliche Seed-Migration. Wurde beim Anlegen
     * explizit "als Kopie von" ein Referenz-Kunde gewählt, werden dessen
     * eigene Sets 1:1 übernommen statt eines beliebigen anderen Kunden -
     * konsistent mit dem, was sonst per TenantConfigCloner kopiert wird.
     */
    private function seedDefaultPermissionTemplates(Tenant $tenant, ?Tenant $preferredSource = null): void
    {
        foreach (['admin', 'user'] as $role) {
            $reference = PermissionTemplate::query()
                ->withoutGlobalScope('tenant')
                ->where('role', $role)
                ->when(
                    $preferredSource,
                    fn ($query) => $query->where('tenant_id', $preferredSource->id),
                    fn ($query) => $query->where('tenant_id', '!=', $tenant->id),
                )
                ->orderBy('tenant_id')
                ->orderBy('sort')
                ->first();

            $template = PermissionTemplate::query()->create([
                'tenant_id' => $tenant->id,
                'role' => $role,
                'name' => $role === 'admin' ? __('Admin') : __('User'),
            ]);

            $permissionIds = $reference
                ? $reference->permissions()->pluck('permissions.id')
                : ($role === 'admin' ? Permission::query()->pluck('id') : collect());

            $template->permissions()->sync($permissionIds);
        }
    }
}
