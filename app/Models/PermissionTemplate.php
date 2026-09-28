<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Rechte-Set" (Ralfs Begriff) / "Schablone" - entspricht dem in großer
 * Business-Software verbreiteten "Profile"-Muster (z.B. Salesforce): jede
 * Person hat genau EIN Set, das ihre Rechte vollständig bestimmt. Admin 2
 * kann eigene Sets anlegen (i.d.R. durch Klonen eines vorhandenen), Rechte
 * werden ausschließlich über das zugewiesene Set vererbt, nie individuell.
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
 */
#[Fillable(['tenant_id', 'role', 'name', 'sort', 'is_baustein'])]
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
    public function bausteine(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'permission_template_baustein', 'permission_template_id', 'baustein_id')->withTimestamps();
    }

    /**
     * Nur relevant, wenn dies selbst ein Baustein ist (is_baustein = true) -
     * umgekehrte Richtung: welche Sets diesen Baustein einbinden (analog zu
     * people() bei einem Set, nur eine Ebene höher).
     */
    public function usedInSets(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'permission_template_baustein', 'baustein_id', 'permission_template_id')->withTimestamps();
    }

    /**
     * Eigene Rechte plus die aller eingebundenen Bausteine (für einen
     * Baustein selbst: nur die eigenen, da bausteine() dort immer leer
     * ist). Keine Rekursion nötig - ein Baustein kann per Konstruktion
     * keine weiteren Bausteine einbinden.
     */
    public function effectivePermissions(): \Illuminate\Support\Collection
    {
        return $this->permissions->concat($this->bausteine->flatMap->permissions)->unique('id');
    }

    public function hasPermission(string $key): bool
    {
        return $this->effectivePermissions()->contains(fn (Permission $permission) => $permission->key === $key);
    }
}
