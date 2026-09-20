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
    'construction_year', 'project_type_main_id', 'project_type_sub_id', 'project_template_id', 'version',
    'status', 'creation_type', 'archived', 'localization', 'publication_date', 'start_date', 'end_date', 'remarks',
    'attributes', 'workflow_id', 'verbund_rolle', 'hauptprojekt_id',
    'paper_format_combination_id', 'input_format_free_text', 'output_format_free_text',
    'stamm_id', 'stamm_position',
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
            'publication_date' => $this->publication_date?->format('d.m.Y'),
            'creation_type' => $this->creation_type_label,
            'verbund_rolle' => $this->verbund_rolle_label,
            'stamm_id' => \App\Support\StammId::format($this->stamm_id),
            'stamm_version' => __(':n von :total', ['n' => $this->stammPositionInfo()['position'], 'total' => $this->stammPositionInfo()['total']]),
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

    protected function creationTypeLabel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => match ($this->creation_type) {
                1 => __('Neuerstellung'),
                2 => __('Änderung'),
                default => null,
            },
        );
    }

    protected function verbundRolleLabel(): CastsAttribute
    {
        return CastsAttribute::make(
            get: fn () => match ($this->verbund_rolle) {
                1 => __('Hauptprojekt'),
                2 => __('Unterprojekt'),
                default => null,
            },
        );
    }

    /**
     * Echte Zuordnung zur Projektart - ein echter Fremdschlüssel, der auch
     * für nicht aus Vietto stammende Arten funktioniert (anders als die
     * inzwischen entfernten rohen Vietto-Legacy-Codes project_type_main/
     * project_type_sub).
     */
    public function projectTypeSub(): BelongsTo
    {
        // main mitladen - für die zweizeilige Icon-Darstellung
        // (Kategorie: Art) in Übersicht/Dashboard-Kacheln, sonst N+1.
        return $this->belongsTo(ProjectTypeSub::class, 'project_type_sub_id')->with('main');
    }

    public function projectTemplate(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class);
    }

    public function paperFormatCombination(): BelongsTo
    {
        return $this->belongsTo(PaperFormatCombination::class)->with(['inputFormat', 'outputFormat']);
    }

    public function projectTypeMain(): BelongsTo
    {
        return $this->belongsTo(ProjectTypeMain::class, 'project_type_main_id');
    }

    /**
     * Footer "angelegt durch"/"zuletzt geändert" (siehe ProjectObserver) -
     * bewusst NICHT in Fillable, damit das nur der Observer setzen kann,
     * nie ein manipulierter Request.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function projectWorkflowSteps(): HasMany
    {
        return $this->hasMany(ProjectWorkflowStep::class)->orderBy('sort');
    }

    public function projectChecklists(): HasMany
    {
        return $this->hasMany(ProjectChecklist::class)->with('checklist.sections.points');
    }

    public function projectChecklistPoints(): HasMany
    {
        return $this->hasMany(ProjectChecklistPoint::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->orderBy('created_at');
    }

    /**
     * Verknüpfte Produkte aus dem Mini-PIM (Ralf, 2026-09-13, analog
     * Viettos projekte_pim_cx) - Anzeige/Verwaltung über das "Modell/
     * System"-Feld, siehe ProjectProductController.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'project_products')->withTimestamps()->orderBy('name');
    }

    public function projectGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProjectGroup::class, 'project_group_project')->withTimestamps();
    }

    /**
     * "Projektverbund" (Ralf, 2026-09-14): genau 2 Ebenen, kein Nesting -
     * hauptprojekt_id nur bei verbund_rolle=2 (Unterprojekt) belegt.
     */
    public function hauptprojekt(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'hauptprojekt_id');
    }

    public function unterprojekte(): HasMany
    {
        return $this->hasMany(Project::class, 'hauptprojekt_id');
    }

    public function connectionsFrom(): HasMany
    {
        return $this->hasMany(ProjectConnection::class, 'project_id');
    }

    public function connectionsTo(): HasMany
    {
        return $this->hasMany(ProjectConnection::class, 'related_project_id');
    }

    /**
     * Verknüpfungen aus Sicht DIESES Projekts, unabhängig davon, ob es die
     * project_id- oder related_project_id-Seite der Zeile ist (siehe
     * ProjectConnection-Migration) - liefert je Verknüpfung das jeweils
     * andere Projekt und die aus dieser Richtung passende Bezeichnung.
     *
     * @return Collection<int, object{connection: ProjectConnection, otherProject: Project, label: string}>
     */
    public function connections(): Collection
    {
        return $this->connectionsFrom->map(fn (ProjectConnection $c) => (object) [
            'connection' => $c,
            'otherProject' => $c->relatedProject,
            'label' => $c->label,
        ])->concat(
            $this->connectionsTo->map(fn (ProjectConnection $c) => (object) [
                'connection' => $c,
                'otherProject' => $c->project,
                'label' => $c->label_reverse,
            ])
        );
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
     * Zeitlicher Fortschritt zwischen Start und Ende in Prozent (0-100),
     * analog Viettos getzeitbalken() in ajax_ueb_getpncontent.php - Anteil
     * der bereits verstrichenen Tage seit Start an der Gesamtlaufzeit
     * Start-Ende, auf "heute" bezogen. null ohne Start/Ende oder wenn Start
     * nach Ende liegt (ungültiger Bereich).
     */
    public function dateProgressPercent(): ?int
    {
        if (! $this->start_date || ! $this->end_date || $this->start_date->gt($this->end_date)) {
            return null;
        }

        $totalDays = max(1, (int) floor($this->start_date->diffInSeconds($this->end_date) / 86400));
        $elapsedDays = (int) floor((now()->timestamp - $this->start_date->timestamp) / 86400);

        return max(0, min(100, (int) floor(100 / $totalDays * $elapsedDays)));
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

    /**
     * Versionskette (Ralf, 2026-09-20): alle Projekte mit derselben Stamm-ID
     * (inkl. dieses), in Reihenfolge der Kette. Mandantengrenze über den
     * globalen Scope.
     */
    public function stammChain(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('stamm_id', $this->stamm_id)->orderBy('stamm_position')->orderBy('id');
    }

    /** Nächste freie Position am Ende der Kette. */
    public function nextStammPosition(): int
    {
        return ((int) static::query()->where('stamm_id', $this->stamm_id)->max('stamm_position')) + 1;
    }

    /** Vorberechnete Kettenposition (siehe preloadStammInfo) - spart in der Übersicht eine Abfrage je Zeile. */
    public ?array $stammInfoCache = null;

    /**
     * Kettenposition für eine ganze Projektliste mit EINER Abfrage vorberechnen
     * (Übersichtsspalte "Stamm-Version").
     *
     * @param  iterable<Project>  $projects
     */
    public static function preloadStammInfo(iterable $projects): void
    {
        $projects = collect($projects);
        $stammIds = $projects->pluck('stamm_id')->filter()->unique()->values();
        if ($stammIds->isEmpty()) {
            return;
        }

        $chains = static::query()->whereIn('stamm_id', $stammIds)
            ->orderBy('stamm_position')->orderBy('id')
            ->get(['id', 'stamm_id'])
            ->groupBy('stamm_id')
            ->map(fn ($members) => $members->pluck('id')->values()->all());

        foreach ($projects as $project) {
            $ids = $chains[$project->stamm_id] ?? [];
            $index = array_search($project->id, $ids, true);
            $project->stammInfoCache = ['position' => $index === false ? 1 : $index + 1, 'total' => max(count($ids), 1)];
        }
    }

    /** Position dieses Projekts in der Kette (1-basiert) und Länge der Kette. */
    public function stammPositionInfo(): array
    {
        if ($this->stammInfoCache !== null) {
            return $this->stammInfoCache;
        }

        $ids = $this->stammChain()->pluck('id')->all();
        $index = array_search($this->id, $ids, true);

        return ['position' => $index === false ? 1 : $index + 1, 'total' => max(count($ids), 1)];
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
     * @return Collection<int, Attribute>
     */
    public function relevantAttributes(): Collection
    {
        return Attribute::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('section', Attribute::SECTION_TYPSPEZIFISCH)
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
     * @return Collection<int, Attribute>
     */
    public function sectionAttributes(string $section): Collection
    {
        return Attribute::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('section', $section)
            ->whereNotIn('key', Attribute::HIDDEN_SYSTEM_FIELDS)
            ->with('options')
            ->orderBy('sort')
            ->get();
    }

    /**
     * Wie sectionAttributes(), aber ohne die reinen system=true-Zeilen
     * (Bezeichnung, Start, Workflow, ...) - die haben eigene, fest
     * verdrahtete Validierung/Schreiblogik in ProjectController::update()
     * und dürfen nicht über den generischen attributes-JSON-Merge laufen.
     * label_editable-System-Felder (aktuell nur "Modell/System") bleiben
     * dagegen drin - ihr Wert liegt wie bei einem echten Zusatzfeld im
     * attributes-JSON und hat keine eigene Schreiblogik.
     *
     * @return Collection<int, Attribute>
     */
    public function customSectionAttributes(string $section): Collection
    {
        return $this->sectionAttributes($section)
            ->filter(fn (Attribute $attribute) => ! $attribute->system || $attribute->label_editable)
            ->values();
    }
}
