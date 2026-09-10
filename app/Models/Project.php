<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute as CastsAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'tenant_id', 'source_pn', 'title', 'codename', 'initiator', 'system_model',
    'construction_year', 'project_type_main', 'project_type_sub', 'project_type_main_id', 'project_type_sub_id', 'version',
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
     * Echte Zuordnung zur Projektart - anders als project_type_sub (roher
     * Vietto-Legacy-Code, siehe unten) ein echter Fremdschlüssel, der auch
     * für nicht aus Vietto stammende Arten funktioniert. project_type_sub
     * selbst bleibt unverändert bestehen (wird noch für
     * attribute_project_type/relevantAttributes() gebraucht).
     */
    public function projectTypeSub(): BelongsTo
    {
        // main mitladen - für die zweizeilige Icon-Darstellung
        // (Kategorie: Art) in Übersicht/Dashboard-Kacheln, sonst N+1.
        return $this->belongsTo(ProjectTypeSub::class, 'project_type_sub_id')->with('main');
    }

    public function projectTypeMain(): BelongsTo
    {
        return $this->belongsTo(ProjectTypeMain::class, 'project_type_main_id');
    }

    /**
     * Klartext-Projektart.
     */
    protected function projectTypeLabel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => $this->projectTypeSub?->name,
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
            get: fn () => $this->projectTypeSub?->color ?? '#f90',
        );
    }

    /**
     * Aufgelöste Projektart inkl. Hauptart/Icon - für die zweizeilige
     * Icon-Darstellung in der Übersichtstabelle (wie in Vietto).
     */
    protected function projectTypeSubModel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => $this->projectTypeSub,
        );
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

    /**
     * Fortschritt in Prozent (0-100), analog Viettos intStand: Position des
     * aktuellen Schritts unter allen Nicht-Ende-Schritten (lifecycle_status
     * < 4, also alles außer "Projekt verworfen") des zugewiesenen Workflows.
     * null, wenn kein Workflow zugewiesen oder noch kein Schritt aktuell ist.
     */
    public function progressPercent(): ?int
    {
        if (! $this->workflow_id) {
            return null;
        }

        $steps = $this->progressSteps();
        $currentIndex = $steps->search(fn (ProjectWorkflowStep $step) => $step->is_current);

        if ($currentIndex === false || $steps->count() < 2) {
            return $currentIndex === false ? null : 0;
        }

        return (int) floor(100 / ($steps->count() - 1) * $currentIndex);
    }

    /**
     * "aktueller Schritt/Schritte gesamt" (z.B. "2/18") für die
     * Workflow-Spalte der Übersicht, analog Viettos WF-Anzeige. null, wenn
     * kein Workflow zugewiesen oder noch kein Schritt aktuell ist.
     */
    public function progressStepLabel(): ?string
    {
        if (! $this->workflow_id) {
            return null;
        }

        $steps = $this->progressSteps();
        $currentIndex = $steps->search(fn (ProjectWorkflowStep $step) => $step->is_current);

        if ($currentIndex === false) {
            return null;
        }

        return ($currentIndex + 1).'/'.$steps->count();
    }

    /**
     * Titel des gerade aktuellen Schritts - für den Tooltip auf dem
     * Schritt-Zähler in der Übersicht (analog Viettos $wfstepname-Tooltip).
     */
    public function currentStepTitle(): ?string
    {
        if (! $this->workflow_id) {
            return null;
        }

        $steps = $this->progressSteps();
        $current = $steps->first(fn (ProjectWorkflowStep $step) => $step->is_current);

        return $current?->workflowStep->title;
    }

    /**
     * @return Collection<int, ProjectWorkflowStep>
     */
    private function progressSteps(): Collection
    {
        return $this->projectWorkflowSteps
            ->filter(fn (ProjectWorkflowStep $step) => $step->workflowStep && $step->workflowStep->workflow_id === $this->workflow_id)
            ->filter(fn (ProjectWorkflowStep $step) => $step->workflowStep->lifecycle_status < 4)
            ->sortBy('sort')
            ->values();
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
            ->where('section', \App\Models\Attribute::SECTION_TYPSPEZIFISCH)
            ->whereIn('id', function ($query) {
                $query->select('attribute_id')
                    ->from('attribute_project_type')
                    ->where('project_type_sub_id', $this->project_type_sub_id);
            })
            ->with('options')
            ->orderBy('sort')
            ->get();
    }

    /**
     * Zusatzattribute für Stammdaten/Ablaufdaten (Ralf, 2026-09-10) - anders
     * als relevantAttributes() nicht nach Projektart eingeschränkt, gelten
     * immer für alle Projekte des Mandanten (diese zwei Bereiche sind
     * inhaltlich nicht an eine Projektart gebunden).
     *
     * @return Collection<int, \App\Models\Attribute>
     */
    public function sectionAttributes(string $section): Collection
    {
        return \App\Models\Attribute::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('section', $section)
            ->with('options')
            ->orderBy('sort')
            ->get();
    }
}
