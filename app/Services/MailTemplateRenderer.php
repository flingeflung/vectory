<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Support\Facades\Schema;

/**
 * Setzt in einer Mail-Vorlage die Platzhalter ({pn}, {title}, {start_date}, … sowie Projektattribute) mit den Werten eines Projekts ein
 * (Ralf, 2026-09-10: Vorlagen kennen Platzhalter, der Anwendungsfall bestimmt Empfänger). Unbekannte Platzhalter bleiben leer.
 */
class MailTemplateRenderer
{
    /** @return array{subject: string, body: string} */
    public function render(MailTemplate $template, Project $project): array
    {
        $values = $this->values($project);
        $replace = fn (string $text) => preg_replace_callback('/\{([a-z0-9_]+)\}/i', fn (array $match) => $values[$match[1]] ?? '', $text);

        return ['subject' => $replace((string) $template->subject), 'body' => $replace((string) $template->body)];
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
