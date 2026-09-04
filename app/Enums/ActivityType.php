<?php

namespace App\Enums;

/**
 * Katalog aller Vorgangs-Typen, die im Vorgänge-Log (App\Models\Activity)
 * auftreten können. Neuer Typ nötig? Hier einen Case ergänzen, Label unten
 * eintragen, dann an der auslösenden Stelle Activity::log() aufrufen.
 *
 * Bewusst als PHP-Enum statt DB-Katalogtabelle: die Typen sind vom Code
 * fest vorgegeben (nicht mandantenspezifisch änderbar) - anders als z.B.
 * die Attribut-Farben pro Projektart, die echt in der DB stehen müssen.
 *
 * Ursprung/Vorbild: Viettos vorgaenge-Tabelle hat KEINE eigene Typ-Spalte,
 * der "Typ" muss dort per Text-Mustererkennung auf txtVorgang geraten
 * werden - das ist die Vietto-Analyse, aus der dieser Katalog entstanden
 * ist (siehe Konversation vom 2026-09-02, ~30 gefundene Trigger-Stellen).
 * Hier absichtlich robuster gelöst: der Typ wird beim Anlegen direkt vom
 * auslösenden Code mitgegeben, kein Rätselraten im Nachhinein nötig.
 */
enum ActivityType: string
{
    case WorkflowAssigned = 'workflow_assigned';
    case WorkflowUnassigned = 'workflow_unassigned';
    case WorkflowStepActivated = 'workflow_step_activated';
    case GraphicOrderStatusChanged = 'graphic_order_status_changed';

    public function label(): string
    {
        return match ($this) {
            self::WorkflowAssigned => __('Workflow zugewiesen'),
            self::WorkflowUnassigned => __('Workflow entfernt'),
            self::WorkflowStepActivated => __('Workflow-Schritt aktiviert'),
            self::GraphicOrderStatusChanged => __('Illustrationsauftrag-Status geändert'),
        };
    }
}
