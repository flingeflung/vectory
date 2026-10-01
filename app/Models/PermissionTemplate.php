<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * "Rechte-Set" (Ralfs Begriff) / "Schablone" - entspricht dem in großer
 * Business-Software verbreiteten "Profile"-Muster (z.B. Salesforce): jede
 * Ein Standard-User hat genau EIN Set, das seine fachlichen Rechte vollständig
 * bestimmt. Zugriffsstufen und Organisationsgrenzen bleiben davon getrennt.
 *
 * Ralf, 2026-09-28 (Datenschutz-Konflikt, siehe Migration): zwei Arten
 * dieses einen Modells, unterschieden über is_baustein.
 * - "Set" (is_baustein = false): das EINE, einer Person zuweisbare Set von
 *   oben - unverändert. Kann zusätzlich zu seinen eigenen Rechten beliebig
 *   viele Bausteine einbinden (bausteine()).
 * - "Baustein" (is_baustein = true): nie einer Person zuweisbar, nur in
 *   beliebig viele Sets einbindbar (usedInSets()) - und bindet selbst
 *   NIE weitere Bausteine ein (bausteine() bleibt für einen Baustein immer
 *   leer, per Konstruktion in Controller/View, nicht per DB-Constraint) -
 *   das schließt Ringbezüge aus, ohne sie erst prüfen zu müssen.
 *
 * Ralf, 2026-09-28 (Nachtrag, gleicher Tag): Bausteine lösen nur die
 * Wiederverwendbarkeits-Seite - ein Set entstand bisher immer durch
 * EINMALIGES KOPIEREN eines vorhandenen ("auf Basis von X"), Änderungen am
 * Original zogen bei der Kopie nie nach ("TR" ändert sich, "TR + PL"
 * bleibt stehen). Deshalb zusätzlich: ein Set kann optional genau EIN
 * anderes Set als "Basis" LEBEND referenzieren (basis_id) statt es zu
 * kopieren - effectivePermissions() liest die Basis-Rechte rekursiv live
 * mit. Ketten sind erlaubt, Ringbezüge werden beim Speichern geprüft
 * (siehe PermissionController::update()). Ein Baustein hat NIE eine
 * Basis - genau wie er nie selbst Bausteine einbindet, bleibt er ein
 * reines Blatt.
 */
// `role` remains fillable solely because an historical migration uses this
// model while rebuilding a database. The current schema removes the column.
#[Fillable(['tenant_id', 'role', 'name', 'sort', 'is_baustein', 'basis_id'])]
class PermissionTemplate extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_baustein' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_template_permission')->withTimestamps();
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Nur relevant, wenn dies selbst ein Set ist (is_baustein = false) -
     * die Bausteine, die dieses Set zusätzlich zu seinen eigenen Rechten
     * einbindet.
     */
    /**
     * withoutGlobalScope('tenant'): Ralf-Bug-Report 2026-09-28 - ohne das
     * filtert der Mandanten-Scope diese Relation nach dem gerade AKTIVEN
     * Mandanten des Prüfenden, nicht nach dem Mandanten des Sets selbst.
     * Weicht der aktive Mandant ab, wurde effectivePermissions() dadurch
     * still leer (Basis "verschwand"), obwohl beide Seiten im selben
     * Mandanten liegen - gleicher Fehler, den Person::permissionTemplate()/
     * User::person() aus genau diesem Grund schon immer vermeiden.
     */
    public function bausteine(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'permission_template_baustein', 'permission_template_id', 'baustein_id')->withoutGlobalScope('tenant')->withTimestamps();
    }

    /**
     * Nur relevant, wenn dies selbst ein Baustein ist (is_baustein = true) -
     * umgekehrte Richtung: welche Sets diesen Baustein einbinden (analog zu
     * people() bei einem Set, nur eine Ebene höher). withoutGlobalScope
     * siehe bausteine() oben.
     */
    public function usedInSets(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'permission_template_baustein', 'baustein_id', 'permission_template_id')->withoutGlobalScope('tenant')->withTimestamps();
    }

    /**
     * Nur relevant für ein Set - das andere Set, dessen Rechte hier LEBEND
     * (nicht kopiert) mit einfließen. Für einen Baustein immer null.
     * withoutGlobalScope siehe bausteine() oben.
     */
    public function basis(): BelongsTo
    {
        return $this->belongsTo(self::class, 'basis_id')->withoutGlobalScope('tenant');
    }

    /**
     * Umkehrung von basis() - welche Sets DIESES Set als Basis referenzieren.
     * Genutzt für die Lösch-Sperre (siehe PermissionController::destroy(),
     * gleiches Prinzip wie bei zugewiesenen Personen).
     */
    public function derivedSets(): HasMany
    {
        return $this->hasMany(self::class, 'basis_id');
    }

    /**
     * Eigene Rechte, plus aller eingebundenen Bausteine, plus (rekursiv)
     * die der Basis, falls gesetzt. Für einen Baustein selbst: nur die
     * eigenen, da bausteine()/basis() dort immer leer sind - keine Gefahr
     * einer Endlosschleife, ein Baustein bricht die Rekursion immer sofort ab.
     */
    public function effectivePermissions(): Collection
    {
        return $this->permissions
            ->concat($this->bausteine->flatMap->permissions)
            ->concat($this->basis?->effectivePermissions() ?? collect())
            ->unique('id');
    }

    public function hasPermission(string $key): bool
    {
        return $this->effectivePermissions()->contains(fn (Permission $permission) => $permission->key === $key);
    }

    /**
     * Die grobe Nutzer-Rolle (super_admin/admin/user), die assignTemplate()
     * auf den User-Datensatz überträgt - kommt bei gesetzter Basis IMMER
     * von dort (rekursiv bis zur Wurzel der Kette), nicht vom eigenen role-
     * Feld. Ein frisch angelegtes, leeres "TR + PL" braucht also keine
     * eigene korrekte role-Pflege - sie ergibt sich automatisch aus TR.
     */
}
