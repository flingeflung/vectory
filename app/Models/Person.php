<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    public function permissionTemplate(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class);
    }

    public function hasPermission(string $key): bool
    {
        return $this->permissionTemplate?->permissions()->where('key', $key)->exists() ?? false;
    }
}
