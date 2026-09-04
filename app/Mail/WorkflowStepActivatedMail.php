<?php

namespace App\Mail;

use App\Models\Person;
use App\Models\ProjectWorkflowStep;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkflowStepActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProjectWorkflowStep $projectWorkflowStep,
        public readonly ?Person $triggeredBy,
        public readonly ?string $personalMessage = null,
    ) {}

    public function build(): self
    {
        $project = $this->projectWorkflowStep->project;

        return $this
            ->subject(__('Vectory: Workflow-Schritt zugewiesen – Projekt :pn', ['pn' => $project->source_pn]))
            ->view('emails.workflow-step-activated')
            ->with([
                'project' => $project,
                'step' => $this->projectWorkflowStep->workflowStep,
                'triggeredBy' => $this->triggeredBy,
                'personalMessage' => $this->personalMessage,
            ]);
    }
}
