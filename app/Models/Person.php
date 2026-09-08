<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'tenant_id', 'legacy_id', 'first_name', 'last_name', 'short_name', 'email',
    'company_id', 'department_id', 'business_unit_id', 'legacy_role_id', 'permission_template_id',
    'last_login_at', 'start_date', 'end_date', 'remarks', 'language', 'sort', 'active',
])]
class Person extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_login_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Reines Zuordnungs-Hilfsattribut neben Firma/Abteilung, bewusst OHNE
     * Abhängigkeit zu diesen (z.B. bei Viega aktuell "Global"/"Regional",
     * unabhängig von der Abteilung) - kein Einfluss auf Prozesse/Rechte.
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function legacyRole(): BelongsTo
    {
        return $this->belongsTo(LegacyRole::class);
    }

    /**
     * "Nachname, Vorname" - matches the sort/display convention used
     * throughout Vietto for people lists.
     */
    public function fullName(): string
    {
        return trim("{$this->last_name}, {$this->first_name}", ', ');
    }

    /**
     * Rein für Task-Routing (Illustrator-Auswahl etc.) - hat bewusst KEINEN
     * Einfluss mehr auf Rechte, siehe PermissionTemplate/hasPermission().
     * Grund: eine Person kann in mehreren Fktgrps sein, was bei einer
     * Rechte-Vererbung über Fktgrps zu ungewollten Rechten führen würde
     * (z.B. ein PM, der aus Routing-Gründen auch in "Lektorat" ist, hätte
     * sonst automatisch Lektorat-Rechte).
     */
    public function functionGroups(): BelongsToMany
    {
        return $this->belongsToMany(FunctionGroup::class, 'function_group_member');
    }

    /**
     * Jede Person hat genau EIN Rechte-Set, das ihre Rechte vollständig
     * bestimmt - keine individuellen Ausnahmen mehr (die führten zu nicht
     * mehr nachvollziehbarem "Permission Sprawl", siehe Rechtekonzept-
     * Diskussion). Admin 2 kann sich in der Rechte-Verwaltung beliebig
     * viele eigene Sets anlegen (z.B. "PM", "Lektorat") und Personen frei
     * zuordnen. Super-Admin braucht kein Set, siehe Gate::before().
     */
    /**
     * withoutGlobalScope('tenant'): das eigene Rechte-Set einer Person
     * gehört zu IHREM Heimat-Mandanten, nicht zum gerade aktiven Kunden -
     * ohne das würde hasPermission() fälschlich "kein Recht" liefern,
     * sobald ein anderer Kunde als der eigene aktiv ist (gleiche Ursache
     * wie beim User::person()-Fix).
     */
    public function permissionTemplate(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class)->withoutGlobalScope('tenant');
    }

    public function hasPermission(string $key): bool
    {
        return $this->permissionTemplate?->permissions()->where('key', $key)->exists() ?? false;
    }

    /**
     * Zusätzliche Kunden-Mandanten, auf die diese Person umschalten darf -
     * neben ihrem eigenen Heimat-Mandanten (tenant_id). Nur relevant, wenn
     * Mandantenfähigkeit aktiv ist (siehe SystemSetting).
     */
    public function accessibleTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    /**
     * Heimat-Personen des angegebenen Kunden PLUS Personen mit
     * Kundenzugriff-Freigabe dafür (siehe accessibleTenants/person_tenant) -
     * "wer steht mir hier zur Verfügung" (Kunden-eigene Personen + eigene
     * DL-Mitarbeiter, die für diesen Kunden freigeschaltet sind). Zentrale
     * Stelle für dieses Muster - ursprünglich nur in PersonController für
     * die Personenliste gebaut, dann bei Funktionsgruppen/Rechte-Verwaltung
     * vergessen (Ralf: "Ich bin in der Maschinen AG. Ich kann hier gar
     * keine TR der Fktgrp zuweisen" - eigene TR-Mitarbeiter fehlten dort).
     * Aufrufer braucht vorher withoutGlobalScope('tenant'), sonst hängt
     * BelongsToTenant automatisch UND tenant_id = aktiver Kunde davor.
     */
    public function scopeVisibleInTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $query) use ($tenantId) {
            $query->where('tenant_id', $tenantId)
                ->orWhereHas('accessibleTenants', fn (Builder $query) => $query->where('tenants.id', $tenantId));
        });
    }

    /**
     * Boolean-Variante von scopeVisibleInTenant() für eine bereits geladene
     * Person (z.B. per Route-Model-Binding) - rohe DB::table-Abfrage statt
     * der Eloquent-Relation, damit dieselbe Prüfung auch ohne Query-Kontext
     * (einfach $person->isVisibleInTenant($id)) funktioniert.
     */
    public function isVisibleInTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId
            || DB::table('person_tenant')->where('person_id', $this->id)->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Der aktive Kunde PLUS die Heimat-Mandanten aller Personen, die per
     * Kundenzugriff dafür freigegeben sind - für Filter-Dropdowns
     * (Firma/Abteilung/GB/Rechte-Set/Rolle in Personenliste, Funktions-
     * gruppen, Rechte-Verwaltung), deren Katalog sonst nur den aktiven
     * Kunden abdecken würde. Bewusst aus den grundsätzlich sichtbaren
     * MANDANTEN abgeleitet, nicht aus den gerade angezeigten Personen -
     * sonst wäre die Liste bei einem frischen Kunden ohne Personen leer,
     * obwohl der Katalog (z.B. Abteilungen) längst existiert (Ralfs
     * Bug-Report: Abteilungen angelegt, Dropdown trotzdem leer, weil noch
     * niemand zugeordnet war).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function visibleTenantIds(int $tenantId): \Illuminate\Support\Collection
    {
        $grantedHomeTenantIds = self::query()
            ->withoutGlobalScope('tenant')
            ->whereHas('accessibleTenants', fn (Builder $query) => $query->where('tenants.id', $tenantId))
            ->pluck('tenant_id');

        return $grantedHomeTenantIds->push($tenantId)->unique()->values();
    }

    /**
     * Blendet Super-Admin-Konten in Listen/Auswahlen für alle Logins
     * UNTERHALB der Superadmin-Rolle aus - Ralf: "es beginnt zu stören,
     * dass der Account des Superadmins überall angezeigt wird". Ersetzt
     * das frühere Ausgrauen in der Personenliste (person-link.blade.php):
     * tauchen sie gar nicht erst auf, muss dort auch nichts mehr gesperrt
     * werden. Einzelzugriff per direktem Link bleibt zusätzlich über
     * PersonController::abortIfProtectedFromEditing() gesperrt.
     */
    public function scopeVisibleToRole(Builder $query, string $viewerRole): Builder
    {
        return $query->when(
            $viewerRole !== 'super_admin',
            fn (Builder $query) => $query->whereDoesntHave('user', fn (Builder $query) => $query->where('role', 'super_admin')),
        );
    }

    /**
     * Ob diese Person schon irgendwo "echte" Daten hat - Login, Projekt-/
     * Workflow-Zuordnungen, Aufgaben, Illustrationsaufträge - und deshalb
     * nicht mehr gefahrlos gelöscht werden kann, nur noch inaktiv gesetzt.
     * Ralf: aus Versehen unter dem falschen Kunden angelegt, direkt wieder
     * gemerkt - "ich kann sie dann nur inaktiv setzen und sie bleibt auf
     * ewig als Leiche rumliegen". Funktionsgruppen-Mitgliedschaft und
     * Kundenzugriff-Freigabe zählen bewusst NICHT als "echte Daten" - reine
     * Einstellungen, kein tatsächlich geleisteter Arbeitsschritt, und genau
     * die Art Häkchen, die man vor dem Bemerken des Kunden-Irrtums schon
     * gesetzt haben könnte.
     */
    public function hasData(): bool
    {
        return $this->user !== null
            || DB::table('project_people')->where('person_id', $this->id)->exists()
            || DB::table('project_workflow_step_people')->where('person_id', $this->id)->exists()
            || DB::table('project_workflow_steps')->where('completed_by_person_id', $this->id)->exists()
            || DB::table('tasks')->where('person_id', $this->id)->exists()
            || DB::table('graphic_orders')
                ->where('initiated_by_person_id', $this->id)
                ->orWhere('illustrator_person_id', $this->id)
                ->orWhere('completed_by_person_id', $this->id)
                ->exists();
    }
}
