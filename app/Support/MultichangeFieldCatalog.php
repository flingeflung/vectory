<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\FunctionGroup;
use App\Models\Market;
use App\Models\Person;
use App\Models\ProjectTemplate;
use App\Models\ProjectNote;
use App\Models\ProjectTypeMain;
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
 *
 * WICHTIG für jede generische Attribut-Scan-Methode hier unten (pulldown-/
 * number-/boolean-/date-/textAttributeFields()): IMMER `where('system',
 * false)` mitgeben. Systemfelder (title, status, workflow_id, archived,
 * project_type, remarks, ...) existieren alle als echte Attribute-Zeilen
 * für Sortierung/Label, tragen dort aber ALLE `data_type = 'text'`
 * (Datenbank-Stichprobe 2026-09-15) - unabhängig davon, wie das Feld
 * TATSÄCHLICH angezeigt/gespeichert wird (Pulldown, echte Spalte, ...).
 * Ohne den Systemfeld-Ausschluss tauchen sie hier als kaputte Dubletten
 * auf, die ins Leere schreiben (attributes-JSON statt der echten Spalte/
 * Sonderlogik).
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
            // Ralf, 2026-09-14: "Projekttyp/-art, genau, das bitte
            // umsetzen" - schreibt project_type_sub_id, project_type_main_id
            // wird analog ProjectController::update() serverseitig daraus
            // abgeleitet (siehe MultichangeController::applyValue()). Flache
            // Liste statt optgroups wie im normalen Formular (system-fields/
            // project_type.blade.php) - Multichange kennt bisher keine
            // gruppierten Selects, "Kategorie: Art" im Label reicht als
            // Orientierung.
            [
                'key' => 'project_type_sub_id',
                'label' => __('Projektkategorie/-art'),
                'type' => 'select',
                'required' => true,
                // flatMap()/collapse() würde die int-Keys (Sub-IDs) über
                // array_merge() neu durchnummerieren (0,1,2,...) statt sie zu
                // erhalten - deshalb manuell per reduce() zusammengebaut.
                // Genau das hat den 500er beim Anwenden verursacht: Option
                // "Kurzanleitung" wurde als Wert 1 übermittelt statt der
                // echten ID 63, die dann als FK nicht existierte.
                'options' => ProjectTypeMain::query()->where('tenant_id', $tenantId)->where('active', true)
                    ->orderBy('sort')->with(['subs' => fn ($query) => $query->where('active', true)->orderBy('sort')])->get()
                    ->reduce(function (array $options, ProjectTypeMain $category) {
                        foreach ($category->subs as $sub) {
                            $options[$sub->id] = $category->name.': '.$sub->name;
                        }

                        return $options;
                    }, []),
            ],
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
            ...self::projectTemplateField($tenantId),
            ...self::workflowStepField($tenantId),
            ...self::marketsField($tenantId),
            ...self::projectPeopleField($tenantId),
            ...self::pulldownAttributeFields($tenantId),
            ...self::numberAttributeFields($tenantId),
            ...self::booleanAttributeFields($tenantId),
            ...self::dateAttributeFields($tenantId),
            ...self::textAttributeFields($tenantId),
        ];
    }

    /**
     * Pulldown-Zusatzfelder (Ralf, 2026-09-15, Konzept mit Ralf abgestimmt)
     * - jedes Zusatzfeld vom Typ Pulldown (egal welcher Bereich) bekommt
     * automatisch ein eigenes Multichange-Feld, genau wie "Initiator" es
     * für ein Text-Zusatzfeld schon vorher gab. 'key' bewusst der reine
     * Attribut-Key OHNE Präfix (wie bei 'initiator') - applyValue()/
     * valueUnchanged()/describeOldValue() lesen/schreiben damit direkt
     * unter demselben Schlüssel im attributes-JSON.
     *
     * Einfachauswahl-Pulldowns (type 'attribute_select') laufen über die
     * normale Set-Semantik (auch Leeren erlaubt, siehe validateInput() -
     * anders als die festen Pulldown-Felder oben, die immer einen Wert
     * verlangen). Mehrfachauswahl-Pulldowns (type 'attribute_select_multiple')
     * haben eigene Semantik (Ergänzen ODER Überschreiben, Nutzer wählt im
     * Formular - siehe MultichangeController::buildPreview()/applyValue()).
     *
     * 'typspezifisch_sub_ids' (nur bei Bereich "Typspezifisch" gesetzt):
     * Projekte, deren Projektart NICHT in dieser Liste steht, kennen das
     * Feld gar nicht und werden übersprungen (siehe buildPreview()) - anders
     * als bei den festen Feldern oben gibt es das hier zum ersten Mal, weil
     * ein Zusatzfeld je nach Bereich eben NICHT für jedes Projekt existiert.
     *
     * @return list<array{key: string, label: string, type: string, storage: string, options: array<string, string>, typspezifisch_sub_ids: ?list<int>}>
     */
    private static function pulldownAttributeFields(int $tenantId): array
    {
        return Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            ->where('data_type', Attribute::DATA_TYPE_SELECT)
            ->with('options', 'projectTypeSubs')
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => $attribute->key,
                'label' => $attribute->label,
                'type' => $attribute->multiple ? 'attribute_select_multiple' : 'attribute_select',
                'storage' => 'attribute',
                'options' => $attribute->options->pluck('label', 'value')->all(),
                'typspezifisch_sub_ids' => $attribute->section === Attribute::SECTION_TYPSPEZIFISCH
                    ? $attribute->projectTypeSubs->pluck('id')->all()
                    : null,
            ])
            ->values()
            ->all();
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

    /**
     * Projektschablone (Ralf, 2026-09-19: "Projektschablonen per MC zuweisen") -
     * einfache Set-Semantik auf projects.project_template_id, reiner Verweis
     * (keine Werteübernahme ins Projekt). Nur aktive Schablonen, in der im Admin
     * frei festgelegten Reihenfolge. Fehlt jede aktive Schablone, gibt es das
     * Feld nicht.
     *
     * @return list<array<string, mixed>>
     */
    private static function projectTemplateField(int $tenantId): array
    {
        $templates = ProjectTemplate::query()->where('tenant_id', $tenantId)->where('active', true)
            ->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all();

        if ($templates === []) {
            return [];
        }

        return [[
            'key' => 'project_template_id',
            'label' => __('Projektschablone'),
            'type' => 'select',
            'required' => true,
            'options' => $templates,
            'hint' => __('Die Schablone wird den Projekten nur zugeordnet, es werden keine Werte aus der Schablone in die Projekte übernommen.'),
        ]];
    }

    /**
     * Märkte (Ralf, 2026-09-19: "Märkte per MC zuweisen") - echte n:m-Zuordnung
     * (project_market), deshalb eigener Feldtyp mit drei Modi statt einfacher
     * Set-Semantik: Hinzufügen (nur ergänzen, nichts geht verloren), Entfernen,
     * Überschreiben (komplette Auswahl ersetzen). Werte laufen als Liste von
     * Markt-IDs (wie die Mehrfachauswahl-Pulldowns), Modus über multi_mode.
     * 'short_options' = kompaktes Format der Projektdetails (z.B. "DEde
     * Deutschland") für Alter/Neuer Wert in der Vorschau-Tabelle, 'options'
     * = ausführliche Bezeichnung für die Auswahlliste.
     *
     * @return list<array<string, mixed>>
     */
    private static function marketsField(int $tenantId): array
    {
        $markets = Market::query()->where('tenant_id', $tenantId)->orderBy('sort')->get();

        if ($markets->isEmpty()) {
            return [];
        }

        return [[
            'key' => 'markets',
            'label' => __('Märkte'),
            'type' => 'markets',
            'options' => $markets->mapWithKeys(fn (Market $market) => [$market->id => $market->label()])->all(),
            'short_options' => $markets->mapWithKeys(fn (Market $market) => [$market->id => $market->shortLabel()])->all(),
            'hint' => [
                __('Hinzufügen: Die gewählten Märkte werden bei allen Projekten ergänzt, bestehende bleiben erhalten.'),
                __('Entfernen: Die gewählten Märkte werden bei allen Projekten entfernt.'),
                __('Überschreiben: Bei allen Projekten werden die gespeicherten Märkte entfernt und stattdessen die gewählten gespeichert.'),
            ],
        ]];
    }

    /**
     * Eigener Feld-Typ 'project_people' (Ralf, 2026-09-18: "Wir brauchen
     * Projektbeteiligte Personen bei MC") - Ziel ist bewusst die
     * FUNKTIONSGRUPPE, nicht das Projekt als Ganzes (Ralf: "Ich würde dann
     * tatsächlich die Fktgrp als Ziel sehen"). Zwei unabhängige Werte
     * (Funktionsgruppe + Person, kein WF-Schritt-artiger Kaskaden-Zwang -
     * jede Person ist frei jeder Fktgrp zuordenbar) plus eigene
     * Hinzufügen/Entfernen-Aktion (Ralf: "mach Entfernen und Hinzufügen
     * separat, dann kann der Benutzer selber wählen") - wiederverwendet
     * dafür denselben multi_mode-Mechanismus wie attribute_select_multiple
     * (dort 'add'/'overwrite', hier 'add'/'remove'), siehe
     * MultichangeController::buildPreview()/applyValue().
     *
     * is_primary bleibt beim Hinzufügen IMMER unangetastet (Ralf, 2026-09-18:
     * "das ist eine nicht ganz so wichtige Sache") - eine neu hinzugefügte
     * Person wird nie automatisch hauptverantwortlich.
     *
     * @return list<array{key: string, label: string, type: string, options: array<int, string>, function_groups: array<int, string>}>
     */
    private static function projectPeopleField(int $tenantId): array
    {
        // Ralf-Korrektur, 2026-09-18: "Das darf doch nur dort passieren, wo
        // die Person auch Mitglied in der gewählten Funktionsgruppe ist" -
        // Personen-Auswahl muss sich also nach der gewählten Funktionsgruppe
        // richten (Kaskade wie bei 'workflow_step', nur clientseitig gefiltert
        // statt nachgeladen - Mitgliederzahl je Fktgrp ist überschaubar).
        // withoutGlobalScope('tenant') auf members(): auch per Kundenzugriff
        // freigegebene DL-Mitarbeiter zählen als Mitglied, gleiches Muster
        // wie FunctionGroupController::index() (Ralf dort: "Ich bin in der
        // Maschinen AG. Ich kann hier gar keine TR der Fktgrp zuweisen").
        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->where('active', true)
            ->with(['members' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->orderBy('name')->get(['id', 'name']);

        // withoutGlobalScope + visibleInTenant: auch per Kundenzugriff
        // freigegebene DL-Mitarbeiter zur Auswahl, gleiches Bedürfnis wie
        // FunctionGroupController::index() (Ralf dort: "Ich bin in der
        // Maschinen AG. Ich kann hier gar keine TR der Fktgrp zuweisen").
        $people = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'active']);

        return [[
            'key' => 'project_people',
            'label' => __('Projektbeteiligte Personen'),
            'type' => 'project_people',
            'required' => true,
            // Für Validierung + describeValue() - volle Personen-Liste
            // (Mitgliedschafts-Einschränkung läuft separat über
            // 'function_group_members', siehe MultichangeController::
            // validateFunctionGroupId()) - ein Wert ohne Mitgliedschaft ist
            // damit trotzdem noch ein "echter" Personen-Wert, nur fachlich
            // unzulässig für diese Fktgrp.
            'options' => $people->mapWithKeys(fn (Person $p) => [
                $p->id => $p->fullName().(! $p->active ? ' [i]' : ''),
            ])->all(),
            'function_groups' => $functionGroups->mapWithKeys(fn (FunctionGroup $fg) => [$fg->id => $fg->name])->all(),
            // Für die clientseitige Personen-Kaskade in der View (Fktgrp
            // wählen -> nur deren Mitglieder zur Auswahl) UND für die
            // serverseitige Mitgliedschafts-Prüfung im Controller.
            'function_group_members' => $functionGroups->mapWithKeys(fn (FunctionGroup $fg) => [
                $fg->id => $fg->members->pluck('id')->all(),
            ])->all(),
            'hint' => [
                __('Hinzufügen: Die Person wird der gewählten Funktionsgruppe bei allen Projekten zugeordnet.'),
                __('Entfernen: Die Person wird aus der gewählten Funktionsgruppe bei allen Projekten entfernt.'),
                __('Nur Personen, die Mitglied der gewählten Funktionsgruppe sind, stehen zur Auswahl.'),
            ],
        ]];
    }

    /**
     * Zahl-Zusatzfelder (Ralf, 2026-09-15: "sinngemäß" wie die Pulldown-
     * Zusatzfelder oben) - anders als Pulldown gibt es hier keine
     * Mehrfachauswahl, deshalb reicht die normale Set-Semantik (kein eigener
     * type-Zweig in buildPreview()/applyValue() nötig, läuft über denselben
     * generischen Pfad wie z.B. "Initiator"). Mindest-/Höchstwert +
     * Dezimalstellen (Attribute::number_min/.../number_decimals) werden hier
     * nur durchgereicht - Validierung baut MultichangeController::
     * validateInput() daraus dieselben Regeln wie ProjectController::
     * attributeValidationRules() für das normale Projektformular.
     *
     * @return list<array{key: string, label: string, type: string, storage: string, number_min: ?float, number_max: ?float, number_decimals: ?int, typspezifisch_sub_ids: ?list<int>}>
     */
    private static function numberAttributeFields(int $tenantId): array
    {
        return Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            ->where('data_type', Attribute::DATA_TYPE_NUMBER)
            ->with('projectTypeSubs')
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => $attribute->key,
                'label' => $attribute->label,
                'type' => 'attribute_number',
                'storage' => 'attribute',
                'number_min' => $attribute->number_min !== null ? (float) $attribute->number_min : null,
                'number_max' => $attribute->number_max !== null ? (float) $attribute->number_max : null,
                'number_decimals' => $attribute->number_decimals,
                'typspezifisch_sub_ids' => $attribute->section === Attribute::SECTION_TYPSPEZIFISCH
                    ? $attribute->projectTypeSubs->pluck('id')->all()
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Ja/Nein-Zusatzfelder - anders als Pulldown/Zahl/Datum gibt es am
     * einzelnen Projekt kein "leer" (die Checkbox liefert immer true/false,
     * siehe ProjectController::update()), deshalb hier bewusst "required"
     * statt nullable (siehe MultichangeController::validateInput()) - ein
     * "Leeren" ergäbe kein sinnvolles Ziel.
     *
     * @return list<array{key: string, label: string, type: string, storage: string, options: array<string, string>, typspezifisch_sub_ids: ?list<int>}>
     */
    private static function booleanAttributeFields(int $tenantId): array
    {
        return Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            ->where('data_type', Attribute::DATA_TYPE_BOOLEAN)
            ->with('projectTypeSubs')
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => $attribute->key,
                'label' => $attribute->label,
                'type' => 'attribute_boolean',
                'storage' => 'attribute',
                'options' => ['1' => __('Ja'), '0' => __('Nein')],
                'typspezifisch_sub_ids' => $attribute->section === Attribute::SECTION_TYPSPEZIFISCH
                    ? $attribute->projectTypeSubs->pluck('id')->all()
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Datum-Zusatzfelder - "sinngemäß" wie Start/Ende/Publikationsdatum
     * oben (gleicher type 'date' bei Validierung/Formular, siehe
     * MultichangeController), nur eben mit eigenem type-Namen, damit
     * buildPreview() sie zusätzlich durch die Typspezifisch-Übersprungen-
     * Prüfung schicken kann (die festen Datumsfelder brauchen das nicht,
     * die gelten immer für jedes Projekt).
     *
     * @return list<array{key: string, label: string, type: string, storage: string, typspezifisch_sub_ids: ?list<int>}>
     */
    private static function dateAttributeFields(int $tenantId): array
    {
        return Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            ->where('data_type', Attribute::DATA_TYPE_DATE)
            ->with('projectTypeSubs')
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => $attribute->key,
                'label' => $attribute->label,
                'type' => 'attribute_date',
                'storage' => 'attribute',
                'typspezifisch_sub_ids' => $attribute->section === Attribute::SECTION_TYPSPEZIFISCH
                    ? $attribute->projectTypeSubs->pluck('id')->all()
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Text-/Textarea-Zusatzfelder (Ralf, 2026-09-15) - eigene type-Werte
     * ('attribute_text'/'attribute_textarea') statt der festen 'text'/
     * 'textarea', damit buildPreview() sie zusätzlich durch die
     * Typspezifisch-Übersprungen-Prüfung schicken kann (die feste
     * "Bezeichnung" oben braucht das nicht). max_length kommt vom Attribut
     * selbst (Attribute::max_length) und wird in validateInput() genauso
     * ausgewertet wie am einzelnen Projekt (attribute-field.blade.php).
     *
     * @return list<array{key: string, label: string, type: string, storage: string, max_length: ?int, typspezifisch_sub_ids: ?list<int>}>
     */
    private static function textAttributeFields(int $tenantId): array
    {
        return Attribute::query()
            ->where('tenant_id', $tenantId)
            ->where('system', false)
            // "Initiator" hat oben bereits sein eigenes, historisch
            // gewachsenes Eintrag mit eigenen Kommentaren - ohne diesen
            // Ausschluss würde es hier ein zweites Mal auftauchen.
            ->where('key', '!=', 'initiator')
            ->whereIn('data_type', [Attribute::DATA_TYPE_TEXT, Attribute::DATA_TYPE_TEXTAREA])
            ->with('projectTypeSubs')
            ->orderBy('sort')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'key' => $attribute->key,
                'label' => $attribute->label,
                'type' => $attribute->data_type === Attribute::DATA_TYPE_TEXTAREA ? 'attribute_textarea' : 'attribute_text',
                'storage' => 'attribute',
                'max_length' => $attribute->max_length,
                'typspezifisch_sub_ids' => $attribute->section === Attribute::SECTION_TYPSPEZIFISCH
                    ? $attribute->projectTypeSubs->pluck('id')->all()
                    : null,
            ])
            ->values()
            ->all();
    }

    public static function find(int $tenantId, string $key): ?array
    {
        return collect(self::available($tenantId))->firstWhere('key', $key);
    }
}
