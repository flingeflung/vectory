<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Fillable(['name', 'short_name', 'icon_filename', 'project_path', 'arbeitsverzeichnis_path', 'notification_email', 'max_project_copies', 'gantt_max_projects', 'jobload_time_grid', 'default_weekly_hours', 'default_vacation_days', 'is_home_tenant', 'is_active', 'show_unopenable_projects'])]
class Tenant extends Model
{
    /** @var array<int, int>|null */
    private static ?array $inactiveIds = null;

    private static ?array $existingIds = null;

    protected function casts(): array
    {
        return ['is_home_tenant' => 'boolean', 'is_active' => 'boolean', 'show_unopenable_projects' => 'boolean'];
    }

    protected static function booted(): void
    {
        $forget = function () {
            self::$inactiveIds = null;
            self::$existingIds = null;
        };
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * IDs aller deaktivierten Organisationen (ein Abruf je Anfrage). Grundlage der Schutzregel
     * "tenant_active" in BelongsToTenant: Daten dieser Organisationen sind überall ausgeblendet,
     * auch dort, wo die Regel "tenant" bewusst umgangen wird. Die Heimat-Organisation ist nie darunter.
     *
     * @return array<int, int>
     */
    public static function inactiveIds(): array
    {
        // Während älterer Migrationen existiert die Spalte noch nicht (diese laufen mit Eloquent-Modellen und
        // lösen die Schutzregel schon aus): dann ist nichts deaktiviert.
        if (self::$inactiveIds === null && ! Schema::hasColumn('tenants', 'is_active')) {
            return [];
        }

        return self::$inactiveIds ??= DB::table('tenants')
            ->where('is_active', false)
            ->where('is_home_tenant', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * IDs aller vorhandenen Organisationen - damit eine gelöschte, aber noch in der Sitzung gemerkte
     * Organisation nicht als "aktiv" weiterlebt (Ralf, 2026-10-03: gelöschte Organisation im Schalter gewählt).
     *
     * @return array<int, int>
     */
    public static function existingIds(): array
    {
        return self::$existingIds ??= DB::table('tenants')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function forgetInactiveCache(): void
    {
        self::$inactiveIds = null;
        self::$existingIds = null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function iconUrl(): ?string
    {
        return $this->icon_filename
            ? asset('images/company-icons/'.rawurlencode($this->icon_filename))
            : null;
    }

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
