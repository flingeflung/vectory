<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\FunctionGroup;
use App\Models\MailTimer;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowMailTimer;
use App\Models\WorkflowMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Mail-Timer (Ralf, 2026-10-09; Vietto-Vorbild admin_timermail.php): zeitgesteuerte Erinnerungsmails.
 * - Vorlagen am Workflow werden für jedes Projekt kopiert (syncFromWorkflow).
 * - Das Sendedatum folgt dem Bezugstermin (refreshSendDates, nach jeder Terminrechnung).
 * - Gesendet wird einmal täglich (sendDue): nur wenn das Projekt zum Sendetag noch im Schritt steht (Haken "nur senden, wenn …"),
 *   an die Personen der gewählten Funktionsgruppen im Projekt. Wartet das Projekt noch auf den Schritt, bleibt der Timer offen;
 *   hat es ihn schon verlassen, wird er übersprungen.
 */
class MailTimerService
{
    /** Vorlagen des Workflows als Timer ans Projekt kopieren (ohne Doppelung); Timer ohne Vorlage-Bezug bleiben unberührt. */
    public function syncFromWorkflow(Project $project): void
    {
        if (! $project->workflow_id) {
            return;
        }

        $templates = WorkflowMailTimer::query()->withoutGlobalScopes()->where('workflow_id', $project->workflow_id)->get();
        $existing = MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->whereNotNull('workflow_mail_timer_id')->pluck('workflow_mail_timer_id')->all();

        foreach ($templates as $template) {
            if (in_array($template->id, $existing, true)) {
                continue;
            }

            $milestoneId = null;
            if ($template->reference_type === MailTimer::REFERENCE_MILESTONE) {
                $milestoneId = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_milestone_id', $template->reference_milestone_id)->value('id');
                if ($milestoneId === null) {
                    continue;   // Meilenstein fehlt im Projekt (noch): beim nächsten Abgleich erneut versuchen
                }
            }

            MailTimer::query()->withoutGlobalScopes()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'workflow_mail_timer_id' => $template->id,
                'workflow_step_id' => $template->workflow_step_id,
                'mail_template_id' => $template->mail_template_id,
                'reference_type' => $template->reference_type,
                'reference_milestone_id' => $milestoneId,
                'reference_step_id' => $template->reference_step_id,
                'offset_days' => $template->offset_days,
                'only_if_in_step' => $template->only_if_in_step,
                'function_group_ids' => $template->function_group_ids,
            ]);
        }

        $this->refreshSendDates($project);
    }

    /** Sendedatum aller offenen Timer des Projekts neu berechnen. */
    public function refreshSendDates(Project $project): void
    {
        $timers = MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->whereNull('sent_at')->whereNull('skipped_at')->get();
        foreach ($timers as $timer) {
            $date = $this->sendDateFor($timer);
            if (($timer->send_date?->toDateString()) !== $date?->toDateString()) {
                $timer->forceFill(['send_date' => $date])->saveQuietly();
            }
        }
    }

    public function sendDateFor(MailTimer $timer): ?CarbonImmutable
    {
        $base = match ($timer->reference_type) {
            MailTimer::REFERENCE_FIXED => $timer->fixed_date,
            MailTimer::REFERENCE_MILESTONE => ProjectMilestone::query()->withoutGlobalScopes()->whereKey($timer->reference_milestone_id)->value('date'),
            MailTimer::REFERENCE_PHASE_END => ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $timer->project_id)->where('workflow_step_id', $timer->reference_step_id)->value('due_date'),
            default => null,
        };
        if ($base === null) {
            return null;
        }

        $date = CarbonImmutable::parse($base);

        return $timer->reference_type === MailTimer::REFERENCE_FIXED ? $date : $date->addDays((int) $timer->offset_days);
    }

    /**
     * Fällige Timer verarbeiten (täglich per Zeitplan).
     *
     * @return array{sent: int, skipped: int, failed: int, waiting: int}
     */
    public function sendDue(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'waiting' => 0];

        $due = MailTimer::query()->withoutGlobalScopes()->whereNull('sent_at')->whereNull('skipped_at')->whereNotNull('send_date')->whereDate('send_date', '<=', $today->toDateString())->get();

        foreach ($due as $timer) {
            $project = Project::query()->withoutGlobalScopes()->find($timer->project_id);
            // Projekt beendet/verworfen oder archiviert: Erinnerung ist überflüssig
            if (! $project || $project->archived || in_array((int) $project->status, [2, 3], true)) {
                $timer->forceFill(['skipped_at' => now(), 'last_error' => null])->saveQuietly();
                $result['skipped']++;

                continue;
            }

            if ($timer->only_if_in_step && $timer->workflow_step_id) {
                $state = $this->stepState($project, (int) $timer->workflow_step_id);
                if ($state === 'waiting') {
                    $result['waiting']++;

                    continue;
                }
                if ($state === 'left') {
                    $timer->forceFill(['skipped_at' => now(), 'last_error' => null])->saveQuietly();
                    $result['skipped']++;

                    continue;
                }
            }

            $error = $this->send($timer, $project);
            if ($error === null) {
                $result['sent']++;
            } else {
                $timer->forceFill(['last_error' => $error])->saveQuietly();
                $result['failed']++;
            }
        }

        return $result;
    }

    /** @return list<string> Mailadressen der Personen in den Funktionsgruppen des Timers */
    public function recipients(MailTimer $timer): array
    {
        $groupIds = array_map('intval', (array) $timer->function_group_ids);
        if ($groupIds === []) {
            return [];
        }

        return ProjectPerson::query()->withoutGlobalScopes()->where('project_id', $timer->project_id)->whereIn('function_group_id', $groupIds)
            ->with(['person' => fn ($query) => $query->withoutGlobalScopes()])->get()
            ->map(fn (ProjectPerson $row) => $row->person)
            ->filter(fn ($person) => $person && $person->active && filled($person->email))
            ->pluck('email')->map(fn ($email) => strtolower(trim($email)))->unique()->values()->all();
    }

    /** Namen der Funktionsgruppen (für die Anzeige) */
    /**
     * Bezug der Erinnerung in Worten ("3 Tage vor Meilenstein „Druck“"); gemeinsam genutzt vom Projekt-Overlay und von Planung › Erinnerungen.
     *
     * @param  array<int, string>  $stepTitles  Schritt-ID => Titel
     * @param  array<int, string>  $milestoneNames  Meilenstein-ID => Name
     */
    public function ruleText(MailTimer $timer, array $stepTitles, array $milestoneNames): string
    {
        if ($timer->reference_type === MailTimer::REFERENCE_FIXED) {
            return __('festes Datum');
        }
        $reference = $timer->reference_type === MailTimer::REFERENCE_MILESTONE
            ? __('Meilenstein „:name“', ['name' => $milestoneNames[$timer->reference_milestone_id] ?? '?'])
            : __('Ende von „:name“', ['name' => $stepTitles[$timer->reference_step_id] ?? '?']);
        $days = abs((int) $timer->offset_days);
        if ($days === 0) {
            return __('am Tag von :reference', ['reference' => $reference]);
        }
        $amount = $days % 7 === 0 ? ($days / 7).' '.($days === 7 ? __('Woche') : __('Wochen')) : $days.' '.($days === 1 ? __('Tag') : __('Tage'));

        return $timer->offset_days < 0
            ? __(':amount vor :reference', ['amount' => $amount, 'reference' => $reference])
            : __(':amount nach :reference', ['amount' => $amount, 'reference' => $reference]);
    }

    public function groupNames(MailTimer $timer): array
    {
        return FunctionGroup::query()->withoutGlobalScopes()->whereIn('id', array_map('intval', (array) $timer->function_group_ids))->orderBy('name')->pluck('name')->all();
    }

    /** waiting = Schritt noch nicht erreicht, in = Projekt steht im Schritt, left = Schritt schon verlassen */
    private function stepState(Project $project, int $stepId): string
    {
        $rows = ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->with(['workflowStep' => fn ($query) => $query->withoutGlobalScopes()])->get()
            ->filter(fn (ProjectWorkflowStep $row) => $row->workflowStep && (int) $row->workflowStep->workflow_id === (int) $project->workflow_id);
        $target = $rows->firstWhere('workflow_step_id', $stepId);
        $current = $rows->firstWhere('is_current', true);
        if ($target === null) {
            return 'left';
        }
        if ($current === null) {
            return $target->completed_at ? 'left' : 'waiting';
        }
        if ((int) $current->workflow_step_id === $stepId) {
            return 'in';
        }

        return $current->workflowStep->sort > $target->workflowStep->sort ? 'left' : 'waiting';
    }

    /** @return string|null Fehlertext oder null bei Erfolg */
    private function send(MailTimer $timer, Project $project): ?string
    {
        $template = $timer->mailTemplate;
        if (! $template) {
            return __('Die Mail-Vorlage existiert nicht mehr.');
        }
        $recipients = $this->recipients($timer);
        if ($recipients === []) {
            return __('Keine Empfänger: In den gewählten Funktionsgruppen ist niemand mit E-Mail-Adresse eingetragen.');
        }

        // Fehlt der Vorlage inzwischen etwas (nachträglich geändert), geht lieber keine Mail raus als eine kaputte
        $missing = app(MailTemplateRenderer::class)->unavailable($template, (int) $project->tenant_id, true);
        if ($missing !== []) {
            return __('Die Mail-Vorlage enthält Felder, die nicht zur Verfügung stehen: :fields. Die Mail wurde nicht gesendet.', ['fields' => implode(', ', $missing)]);
        }

        $rendered = app(MailTemplateRenderer::class)->render($template, $project, $timer->workflow_step_id ? (int) $timer->workflow_step_id : null);
        try {
            Mail::raw($rendered['body'], fn ($message) => $message->to($recipients)->subject($rendered['subject']));
        } catch (\Throwable $e) {
            return __('Der Versand ist fehlgeschlagen.').' '.$e->getMessage();
        }

        $timer->forceFill(['sent_at' => now(), 'last_error' => null])->saveQuietly();
        Activity::log($project, ActivityType::MailTimerSent, __('Erinnerung gesendet: :subject (an :n Personen)', ['subject' => $rendered['subject'], 'n' => count($recipients)]));

        return null;
    }
}
