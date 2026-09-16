<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\PaperFormatCombination;
use App\Models\Project;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Speichert die gewählte Format-Kombination am S1/S2-Formatfeld
 * (Print-Formate-Feature Schritt 4, Vietto: ajax_formate_saveformat.php).
 * Nur für Projektarten mit Formattyp "Standard-Formate" relevant - siehe
 * ProjectTypeSub::FORMAT_TYPE_STANDARD. "Variable Formate" (freier Text)
 * läuft über die normalen Formularfelder im Haupt-Speichern-Button, siehe
 * ProjectController::update().
 *
 * Gleiches Nachlade-Muster wie ProjectProductController::toggle(): die
 * Antwort rendert die komplette system-fields/format-Partial (Feldzeile +
 * Overlay) neu, das aufrufende JS ersetzt per DOMParser nur die Feldzeile
 * im Live-DOM - das offene Overlay bleibt dabei unangetastet stehen.
 */
class ProjectFormatController extends Controller
{
    public function saveCombination(Project $project, Request $request): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($request->user()->can('project.edit'), 403);

        $combination = PaperFormatCombination::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('active', true)
            ->matchingHeftung($project->attributes['heftung'] ?? null)
            ->findOrFail($request->integer('paper_format_combination_id'));

        $project->update([
            'paper_format_combination_id' => $combination->id,
            'input_format_free_text' => null,
            'output_format_free_text' => null,
        ]);

        return response()->view('projekte.partials.system-fields.format', [
            'project' => $project->fresh(),
            'field' => Attribute::query()->where('tenant_id', $project->tenant_id)->where('key', 'format')->first(),
        ]);
    }
}
