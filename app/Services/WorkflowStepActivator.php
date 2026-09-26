<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\GraphicOrderStatus;
use App\Mail\WorkflowStepActivatedMail;
use App\Models\Activity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

/**
 * Kern von "einen Workflow-Schritt aktivieren" - aus
 * ProjectWorkflowStepController::activate() herausgelöst (2026-09-26),
 * damit auch die Freigabe-Wege (interner Button ProjectWorkflowStep
 * Controller::toggleFreigabe() UND externer Mail-Klick ohne Login,
 * WorkflowStepFreigabeActionController) denselben Folge-WFS auf
 * identische Weise auslösen können - Ralf: "die müssen natürlich
 * identisch sein".
 */
class WorkflowStepActivator
{
    /**
     * @return array{open_graphic_orders_count: ?int}
     */
    public function activate(
        Project $project,
        ProjectWorkflowStep $target,
        ?Person $triggeredBy,
        bool $sendEmail = false,
        ?string $ccEmail = null,
        ?string $message = null,
    ): array {
        $target->loadMissing('workflowStep.functionGroups');

        $currentStep = $project->projectWorkflowSteps->firstWhere('is_current', true);

        if ($currentStep && $currentStep->id !== $target->id) {
            $movingForward = $target->sort > $currentStep->sort;

            $currentStep->update([
                'is_current' => false,
                'completed_at' => $movingForward ? now() : $currentStep->completed_at,
                'completed_by_person_id' => $movingForward ? $triggeredBy?->id : $currentStep->completed_by_person_id,
            ]);
        }

        $target->update([
            'is_current' => true,
            'started_at' => now(),
            'completed_at' => null,
            'completed_by_person_id' => null,
        ]);

        // Status automatisch aus der Kastenfarbe des neuen Schritts ableiten
        // (lifecycle_status 1=Geplant..4=Verworfen -> Status 0=Geplant..3=Verworfen).
        $project->update(['status' => $target->workflowStep->lifecycle_status - 1]);

        Activity::log($project, ActivityType::WorkflowStepActivated, __('Workflow-Schritt ":title" aktiviert.', ['title' => $target->workflowStep->title]));

        if ($sendEmail) {
            $recipientEmails = Task::recipientsFor($target)->pluck('email')->filter()->all();

            if (! empty($recipientEmails)) {
                $mail = Mail::to($recipientEmails);
                if ($ccEmail) {
                    $mail->cc($ccEmail);
                }
                $mail->send(new WorkflowStepActivatedMail($target, $triggeredBy, $message));
            }
        }

        // Weiche Warnung: offene Illustrationsaufträge bei Beenden/Verwerfen (lifecycle_status 3/4).
        $openGraphicOrdersCount = null;
        if (in_array($target->workflowStep->lifecycle_status, [3, 4], true)) {
            $openStatusValues = array_map(fn ($status) => $status->value, array_filter(GraphicOrderStatus::cases(), fn ($status) => $status->isOpen()));
            $count = $project->graphicOrders()->whereIn('graphic_order_status_id', $openStatusValues)->count();
            $openGraphicOrdersCount = $count > 0 ? $count : null;
        }

        return ['open_graphic_orders_count' => $openGraphicOrdersCount];
    }
}
