<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'tenant_id', 'source_pn', 'title', 'codename', 'initiator', 'system_model',
    'construction_year', 'project_type_main', 'project_type_sub', 'version',
    'status', 'archived', 'localization', 'publication_date', 'start_date', 'end_date', 'remarks',
    'attributes', 'workflow_id',
])]
#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'archived' => 'boolean',
            'localization' => 'boolean',
            'publication_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'attributes' => 'array',
        ];
    }

    /**
     * Löst einen Spalten-Key aus dem Anzeigefilter-Katalog gegen dieses
     * Projekt auf – "attribute:xxx" kommt aus dem attributes-JSON, alles
     * andere ist ein festes Feld/Accessor.
     */
    public function columnValue(string $key): mixed
    {
        if (str_starts_with($key, 'attribute:')) {
            return $this->getAttribute('attributes')[substr($key, strlen('attribute:'))] ?? null;
        }

        return match ($key) {
            'status' => $this->status_label,
            'project_type' => $this->project_type_label,
            'workflow' => $this->workflow?->name,
            'start_date' => $this->start_date?->format('d.m.Y'),
            'end_date' => $this->end_date?->format('d.m.Y'),
            default => $this->getAttribute($key),
        };
    }

    protected function statusLabel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => match ($this->status) {
                0 => __('Geplant'),
                1 => __('In Bearbeitung'),
                2 => __('Beendet'),
                3 => __('Verworfen'),
                default => __('Unbekannt'),
            },
        );
    }

    /**
     * Klartext-Projektart (aus project_types aufgelöst). Statisch pro
     * Mandant im Request gecacht, damit eine Tabellenseite (25 Zeilen)
     * nicht 25x dieselbe kleine Lookup-Tabelle abfragt.
     */
    protected function projectTypeLabel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: function () {
                if ($this->project_type_sub === null) {
                    return null;
                }

                $type = self::projectTypeLookup($this->tenant_id)[$this->project_type_sub] ?? null;

                return $type?->name ?? (string) $this->project_type_sub;
            },
        );
    }

    /**
     * Farbe des Balkens für die typspezifischen Attribute (aus der
     * Projektart in der DB, pro Mandant änderbar) – Standardfarbe, falls
     * für die Projektart (noch) keine eigene hinterlegt ist.
     */
    protected function attributeSectionColor(): CastsAttribute
    {
        return CastsAttribute::make(
            get: function () {
                $type = $this->project_type_sub !== null
                    ? self::projectTypeLookup($this->tenant_id)[$this->project_type_sub] ?? null
                    : null;

                return $type?->color ?? '#f90';
            },
        );
    }

    /**
     * Aufgelöste Projektart inkl. Hauptart/Icon - für die zweizeilige
     * Icon-Darstellung in der Übersichtstabelle (wie in Vietto).
     */
    protected function projectTypeSubModel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => $this->project_type_sub !== null
                ? self::projectTypeLookup($this->tenant_id)[$this->project_type_sub] ?? null
                : null,
        );
    }

    /**
     * @return array<int, ProjectTypeSub>
     */
    private static function projectTypeLookup(int $tenantId): array
    {
        static $cache = [];

        if (! isset($cache[$tenantId])) {
            $cache[$tenantId] = ProjectTypeSub::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('legacy_id')
                ->with('main')
                ->get()
                ->keyBy('legacy_id')
                ->all();
        }

        return $cache[$tenantId];
    }

    public function graphicOrders(): HasMany
    {
        return $this->hasMany(GraphicOrder::class);
    }

    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'project_market')->orderBy('sort');
    }

    public function projectPeople(): HasMany
    {
        return $this->hasMany(ProjectPerson::class);
    }

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function projectWorkflowSteps(): HasMany
    {
        return $this->hasMany(ProjectWorkflowStep::class)->orderBy('sort');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at');
    }

    public function isFavoritedBy(User $user): bool
    {
        return Favorite::query()->where('user_id', $user->id)->where('project_id', $this->id)->exists();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Variable Attribute, die für die Projektart dieses Projekts relevant
     * sind (Analogon zu Viettos attribute_projekttyp_cx).
     *
     * @return Collection<int, \App\Models\Attribute>
     */
    public function relevantAttributes(): Collection
    {
        return \App\Models\Attribute::query()
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('id', function ($query) {
                $query->select('attribute_id')
                    ->from('attribute_project_type')
                    ->where('project_type_sub', $this->project_type_sub);
            })
            ->orderBy('sort')
            ->get();
    }
}
