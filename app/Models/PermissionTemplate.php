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
 */
#[Fillable(['tenant_id', 'role', 'name', 'sort'])]
class PermissionTemplate extends Model
{
    use BelongsToTenant;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_template_permission')->withTimestamps();
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
