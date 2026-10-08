<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\TenantConfigCloner;
use App\Services\TenantPurger;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly TenantConfigCloner $configCloner, private readonly TenantPurger $purger) {}

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
            // ohne Auswahl die gerade aktive Organisation (Ralf, 2026-10-08: die Seite ersetzt die frühere Stammdaten-Seite)
            'selectedTenant' => $tenants->firstWhere('id', $request->filled('tenant') ? (int) $request->query('tenant') : CurrentTenant::id()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $this->validateIcon($request);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant = Tenant::query()->create([
            'name' => $name,
            'short_name' => $this->normalizedShortName($request, $name),
            'project_path' => $this->normalizedPath($request, 'project_path'),
            'arbeitsverzeichnis_path' => $this->normalizedPath($request, 'arbeitsverzeichnis_path'),
            'notification_email' => $this->normalizedNotificationEmail($request),
        ]);

        if ($request->hasFile('company_icon')) {
            $tenant->update(['icon_filename' => $this->storeIcon($request->file('company_icon'), $tenant)]);
        }

        // Feste Projektattribut-Felder (Bezeichnung, Start, Workflow, ...) -
        // jeder Mandant braucht sie (Ralf, 2026-09-10: "frei mischbar mit
        // Zusatzfeldern"). Alles Weitere übernimmt man danach über
        // "Konfiguration übernehmen".
        $this->configCloner->seedSystemAttributes($tenant);

        $this->seedDefaultPermissionTemplates($tenant);

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

        $this->validateIcon($request);
        $settings = $request->validate([
            'gantt_max_projects' => ['required', 'integer', 'between:1,200'],
            'jobload_time_grid' => ['required', 'integer', 'in:60,30,15'],
            'default_weekly_hours' => ['required', 'numeric', 'between:0,80', 'multiple_of:0.5'],
            'default_vacation_days' => ['required', 'numeric', 'between:0,100', 'multiple_of:0.5'],
        ]);
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $isHomeTenant = $request->boolean('is_home_tenant');
        // Höchstens ein Heimat-Mandant gleichzeitig (Ralf, 2026-09-18:
        // Zentral-Admin bekommt Vollzugriff auf alle Mandanten - bei zwei
        // gleichzeitig markierten wäre nicht mehr eindeutig, wer das ist).
        if ($isHomeTenant) {
            Tenant::query()->where('id', '!=', $tenant->id)->update(['is_home_tenant' => false]);
        }

        $previousIcon = $tenant->icon_filename;
        $newIcon = $request->hasFile('company_icon')
            ? $this->storeIcon($request->file('company_icon'), $tenant)
            : $previousIcon;

        $tenant->update([
            'name' => $name,
            'short_name' => $this->normalizedShortName($request, $name),
            'project_path' => $this->normalizedPath($request, 'project_path'),
            'arbeitsverzeichnis_path' => $this->normalizedPath($request, 'arbeitsverzeichnis_path'),
            'notification_email' => $this->normalizedNotificationEmail($request),
            'is_home_tenant' => $isHomeTenant,
            'show_unopenable_projects' => $request->boolean('show_unopenable_projects'),
            'icon_filename' => $newIcon,
            ...$settings,
        ]);

        if ($newIcon !== $previousIcon) {
            $this->deleteManagedIcon($tenant, $previousIcon);
        }

        return redirect()->back()->with('status', 'tenant-updated');
    }

    /**
     * Löschen nur, solange noch keine echten Daten (Personen/Projekte) für
     * den Mandanten angelegt wurden - Ralf: "sofern noch keine Daten dafür
     * angelegt sind". Verhindert versehentliches Löschen eines bereits
     * genutzten Kunden.
     */
    /**
     * Organisation komplett deaktivieren bzw. wieder aktivieren (Ralf, 2026-10-03; nur Super-Admin, siehe
     * Route). Nichts wird gelöscht, die Daten sind nur überall ausgeblendet (Tenant::inactiveIds()). Die
     * Heimat-Organisation bleibt immer aktiv.
     */
    public function setActive(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);
        abort_if($tenant->is_home_tenant, 422, __('Die Heimat-Organisation kann nicht deaktiviert werden.'));

        $tenant->update(['is_active' => $request->boolean('active')]);

        return redirect()->route('admin.kunden', ['tenant' => $tenant->id])
            ->with('status', $tenant->is_active ? 'tenant-activated' : 'tenant-deactivated');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);
        abort_if($tenant->hasData(), 422, 'Diese Organisation hat bereits Daten und kann nicht gelöscht werden.');

        $this->deleteManagedIcon($tenant, $tenant->icon_filename);
        $tenant->delete();

        return redirect()->route('admin.kunden');
    }

    /**
     * Endgültig löschen samt Personen, Projekten und allen Daten (Ralf, 2026-10-03: Test-Organisationen
     * rückstandsfrei entfernen). Nur Super-Admin; zur Sicherheit muss der Name der Organisation eingetippt werden.
     */
    public function purge(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);
        abort_if($tenant->is_home_tenant, 422);

        if (trim((string) $request->input('confirm_name')) !== $tenant->name) {
            return back()->with('error', __('Der eingegebene Name stimmt nicht überein. Es wurde nichts gelöscht.'));
        }

        $name = $tenant->name;
        $this->purger->purge($tenant);

        return redirect()->route('admin.kunden')->with('notice', __('Die Organisation „:name“ wurde mit allen Daten gelöscht.', ['name' => $name]));
    }

    private function normalizedPath(Request $request, string $field): ?string
    {
        $path = trim((string) $request->string($field));

        return $path === '' ? null : $path;
    }

    private function validateIcon(Request $request): void
    {
        $request->validate([
            'company_icon' => ['nullable', 'file', 'max:2048', 'mimes:svg,png,jpg,jpeg,webp'],
        ]);
    }

    private function storeIcon(UploadedFile $file, Tenant $tenant): string
    {
        $directory = public_path('images/company-icons');
        File::ensureDirectoryExists($directory);
        $filename = 'tenant-'.$tenant->id.'-'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);

        return $filename;
    }

    private function deleteManagedIcon(Tenant $tenant, ?string $filename): void
    {
        if ($filename && str_starts_with($filename, 'tenant-'.$tenant->id.'-')) {
            File::delete(public_path('images/company-icons/'.$filename));
        }
    }

    /**
     * Ziel für Vectory-generierte Mails an diesen Kunden (z.B.
     * Projektanfragen, siehe ProjectController::submitRequest()) - Ralf:
     * "Mail-Adresse für Infos von vectory", pro Kunde in der
     * Konfiguration hinterlegbar.
     *
     * PFLICHTFELD (Ralf, 2026-09-27): sie ist der letzte Fallback, damit
     * Rückmeldungen (z.B. Freigabe-Mails ohne Zuständige mit Adresse) nie
     * ins Leere gehen. Bei der Kundenanlage genügt irgendeine gültige
     * Adresse (z.B. die des Admins), sie lässt sich jederzeit ändern.
     */
    private function normalizedNotificationEmail(Request $request): string
    {
        $email = trim((string) $request->string('notification_email'));
        abort_if($email === '', 422, __('Bitte eine Info-E-Mail eintragen. Zunächst genügt irgendeine gültige Adresse, z. B. Ihre eigene; sie lässt sich später jederzeit ändern.'));
        abort_if(! filter_var($email, FILTER_VALIDATE_EMAIL), 422, __('Ungültige E-Mail-Adresse.'));

        return $email;
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
     * Verhalten wie die ursprüngliche Seed-Migration.
     */
    private function seedDefaultPermissionTemplates(Tenant $tenant): void
    {
        foreach ([__('Alle Benutzerrechte') => true, __('User') => false] as $name => $allPermissions) {
            $reference = PermissionTemplate::query()
                ->withoutGlobalScope('tenant')
                ->where('name', $name)
                ->where('tenant_id', '!=', $tenant->id)
                ->orderBy('tenant_id')
                ->orderBy('sort')
                ->first();

            $template = PermissionTemplate::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
            ]);

            $permissionIds = $reference
                ? $reference->permissions()->pluck('permissions.id')
                : ($allPermissions ? Permission::query()->pluck('id') : collect());

            $template->permissions()->sync($permissionIds);
        }
    }
}
