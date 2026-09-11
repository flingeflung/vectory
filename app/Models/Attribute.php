<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\AttributeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'section', 'system', 'key', 'label', 'data_type', 'multiple', 'sort', 'available_in_mail_templates'])]
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
     * Feste Felder, die es schon vor der Attribut-Verwaltung gab (Ralf,
     * 2026-09-10: "frei mischbar mit Zusatzfeldern") - bekommen jetzt
     * eigene Attribute-Zeilen (system=true) statt nur einer hartkodierten
     * Anzeigeliste, damit sie einen echten, pro Kunde änderbaren sort-Wert
     * tragen und sich frei mit den Zusatzfeldern mischen lassen. Nur die
     * Reihenfolge ist über die Verwaltungsseite änderbar, nicht
     * Bezeichnung/Löschen. Baujahr/Initiator/Übersetzung-Lokalisierung und
     * Modell/System sind bewusst NICHT hier drin - die sind auf Ralfs
     * Wunsch zu normalen Zusatzfeldern geworden (siehe attributes-
     * Migration/Datenmigration). Modell/System explizit deshalb, weil die
     * Caption je Kunde variiert ("bei Viega Modell, anderswo Typ") und das
     * Feld später ohnehin per PIM-Anbindung befüllt wird, nicht manuell.
     */
    public const SYSTEM_FIELDS = [
        self::SECTION_STAMMDATEN => [
            'title' => 'Bezeichnung',
            // Ralf, 2026-09-11: "Start und Ende müssen in einer Zeile
            // nebeneinander stehen" - direkt nach Bezeichnung einsortiert,
            // damit die beiden im 2-spaltigen Raster garantiert dieselbe
            // Zeile teilen (siehe detail.blade.php $isWideField/Zeilen-
            // Zähler - nur schmale Felder mit gerader Vorgänger-Anzahl
            // landen nebeneinander).
            'start_date' => 'Start',
            'end_date' => 'Ende',
            'project_type' => 'Projektkategorie/-art',
            'version' => 'Version',
            'status' => 'Status',
            // "Erstellungsstatus ist auch ein Stammdatum" - ursprünglich
            // (Vietto-Vorbild) bei Ablaufdaten eingeordnet, gehört
            // inhaltlich aber hierher (keine Ablauf-/Prozessinfo, sondern
            // eine feste Eigenschaft des Projekts).
            'creation_type' => 'Erstellungsstatus',
            'markets' => 'Markt',
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
            'changes_vs_previous_version' => 'Änderungen zur Vorversion',
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
}
