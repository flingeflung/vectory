<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'project_path'])]
class Tenant extends Model
{
    /**
     * Ob für diesen Mandanten schon "echte" Daten angelegt wurden (Personen
     * oder Projekte) - entscheidet, ob er noch gefahrlos gelöscht werden
     * kann. Bewusst rohe DB-Abfragen statt Person::/Project::where(...):
     * beide Modelle sind über BelongsToTenant automatisch auf den gerade
     * AKTIVEN Mandanten gescoped - ein Check für einen ANDEREN Mandanten
     * (der Normalfall hier, siehe TenantController) würde über die
     * Eloquent-Relation sonst immer fälschlich 0 liefern.
     */
    public function hasData(): bool
    {
        return DB::table('people')->where('tenant_id', $this->id)->exists()
            || DB::table('projects')->where('tenant_id', $this->id)->exists();
    }
}
