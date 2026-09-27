<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Prozentuale Aufteilung" (Ralf, 2026-09-27, siehe Roadmap-Backlog): Reiter 2
 * der Zeiterfassung, nur am Hauptprojekt mit mindestens einem Unterprojekt
 * sichtbar (siehe showAufteilungTab() unten). Legt fest, wie Stunden, die
 * am Hauptprojekt gebucht werden, auf Hauptprojekt + Unterprojekte verteilt
 * werden - siehe ProjectHourController::store() für die eigentliche Umlage
 * beim Buchen.
 */
class ProjectPercentageSplitController extends Controller
{
    public function tab(Request $request, Project $project): View
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $unterprojekte = $this->unterprojekte($project);
        abort_unless($project->verbund_rolle === 1 && $unterprojekte->isNotEmpty(), 404);

        $participants = collect([(object) [
            'id' => $project->id, 'source_pn' => $project->source_pn, 'title' => $project->title,
        ]])->concat($unterprojekte);

        $saved = DB::table('project_percentage_splits')->where('hauptprojekt_id', $project->id)->pluck('percentage', 'project_id');
        $configured = $saved->isNotEmpty();
        $shares = $configured
            ? $participants->mapWithKeys(fn ($p) => [$p->id => (float) ($saved[$p->id] ?? 0)])
            : $this->evenSplit($participants->pluck('id'));

        return view('projekte.partials.project-percentage-body', [
            'project' => $project,
            'participants' => $participants,
            'shares' => $shares,
            'configured' => $configured,
        ]);
    }

    public function update(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $unterprojekte = $this->unterprojekte($project);
        abort_unless($project->verbund_rolle === 1 && $unterprojekte->isNotEmpty(), 404);

        $validIds = $unterprojekte->pluck('id')->push($project->id)->all();

        $data = $request->validate([
            'shares' => ['required', 'array'],
            'shares.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $shares = [];
        foreach ($data['shares'] as $projectId => $value) {
            if (! in_array((int) $projectId, $validIds, true)) {
                continue;
            }
            $shares[(int) $projectId] = round((float) $value, 2);
        }

        $sum = round(array_sum($shares), 2);
        if ($sum !== 100.0) {
            throw ValidationException::withMessages(['shares' => __('Die Prozentwerte müssen in Summe 100 ergeben. Aktuell: :sum.', [
                'sum' => number_format($sum, 2, ',', '.'),
            ])]);
        }

        DB::transaction(function () use ($project, $shares) {
            foreach ($shares as $projectId => $percentage) {
                $match = ['hauptprojekt_id' => $project->id, 'project_id' => $projectId];
                $existing = DB::table('project_percentage_splits')->where($match)->first();
                if ($existing) {
                    DB::table('project_percentage_splits')->where('id', $existing->id)->update(['percentage' => $percentage, 'updated_at' => now()]);
                } else {
                    DB::table('project_percentage_splits')->insert($match + ['percentage' => $percentage, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return response($this->tab($request, $project)->render());
    }

    public static function showAufteilungTab(Project $project): bool
    {
        return $project->verbund_rolle === 1
            && DB::table('projects')->where('hauptprojekt_id', $project->id)->where('verbund_rolle', 2)->exists();
    }

    private function unterprojekte(Project $project)
    {
        return DB::table('projects')->where('hauptprojekt_id', $project->id)->where('verbund_rolle', 2)
            ->orderBy('source_pn')->get(['id', 'source_pn', 'title']);
    }

    /**
     * Gleichmäßige Ausgangsverteilung, solange noch nichts gespeichert wurde -
     * in Hundertstel-Prozent gerechnet, Rest deterministisch den ersten
     * Teilnehmern (nach id-Reihenfolge) zugeschlagen, damit die Summe exakt
     * 100,00 ergibt.
     */
    private function evenSplit($ids): \Illuminate\Support\Collection
    {
        $ids = $ids->values();
        $n = $ids->count();
        $base = intdiv(10000, $n);
        $remainder = 10000 - $base * $n;

        return $ids->mapWithKeys(function ($id, $i) use ($base, $remainder) {
            $hundredths = $base + ($i < $remainder ? 1 : 0);

            return [$id => round($hundredths / 100, 2)];
        });
    }
}
