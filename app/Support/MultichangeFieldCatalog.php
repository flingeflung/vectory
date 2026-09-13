<?php

namespace App\Support;

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
            ['key' => 'remarks', 'label' => __('Bemerkungen'), 'type' => 'textarea'],
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
