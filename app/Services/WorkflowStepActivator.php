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
use App\Models\Tenant;
use App\Models\WorkflowStepFreigabeRequest;
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
        bool $fallbackToTenantEmail = false,
    ): array {
        $target->loadMissing('workflowStep.functionGroups');

        // Jede (Neu-)Aktivierung überholt noch offene Freigabe-Mails dieses Schritts.
        WorkflowStepFreigabeRequest::closePendingFor($target->id, WorkflowStepFreigabeRequest::STATUS_ERSETZT);

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
            // Jede Auslösung eines Freigabe-Schritts ist eine neue Runde: eine Freigabe aus einer
            // früheren Runde darf nicht hängen bleiben, sonst wäre der Schritt sofort "erteilt" und
            // der Mail-Link der neuen Runde tot (gefunden im Test 2026-09-27).
            ...($target->workflowStep->isFreigabeStep() ? ['milestone_done_at' => null] : []),
        ]);

        // Status automatisch aus der Kastenfarbe des neuen Schritts ableiten
        // (lifecycle_status 1=Geplant..4=Verworfen -> Status 0=Geplant..3=Verworfen).
        $project->update(['status' => $target->workflowStep->lifecycle_status - 1]);

        Activity::log($project, ActivityType::WorkflowStepActivated, __('Workflow-Schritt ":title" aktiviert.', ['title' => $target->workflowStep->title]));

        if ($sendEmail) {
            $recipientEmails = Task::recipientsFor($target)->pluck('email')->filter()->all();

            // Freigabe-Folge-WFS (Ralf, 2026-09-26): hat er selbst niemanden
            // mit Mailadresse, geht die Info an die Kunden-Info-Adresse.
            if (empty($recipientEmails) && $fallbackToTenantEmail) {
                $tenantEmail = Tenant::query()->where('id', $project->tenant_id)->value('notification_email');
                $recipientEmails = $tenantEmail ? [$tenantEmail] : [];
            }

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

    /**
     * "Freigabe erteilen" mit allen Folgen - für den internen Button
     * (toggleFreigabe) UND den externen Mail-Link identisch (Ralf,
     * 2026-09-26): milestone_done_at setzen, Vorgang loggen, konfigurierten
     * Folge-WFS auslösen (inkl. WFS-Mail an dessen Zuständige).
     *
     * @param  string|null  $via  Zusatz fürs Vorgänge-Log, z.B. "per E-Mail-Link"
     * @return ?string Titel des ausgelösten Folge-WFS (null: keiner konfiguriert)
     */
    public function grantFreigabe(Project $project, ProjectWorkflowStep $pws, ?Person $by, ?string $via = null): ?string
    {
        $title = $pws->workflowStep->title;
        $pws->update(['milestone_done_at' => now()]);

        // Per Button erteilt: eine noch offene Mail-Anfrage ist damit erledigt.
        WorkflowStepFreigabeRequest::closePendingFor($pws->id, WorkflowStepFreigabeRequest::STATUS_FREIGEGEBEN);

        Activity::log($project, ActivityType::WorkflowStepActivated, $via
            ? __('Freigabe für ":title" erteilt (:via).', ['title' => $title, 'via' => $via])
            : __('Freigabe für ":title" erteilt.', ['title' => $title]));

        $afterStepId = $pws->workflowStep->after_freigabe_workflow_step_id;
        $next = $afterStepId ? $project->projectWorkflowSteps->firstWhere('workflow_step_id', $afterStepId) : null;
        if (! $next) {
            return null;
        }

        $this->activate(
            $project,
            $next,
            $by,
            sendEmail: true,
            message: __('Automatisch ausgelöst durch Freigabe von ":title".', ['title' => $title]),
            fallbackToTenantEmail: true,
        );

        return $next->workflowStep->title;
    }

    /**
     * Der WFS unmittelbar vor $pws in der Reihenfolge des Projekts - Ziel
     * der "Korrekturen einarbeiten"-Schleife.
     */
    public function previousStep(Project $project, ProjectWorkflowStep $pws): ?ProjectWorkflowStep
    {
        // Nur Schritte desselben (aktiven) Workflows: nach einem Workflow-Wechsel trägt das
        // Projekt die Schritte des alten Workflows weiter mit (die Projektansicht blendet sie
        // aus) - die dürfen hier nicht als "vorheriger Schritt" auftauchen.
        $workflowId = $pws->workflowStep->workflow_id;

        return $project->projectWorkflowSteps->loadMissing('workflowStep')
            ->filter(fn (ProjectWorkflowStep $step) => $step->workflowStep
                && $step->workflowStep->workflow_id === $workflowId
                && $step->workflowStep->is_active
                && $step->sort < $pws->sort)
            ->sortByDesc('sort')
            ->first();
    }
}
