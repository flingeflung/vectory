<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\AttributeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'section', 'system', 'label_editable', 'applies_to_all_types', 'increments_on_new_version', 'default_value', 'required', 'log_changes', 'unit', 'key', 'label', 'data_type', 'multiple', 'number_min', 'number_max', 'number_decimals', 'max_length', 'sort', 'available_in_mail_templates'])]
#[ObservedBy(AttributeObserver::class)]
class Attribute extends Model
{
    use BelongsToTenant;

    /**
     * Bereich im Projekt, in dem das Attribut angezeigt wird (Ralf,
     * 2026-09-10) - nur SECTION_TYPSPEZIFISCH wird zusätzlich einzelnen
     * Projektarten zugewiesen (siehe projectTypeSubs()); die anderen beiden
     * gelten immer für alle Projekte.
     */
    public const SECTION_STAMMDATEN = 'stammdaten';

    public const SECTION_ABLAUFDATEN = 'ablaufdaten';

    public const SECTION_TYPSPEZIFISCH = 'typspezifisch';

    public const SECTIONS = [self::SECTION_STAMMDATEN, self::SECTION_ABLAUFDATEN, self::SECTION_TYPSPEZIFISCH];

    /**
     * System-Felder, deren Bezeichnung (anders als bei den übrigen System-
     * Feldern) pro Kunde änderbar ist - existieren/löschbar bleiben wie
     * jedes System-Feld fest, siehe SYSTEM_FIELDS-Docblock und Migration
     * 2026_09_13_053552.
     */
    public const LABEL_EDITABLE_SYSTEM_FIELDS = ['system_model'];

    /**
     * System-Felder, die als Attribute-Zeile bestehen bleiben (andere
     * Stellen hängen daran, siehe unten), aber weder im Projekt-Detail
     * noch in der Projektattribute-Verwaltung angezeigt werden. Ralf,
     * 2026-09-19: "checklist" raus aus den Projektdetails+Verwaltung,
     * "dafür haben wir ja einen eigenen Reiter" (Checklisten-Tab) - die
     * Kopieroption "Checkliste" bei den Kopiervorlagen (ProjectCopyController,
     * CopyTemplate::fields()) hängt aber an genau dieser Attribute-Zeile
     * und soll laut Ralf bestehen bleiben, deshalb kein echtes Löschen.
     */
    public const HIDDEN_SYSTEM_FIELDS = ['checklist'];

    /**
     * Feste Felder, die es schon vor der Attribut-Verwaltung gab (Ralf,
     * 2026-09-10: "frei mischbar mit Zusatzfeldern") - bekommen jetzt
     * eigene Attribute-Zeilen (system=true) statt nur einer hartkodierten
     * Anzeigeliste, damit sie einen echten, pro Kunde änderbaren sort-Wert
     * tragen und sich frei mit den Zusatzfeldern mischen lassen. Nur die
     * Reihenfolge ist über die Verwaltungsseite änderbar, nicht
     * Bezeichnung/Löschen - AUSSER bei den Feldern in
     * LABEL_EDITABLE_SYSTEM_FIELDS (siehe dort). Baujahr/Initiator/
     * Übersetzung-Lokalisierung sind bewusst NICHT hier drin - die sind auf
     * Ralfs Wunsch zu normalen Zusatzfeldern geworden (siehe attributes-
     * Migration/Datenmigration) und dürfen auch fehlen/gelöscht werden.
     */
    public const SYSTEM_FIELDS = [
        self::SECTION_STAMMDATEN => [
            'title' => 'Bezeichnung',
            // Ralf, 2026-09-11: Start+Ende sowie Status+Erstellungsstatus
            // bilden inhaltlich immer ein festes Paar - statt sie über eine
            // fragile Positions-/Sortier-Kopplung zusammenzuhalten (erst
            // Zufalls-Parität, dann ein explizites Paarungs-Flag, beides an
            // Ralfs eigenem Umsortieren gescheitert), rendert je ein
            // gemeinsames System-Feld beide Werte in einer Zeile (siehe
            // system-fields/start_date.blade.php, .../status.blade.php).
            // 'end_date' und 'creation_type' sind deshalb keine eigenen
            // Attribute-Zeilen mehr - die zugrunde liegenden DB-Spalten
            // projects.end_date/creation_type bleiben unverändert bestehen.
            'start_date' => 'Start/Ende',
            'project_type' => 'Projektkategorie/-art',
            // Ralf, 2026-09-20: "Stamm-Version" = unsere Zählung (Position in der Versionskette),
            // schreibgeschützt. Die "Kundenversion" ist ein normales Zusatzfeld je Kunde.
            'version' => 'Stamm-Version',
            'status' => 'Status/Erstellungsstatus',
            'markets' => 'Markt',
            // Ralf, 2026-09-13: erst als normales (löschbares) Zusatzfeld
            // gebaut, weil nur Zusatzfelder eine änderbare Bezeichnung
            // hatten - falscher Trade-off, siehe Migration
            // 2026_09_13_053552: Existenz muss garantiert sein wie bei
            // jedem anderen System-Feld, nur die Caption bleibt pro Kunde
            // frei ("bei Viega Modell, anderswo Typ/Produkt"). Feld wird
            // später ohnehin per PIM-Anbindung befüllt, nicht manuell.
            'system_model' => 'Modell/System',
            'remarks' => 'Bemerkungen',
        ],
        self::SECTION_ABLAUFDATEN => [
            'workflow_id' => 'Workflow',
            'publication_date' => 'Publikationsdatum',
            'project_people' => 'Projektbeteiligte Personen',
            'archived' => 'Archiviert',
            // Ralf, 2026-09-11: sechs neue, bewusst NICHT editierbare
            // Anzeigefelder (Vietto-Vorbild in ajax_getprojektdetails.php) -
            // "progress" ist nur ein Duplikat der schon vorhandenen
            // berechneten Fortschrittsanzeige (siehe system-fields/status),
            // "remarks_echo" ein Duplikat der Stammdaten-Bemerkungen -
            // beide haben also schon eine echte Datenquelle. Die übrigen
            // drei (Erstellungsstatus zu Stammdaten verschoben, s.o.) haben
            // (noch) keine Datenquelle in Vectory und zeigen bewusst nur
            // einen "– noch nicht verfügbar –"-Platzhalter, bis das
            // jeweilige Feature (Checkliste, Projektverbindungen) gebaut
            // ist - siehe Backlog-Memory.
            // date_progress vor progress (Ralf: "erst Datum, dann projekt") -
            // beide als eigene volle Zeile, direkt untereinander.
            'date_progress' => 'Datumsfortschritt',
            'progress' => 'Projektfortschritt',
            'checklist' => 'Checkliste',
            'project_connections' => 'Projektverknüpfungen',
            'remarks_echo' => 'Bemerkungen',
            'change_log' => 'Änderungsprotokoll',
            // Ralf, 2026-09-18: reiner Verweis auf eine Projektschablone
            // (Step 1 der Kapa-Planung), keine Werteübernahme ins Projekt -
            // Ralf: "das ist aus meiner Sicht mehr 'Ablauf'".
            'project_template' => 'Aufwandsschablone',
        ],
    ];

    /**
     * Feldtypen, gegen Viettos tatsächliche Projekt-Formularfelder
     * geprüft (Baujahr, MatNr, Farbigkeit, Heftung, Verpackung, ...) -
     * DATA_TYPE_SELECT deckt sowohl Einfach- als auch Mehrfachauswahl ab
     * (siehe $multiple), Vietto hatte dafür zwei getrennte, teils
     * hartkodierte Pulldown-Varianten, hier bewusst vereinheitlicht.
     */
    public const DATA_TYPE_TEXT = 'text';

    public const DATA_TYPE_TEXTAREA = 'textarea';

    public const DATA_TYPE_NUMBER = 'number';

    public const DATA_TYPE_DATE = 'date';

    public const DATA_TYPE_BOOLEAN = 'boolean';

    public const DATA_TYPE_SELECT = 'select';

    public const DATA_TYPES = [
        self::DATA_TYPE_TEXT,
        self::DATA_TYPE_TEXTAREA,
        self::DATA_TYPE_NUMBER,
        self::DATA_TYPE_DATE,
        self::DATA_TYPE_BOOLEAN,
        self::DATA_TYPE_SELECT,
    ];

    protected function casts(): array
    {
        return [
            'system' => 'boolean',
            'label_editable' => 'boolean',
            'multiple' => 'boolean',
            'available_in_mail_templates' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort');
    }

    public function projectTypeSubs(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTypeSub::class, 'attribute_project_type');
    }

    /**
     * Systemfelder, die sich (wie Zusatzfelder) auf ausgewählte Projektarten
     * beschränken lassen (Ralf, 2026-09-20). Alle übrigen Systemfelder sind
     * Kernfelder und gelten immer für jedes Projekt (Bezeichnung, Status,
     * Stamm-Version, Workflow, ...).
     */
    public const RESTRICTABLE_SYSTEM_FIELDS = ['system_model', 'markets', 'project_template', 'workflow_id', 'publication_date'];

    /**
     * Vorbelegung als echter Wert fürs attributes-JSON (Ralf, 2026-09-21): Zahl als Zahl, Ja/Nein als bool,
     * alles andere als Text bzw. Datum (Y-m-d) bzw. Optionswert.
     */
    public function castedDefault(): mixed
    {
        if ($this->default_value === null || $this->default_value === '') {
            return null;
        }

        return match ($this->data_type) {
            self::DATA_TYPE_NUMBER => is_numeric($this->default_value) ? $this->default_value + 0 : null,
            self::DATA_TYPE_BOOLEAN => $this->default_value === '1',
            default => $this->default_value,
        };
    }

    /**
     * Vorbelegungen aller Zusatzfelder, die für die Projektart gelten (ohne Art: nur Felder, die für alle
     * Arten gelten) - Schlüssel => Wert fürs attributes-JSON.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(int $tenantId, ?int $projectTypeSubId): array
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            ->whereNotNull('default_value')
            ->applicableTo($projectTypeSubId)
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (Attribute $attribute) => [$attribute->key => $attribute->castedDefault()])
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    /**
     * Einheiten ("Stück", "mm") aller Felder eines Mandanten, Schlüssel => Einheit - pro Anfrage einmal.
     *
     * @return array<string, string>
     */
    public static function unitMap(int $tenantId): array
    {
        static $cache = [];

        return $cache[$tenantId] ??= static::query()->where('tenant_id', $tenantId)->whereNotNull('unit')->where('unit', '<>', '')->pluck('unit', 'key')->all();
    }

    public function isRestrictable(): bool
    {
        return ! $this->system || in_array($this->key, self::RESTRICTABLE_SYSTEM_FIELDS, true);
    }

    /**
     * Nur Attribute, die für die Projektart gelten: "gilt für alle" oder der
     * Art zugewiesen (attribute_project_type).
     */
    public function scopeApplicableTo(\Illuminate\Database\Eloquent\Builder $query, ?int $projectTypeSubId): void
    {
        $query->where(function (\Illuminate\Database\Eloquent\Builder $query) use ($projectTypeSubId) {
            $query->where('applies_to_all_types', true)
                ->orWhereIn('id', function ($sub) use ($projectTypeSubId) {
                    $sub->select('attribute_id')->from('attribute_project_type')->where('project_type_sub_id', $projectTypeSubId);
                });
        });
    }

    /**
     * Geltung aller einschränkbaren Attribute eines Mandanten: Schlüssel => null
     * (gilt für alle Projektarten) oder Liste der Projektart-IDs. Pro Anfrage
     * einmal berechnet (Übersicht: "n.a." in Zellen, für die ein Feld nicht gilt).
     *
     * @return array<string, list<int>|null>
     */
    public static function applicabilityMap(int $tenantId, bool $refresh = false): array
    {
        static $cache = [];

        if ($refresh) {
            unset($cache[$tenantId]);
        }

        return $cache[$tenantId] ??= (function () use ($tenantId) {
            $attributes = static::query()->where('tenant_id', $tenantId)->where('applies_to_all_types', false)->pluck('key', 'id');
            $links = \Illuminate\Support\Facades\DB::table('attribute_project_type')->whereIn('attribute_id', $attributes->keys())->get()->groupBy('attribute_id');

            return $attributes->map(fn ($key, $id) => $links->get($id, collect())->pluck('project_type_sub_id')->map(fn ($v) => (int) $v)->all())
                ->mapWithKeys(fn ($ids, $id) => [$attributes[$id] => $ids])
                ->all();
        })();
    }

    /**
     * number_min/number_max kommen aus der DB als DECIMAL-String mit fixen
     * 4 Nachkommastellen (z.B. "5.0000") - für Anzeige/Formular-Vorbelegung
     * unschöne Nullen abschneiden ("5" statt "5.0000", "2.5" statt
     * "2.5000").
     */
    public function numberMinDisplay(): ?string
    {
        return self::trimTrailingZeros($this->number_min);
    }

    public function numberMaxDisplay(): ?string
    {
        return self::trimTrailingZeros($this->number_max);
    }

    private static function trimTrailingZeros(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
