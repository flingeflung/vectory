<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Löscht eine (Test-)Organisation endgültig und rückstandsfrei (Ralf, 2026-10-03): alle ihre Daten,
 * ihre Benutzer und - nur wenn kein anderer Mandant sie mehr braucht - die technischen Attribut-Spalten
 * der gemeinsamen Projekte-Tabelle. Die Daten selbst entfernt die Datenbank per Kaskade (alle
 * Fremdschlüssel auf tenants sind CASCADE); neue Tabellen mit tenant_id müssen das ebenfalls sein.
 * Dateien auf der Platte (Projektpfade) werden nie angefasst. Die Heimat-Organisation ist tabu.
 */
class TenantPurger
{
    public function __construct(private readonly AttributeColumnManager $columns) {}

    public function purge(Tenant $tenant): void
    {
        abort_if($tenant->is_home_tenant, 422, __('Die Heimat-Organisation kann nicht gelöscht werden.'));

        // Merken, welche Spalten dieser Mandant ggf. verwaist hinterlässt (nach dem Löschen gibt es die Attribute nicht mehr).
        $attributes = Attribute::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('system', false)->get();

        DB::transaction(function () use ($tenant) {
            // Normale Benutzer der Organisation gehen mit; Admin-Stufen bleiben erhalten (Fremdschlüssel setzt sie auf "ohne Organisation").
            User::query()->where('tenant_id', $tenant->id)->whereIn('role', ['user', 'organization_admin'])->delete();
            $tenant->delete();
        });

        if ($tenant->icon_filename && str_starts_with($tenant->icon_filename, 'tenant-'.$tenant->id.'-')) {
            File::delete(public_path('images/company-icons/'.$tenant->icon_filename));
        }

        // DDL committet implizit - deshalb außerhalb der Transaktion. Spalte nur weg, wenn kein anderer Mandant denselben key nutzt.
        foreach ($attributes as $attribute) {
            $this->columns->dropColumnIfUnused($attribute);
        }
    }
}
