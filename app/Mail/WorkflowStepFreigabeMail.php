<?php

namespace App\Mail;

use App\Models\WorkflowStepFreigabeRequest;
use App\Services\ProjectDirectoryLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Freigabe-Mail eines WFS mit zwei signierten, zeitlich begrenzten Links
 * (Freigabe / Korrekturen einarbeiten) - für Empfänger ohne Vectory-Login.
 * Festes Mail-Gerüst wie WorkflowStepActivatedMail, bewusst OHNE Anbindung
 * an die MailTemplates (Ralf, 2026-09-26).
 */
class WorkflowStepFreigabeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Gültigkeit der Links in Tagen (Vorschlag 30, von Ralf noch nicht bestätigt). */
    public const LINK_VALIDITY_DAYS = 30;

    public function __construct(
        public readonly WorkflowStepFreigabeRequest $freigabeRequest,
        public readonly ?string $personalMessage = null,
    ) {}

    public function build(): self
    {
        $request = $this->freigabeRequest;
        $pws = $request->projectWorkflowStep;
        $project = $request->project;
        $step = $pws->workflowStep;
        $locator = app(ProjectDirectoryLocator::class);
        $expires = now()->addDays(self::LINK_VALIDITY_DAYS);

        $projectPath = $locator->arbeitsverzeichnisProjectPath($project);
        $sourceAbsolute = $projectPath && $request->source_path ? $locator->resolveRelative($projectPath, $request->source_path) : null;
        $attachPdf = $sourceAbsolute && is_file($sourceAbsolute) && strtolower(pathinfo($sourceAbsolute, PATHINFO_EXTENSION)) === 'pdf';

        if ($attachPdf) {
            $this->attach($sourceAbsolute, ['as' => basename($sourceAbsolute), 'mime' => 'application/pdf']);
        }

        return $this
            ->subject(__('Vectory: Freigabe erbeten – Projekt :pn', ['pn' => $project->source_pn]))
            ->view('emails.workflow-step-freigabe')
            ->with([
                'project' => $project,
                'step' => $step,
                'triggeredBy' => $request->triggeredBy,
                'personalMessage' => $this->personalMessage,
                'freigabeUrl' => URL::temporarySignedRoute('freigabe.confirm', $expires, ['freigabeRequest' => $request->id]),
                'korrekturUrl' => URL::temporarySignedRoute('freigabe.korrektur', $expires, ['freigabeRequest' => $request->id]),
                'validityDays' => self::LINK_VALIDITY_DAYS,
                'attachedFileName' => $attachPdf ? basename($sourceAbsolute) : null,
                'sourcePathText' => ! $attachPdf ? $sourceAbsolute : null,
                'afterStepTitle' => $step->afterFreigabeStep?->title,
                'previousStepTitle' => app(\App\Services\WorkflowStepActivator::class)->previousStep($project, $pws)?->workflowStep->title,
            ]);
    }
}
