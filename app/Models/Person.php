<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'legacy_id', 'first_name', 'last_name', 'email',
    'company_id', 'department_id', 'legacy_role_id', 'last_login_at',
    'start_date', 'end_date', 'remarks', 'language', 'sort', 'active',
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
}
