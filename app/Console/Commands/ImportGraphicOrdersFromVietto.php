<?php

namespace App\Console\Commands;

use App\Enums\GraphicOrderStatus;
use App\Models\GraphicOrder;
use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Illustrationsaufträge aus Vietto (grafikerstellung, rein
 * lesend) inkl. Beschreibung, Frist, Ersteller und zugewiesenem
 * Illustrator. valIllustratorFirmaID wird bewusst nicht übernommen (siehe
 * GraphicOrder-Model). Status-Katalog ist fest verdrahtet (siehe
 * App\Enums\GraphicOrderStatus) - Viettos valStatusID entspricht exakt
 * unseren Enum-Werten, kein Mapping/Import nötig.
 */
class ImportGraphicOrdersFromVietto extends Command
{
    protected $signature = 'graphic-orders:import-from-vietto';

    protected $description = 'Grafikaufträge aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $projectIds = Project::query()->where('tenant_id', $tenant->id)->pluck('id', 'source_pn');
        $personIds = Person::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $sanitizeDate = function (?string $value): ?string {
            if (! $value || ! preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $matches)) {
                return null;
            }

            return ((int) $matches[1]) < 1000 ? null : $value;
        };

        $orders = DB::connection('vietto')->table('grafikerstellung')->get();
        $count = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $projectId = $projectIds->get($order->strPN);
            $status = GraphicOrderStatus::tryFrom((int) $order->valStatusID);

            if (! $projectId || ! $status) {
                $skipped++;

                continue;
            }

            // "erledigt" ist in Vietto NICHT gleich Status "Fertig und abgelegt" -
            // maßgeblich ist allein, ob ein Erledigt-User gesetzt wurde.
            $doneAt = $order->valDoneUserID > 0 && $sanitizeDate($order->dtgDoneDate)
                ? $order->dtgDoneDate
                : null;

            GraphicOrder::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $order->valID],
                [
                    'project_id' => $projectId,
                    'graphic_order_status_id' => $status,
                    'image_count' => $order->intAnzBilder ?? 0,
                    'description' => $order->strBeschreibung ?: null,
                    'due_date' => $sanitizeDate($order->dtgFertigBis),
                    'initiated_by_person_id' => $personIds->get($order->valInitUserID),
                    'illustrator_person_id' => $personIds->get($order->valIllustratorID),
                    'done_at' => $doneAt,
                    'completed_by_person_id' => $doneAt ? $personIds->get($order->valDoneUserID) : null,
                ]
            );
            $count++;
        }

        $this->info("{$count} Grafikaufträge importiert".($skipped ? ", {$skipped} übersprungen (kein passendes Projekt/Status)." : '.'));

        return self::SUCCESS;
    }
}
