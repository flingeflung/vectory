<?php

namespace App\Support;

use App\Models\ProjectNote;

/**
 * Verfügbare Felder für Multichange (Ralf, 2026-09-13, nach Vietto-Analyse -
 * siehe Backlog-Memory für die vollständige Konzept-Herleitung). Bewusst
 * klein gehalten für die erste Version: einfache Set-Semantik auf feste
 * Projektfelder. Workflow, Projektgruppen-Tag und Checkliste sind bewusst
 * NICHT enthalten (eigene Semantik/Diskussion, vertagt).
 */
class MultichangeFieldCatalog
{
    /**
     * @return list<array{key: string, label: string, type: string, required?: bool, options?: array<int, string>, hint?: string}>
     */
    public static function available(): array
    {
        return [
            ['key' => 'title', 'label' => __('Bezeichnung'), 'type' => 'text', 'required' => true],
            // Ralf-Bug-Report, 2026-09-13: "Der Initiator wird nicht
            // geändert" - Initiator ist (anders als die übrigen Felder
            // hier) KEINE echte projects-Spalte mehr, sondern ein ganz
            // normales, pro Kunde löschbares Zusatzfeld (siehe
            // Attribute::SYSTEM_FIELDS-Docblock: "auf Ralfs Wunsch zu
            // normalen Zusatzfeldern geworden"), der Wert lebt im
            // attributes-JSON. 'storage' => 'attribute' steuert in
            // MultichangeController, dass dort statt der Spalte
            // geschrieben wird.
            ['key' => 'initiator', 'label' => __('Initiator'), 'type' => 'text', 'storage' => 'attribute'],
            // Ralf-Bug-Report, 2026-09-13: "Bemerkungen hat keine neue
            // Bemerkung erzeugt" - "Bemerkungen" ist seit 2026-09-12 KEIN
            // einzelnes Feld mehr, sondern eine Liste einzelner, mit Autor+
            // Zeitpunkt versehener Einträge (project_notes, siehe system-
            // fields/remarks.blade.php) - die alte projects.remarks-Spalte
            // wird von der Oberfläche gar nicht mehr gelesen. Set-Semantik
            // (Wert überschreiben) passt hier konzeptionell nicht mehr -
            // Multichange legt stattdessen bei jedem Projekt einen NEUEN
            // Eintrag an (Anhängen, nicht Überschreiben), analog Viettos
            // eigenem saveattr5 ("Bemerkungen/Änderungen" war dort schon
            // immer ein reines Anhängen, nie ein Set).
            [
                'key' => 'remarks',
                'label' => __('Bemerkungen'),
                'type' => 'textarea',
                'required' => true,
                'storage' => 'note',
                'note_type' => ProjectNote::TYPE_REMARK,
                'note_label' => __('Bemerkung'),
                'hint' => __('Fügt bei jedem Projekt der Gruppe einen neuen Bemerkungen-Eintrag hinzu - bestehende Bemerkungen bleiben unverändert erhalten.'),
            ],
            // Ralf, 2026-09-14: "Änderungsprotokoll bei Multichange, analog
            // zu Bemerkungen" - gleiche Anhängen-Semantik, nur anderer
            // ProjectNote::TYPE (siehe system-fields/change_log.blade.php).
            [
                'key' => 'change_log',
                'label' => __('Änderungsprotokoll'),
                'type' => 'textarea',
                'required' => true,
                'storage' => 'note',
                'note_type' => ProjectNote::TYPE_CHANGE,
                'note_label' => __('Änderungsprotokoll-Eintrag'),
                'hint' => __('Fügt bei jedem Projekt der Gruppe einen neuen Änderungsprotokoll-Eintrag hinzu - bestehende Einträge bleiben unverändert erhalten.'),
            ],
            ['key' => 'start_date', 'label' => __('Start'), 'type' => 'date'],
            ['key' => 'end_date', 'label' => __('Ende'), 'type' => 'date'],
            ['key' => 'publication_date', 'label' => __('Publikationsdatum'), 'type' => 'date'],
            // Ralf, 2026-09-13: nur relevant für Projekte OHNE aktuellen
            // Workflow-Schritt - bei laufendem Workflow bestimmt der
            // WFS-Schritt automatisch den Status (system-fields/status.blade.php),
            // ein direktes Setzen würde das unterlaufen. Betroffene Projekte
            // werden übersprungen (siehe MultichangeController::buildPreview()),
            // nicht stillschweigend ignoriert.
            [
                'key' => 'status',
                'label' => __('Bearbeitungsstatus'),
                'type' => 'select',
                'options' => [0 => __('Geplant'), 1 => __('In Bearbeitung'), 2 => __('Beendet'), 3 => __('Verworfen')],
                'hint' => __('Projekte mit aktuellem Workflow-Schritt werden übersprungen - dort bestimmt der Workflow-Schritt automatisch den Status.'),
            ],
            ['key' => 'creation_type', 'label' => __('Erstellungsstatus'), 'type' => 'select', 'options' => [1 => __('Neuerstellung'), 2 => __('Änderung')]],
        ];
    }

    public static function find(string $key): ?array
    {
        return collect(self::available())->firstWhere('key', $key);
    }
}
