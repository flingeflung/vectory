<?php

namespace App\Observers;

use App\Models\GraphicOrder;
use App\Models\Task;

/**
 * Sobald ein Illustrator einem Auftrag zugewiesen ist (Selbstzuweisung aus
 * dem Illustratoren-Pool oder manuell), soll er "schwupps" Projektbeteiligter
 * sein und den Auftrag als Aufgabe in seiner Aufgabenliste haben - siehe
 * Task::syncIllustrationTaskForOrder().
 */
class GraphicOrderObserver
{
    public function saved(GraphicOrder $graphicOrder): void
    {
        if ($graphicOrder->wasChanged(['illustrator_person_id', 'graphic_order_status_id'])) {
            Task::syncIllustrationTaskForOrder($graphicOrder);
        }
    }
}
