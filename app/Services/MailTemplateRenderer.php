<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\ProjectWorkflowStep;
use App\Models\WorkflowStep;
use App\Http\Controllers\Admin\MailTemplateController;
use Illuminate\Support\Facades\Schema;

/**
 * Setzt in einer Mail-Vorlage die Platzhalter ({pn}, {title}, {start_date}, … sowie Projektattribute) mit den Werten eines Projekts ein
 * (Ralf, 2026-09-10: Vorlagen kennen Platzhalter, der Anwendungsfall bestimmt Empfänger). Unbekannte Platzhalter bleiben leer.
 */
class MailTemplateRenderer
{
    /** Felder, die nur in Mails zu einem Workflow-Schritt vorkommen (Ralf, 2026-10-09). */
    public const STEP_PLACEHOLDERS = [
        'wfs' => 'Workflow-Schritt',
        'wfs_seit' => 'Schritt aktiv seit',
        'wfs_ende' => 'Ende des Schritts',
    ];

    /**
     * @param  int|null  $stepId  Workflow-Schritt, zu dem die Mail gehört (Mail-Timer: immer gesetzt); null = Mail ohne Schritt-Zusammenhang
     * @return array{subject: string, body: string}
     */
    public function render(MailTemplate $template, Project $project, ?int $stepId = null): array
    {
        $values = $this->values($project) + ($stepId !== null ? $this->stepValues($project, $stepId) : []);
        $replace = fn (string $text) => preg_replace_callback('/\{([a-z0-9_]+)\}/i', fn (array $match) => $values[$match[1]] ?? '', $text);

        return ['subject' => $replace((string) $template->subject), 'body' => $replace((string) $template->body)];
    }

    /**
     * Felder der Vorlage, die im angegebenen Zusammenhang nicht zur Verfügung stehen (Schritt-Felder ohne Schritt, unbekannte Felder wie
     * Tippfehler). Leer = die Vorlage ist hier verwendbar.
     *
     * @return list<string> Platzhalter in geschweiften Klammern, z. B. "{wfs}"
     */
    public function unavailable(MailTemplate $template, int $tenantId, bool $withStep): array
    {
        $available = array_keys(MailTemplateController::basePlaceholders());
        $available = array_merge($available, Attribute::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('available_in_mail_templates', true)->pluck('key')->all());
        if ($withStep) {
            $available = array_merge($available, array_keys(self::STEP_PLACEHOLDERS));
        }

        preg_match_all('/\{([a-z0-9_]+)\}/i', (string) $template->subject."\n".(string) $template->body, $matches);

        return collect($matches[1])->unique()->reject(fn ($key) => in_array($key, $available, true))->map(fn ($key) => '{'.$key.'}')->values()->all();
    }

    /** @return array<string, string> */
    private function stepValues(Project $project, int $stepId): array
    {
        $step = WorkflowStep::query()->withoutGlobalScopes()->find($stepId);
        $row = ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $stepId)->first();

        return [
            'wfs' => (string) ($step?->title ?? ''),
            'wfs_seit' => $row?->started_at ? $row->started_at->format('d.m.Y') : '',
            'wfs_ende' => $row?->due_date ? $row->due_date->format('d.m.Y') : '',
        ];
    }

    /** @return array<string, string> */
    private function values(Project $project): array
    {
        $date = fn ($value) => $value ? $value->format('d.m.Y') : '';
        $values = [
            'pn' => (string) $project->source_pn,
            'title' => (string) $project->title,
            'start_date' => $date($project->start_date),
            'end_date' => $date($project->end_date),
            'publication_date' => $date($project->publication_date),
            'change_log' => ProjectNote::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('type', ProjectNote::TYPE_CHANGE)
                ->orderBy('created_at')->pluck('text')->implode("\n"),
        ];

        $custom = (array) ($project->attributes ?? []);
        $attributes = Attribute::query()->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)->where('available_in_mail_templates', true)->get(['key']);
        foreach ($attributes as $attribute) {
            $key = $attribute->key;
            $value = array_key_exists($key, $custom) ? $custom[$key] : (Schema::hasColumn('projects', $key) ? $project->getAttribute($key) : null);
            $values[$key] = is_scalar($value) || $value instanceof \Stringable ? (string) $value : (is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : '');
        }

        return $values;
    }
}
