<?php

namespace App\Support;

use App\Models\ProjectNote;
use App\Models\Workflow;
use App\Models\WorkflowStep;

/**
 * Verfügbare Felder für Multichange (Ralf, 2026-09-13, nach Vietto-Analyse -
 * siehe Backlog-Memory für die vollständige Konzept-Herleitung). Bewusst
 * klein gehalten für die erste Version: einfache Set-Semantik auf feste
 * Projektfelder. Projektgruppen-Tag und Checkliste sind bewusst NICHT
 * enthalten (eigene Semantik/Diskussion, vertagt). Workflow-Zuweisung
 * (2026-09-14) kam als erstes Feld mit eigener Sonderlogik dazu (siehe
 * MultichangeController::buildPreview()/applyValue() - drei Fälle statt
 * einfachem Set), daher braucht available() jetzt den Mandanten (für die
 * Workflow-Auswahlliste), anders als vorher.
 */
class MultichangeFieldCatalog
{
    /**
     * @return list<array{key: string, label: string, type: string, required?: bool, options?: array<int, string>, hint?: string}>
     */
    public static function available(int $tenantId): array
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
            // Ralf, 2026-09-14: Zuweisung nach Vietto-Vorbild, aber ohne
            // dessen fest verdrahtete Fallback-Tabelle für den aktuellen
            // Schritt - Vectory hat dafür schon ein sauberes eigenes Muster
            // (siehe ProjectCopyController::store(), "Workflow mit
            // kopieren"): Schritt-Vorlagen werden kopiert, der Schritt mit
            // lifecycle_status=Geplant wird automatisch aktiviert (nur
            // is_current+started_at - Termine/WFS-Details sind bewusst ein
            // separater, späterer Schritt, siehe Ralf: "WFS machen wir in
            // einem separaten Step"; Beginn/Ende des Projekts bleiben daher
            // unangetastet). Drei Situationen je Projekt bei der Prüfung
            // (mit Ralf durchgesprochen), siehe
            // MultichangeController::buildPreview():
            // 1. Kein Workflow -> wird zugewiesen, 1. Schritt aktiviert.
            // 2. Hat GENAU diesen Workflow schon -> unverändert, kein Eingriff.
            // 3. Hat einen ANDEREN Workflow -> zwei mögliche Reaktionen je
            //    nach Häkchen "Andere Workflows überschreiben": a) unangetastet
            //    lassen (übersprungen) oder b) überschreiben - bisheriger
            //    aktueller Schritt wird deaktiviert, 1. Schritt des neuen
            //    Workflows aktiviert (Fortschritt geht verloren).
            [
                'key' => 'workflow_id',
                'label' => __('Workflow'),
                'type' => 'select',
                'required' => true,
                // Nur aktive (= nicht durch eine neuere Version ersetzte,
                // siehe Workflow::superseded_by_id) Workflows zur Auswahl -
                // gleicher Filter wie im normalen Projekt-Bearbeiten-Formular.
                'options' => Workflow::query()->where('tenant_id', $tenantId)->where('active', true)
                    ->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all(),
                // Ralf, 2026-09-14: Sie-Form (nicht "du") in der Oberfläche,
                // dazu als Liste statt Fließtext, damit die drei Fälle gut
                // sichtbar bleiben - hint darf deshalb hier ausnahmsweise
                // eine Liste von Zeilen statt eines einzelnen Strings sein
                // (siehe generische Darstellung in multichange-body.blade.php).
                'hint' => [
                    __('Projekte ohne Workflow bekommen ihn neu zugewiesen (1. Schritt "In Planung" wird automatisch aktiviert).'),
                    __('Projekte, die diesen Workflow bereits haben, bleiben unverändert.'),
                    __('Projekte mit einem ANDEREN Workflow werden übersprungen - außer Sie aktivieren unten "Andere Workflows überschreiben": dann wird dort ebenfalls neu zugewiesen und der bisherige Fortschritt geht verloren.'),
                ],
            ],
            ...self::workflowStepField($tenantId),
        ];
    }

    /**
     * Eigener Feld-Typ 'workflow_step' (2026-09-14, Ralf: "Zunächst muss
     * man einen WF auswählen, dann dort den entsprechenden WFS") - im
     * Gegensatz zu allen anderen Feldern hier braucht die Werteauswahl
     * ZWEI verschachtelte Schritte (erst Workflow, dann dessen Schritt),
     * deshalb eigene Formular-Darstellung in multichange-body.blade.php
     * statt der generischen select-Option-Liste. Übermittelt wird am Ende
     * trotzdem nur die eine WorkflowStep-ID als Wert - "options" bleibt
     * trotzdem als flache ID->Label-Liste vorhanden (Label inkl.
     * Workflow-Name, da Schritt-Titel wie "In Planung" workflow-übergreifend
     * mehrfach vorkommen können), fürs Validieren und für describeValue().
     *
     * Drei Situationen je Projekt bei der Prüfung (mit Ralf durchgesprochen,
     * siehe MultichangeController::buildPreview()):
     * 1. Hat denselben Workflow UND bereits den Ziel-Schritt aktuell ->
     *    unverändert, kein Eingriff.
     * 2. Hat denselben Workflow, aber einen ANDEREN Schritt aktuell -> wird
     *    geändert (Vorwärts/Rückwärts-Logik wie beim einzelnen
     *    "Aktivieren"-Button, siehe ProjectWorkflowStepController::activate()
     *    - pro Projekt einzeln berechnet, nicht global).
     * 3. Hat keinen oder einen ANDEREN Workflow -> übersprungen, mit Hinweis,
     *    dass zuerst der Workflow angepasst werden muss.
     *
     * @return list<array{key: string, label: string, type: string}>
     */
    private static function workflowStepField(int $tenantId): array
    {
        $workflows = Workflow::query()->where('tenant_id', $tenantId)->where('active', true)
            ->orderBy('sort')->orderBy('name')->get(['id', 'name']);

        $steps = WorkflowStep::query()->whereIn('workflow_id', $workflows->pluck('id'))->where('is_active', true)
            ->orderBy('sort')->get(['id', 'workflow_id', 'title', 'lifecycle_status', 'sort']);

        // Ralf, 2026-09-14: "Schritt X: ..." - sort ist genau die Nummer,
        // die auch im Workflow-Reiter der Projektdetails vor jedem Schritt
        // steht (1 In Planung, 2 Datenpflege..., usw.).
        $options = $steps->mapWithKeys(fn (WorkflowStep $step) => [
            $step->id => __('Schritt :nr: :title (:workflow)', [
                'nr' => $step->sort,
                'title' => $step->title,
                'workflow' => $workflows->firstWhere('id', $step->workflow_id)?->name,
            ]),
        ])->all();

        return [
            [
                'key' => 'workflow_step_id',
                'label' => __('Workflow-Schritt'),
                'type' => 'workflow_step',
                'required' => true,
                'options' => $options,
                // Für die Formular-Kaskade (Workflow wählen -> nur dessen
                // Schritte anzeigen), nicht für Validierung/Anzeige genutzt.
                'workflows' => $workflows->map(fn (Workflow $w) => ['id' => $w->id, 'name' => $w->name])->values()->all(),
                'steps' => $steps->map(fn (WorkflowStep $step) => ['id' => $step->id, 'workflow_id' => $step->workflow_id, 'title' => $step->title])->values()->all(),
                'hint' => [
                    __('Projekte mit demselben Workflow, die den Ziel-Schritt schon als aktuellen Schritt haben, bleiben unverändert.'),
                    __('Projekte mit demselben Workflow, aber einem anderen aktuellen Schritt, werden auf den Ziel-Schritt umgestellt.'),
                    __('Projekte ohne diesen Workflow werden übersprungen - der Workflow muss dafür zuerst per Multichange-Feld "Workflow" angepasst werden.'),
                    __('Es werden keine automatischen E-Mails an Zuständige verschickt - bei Bedarf bitte selbst informieren.'),
                ],
            ],
        ];
    }

    public static function find(int $tenantId, string $key): ?array
    {
        return collect(self::available($tenantId))->firstWhere('key', $key);
    }
}
