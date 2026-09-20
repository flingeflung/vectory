<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Support\CurrentTenant;
use App\Support\StammId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Versionskette eines Dokuments (Ralf, 2026-09-20, "Stamm-ID"): alle Projekte
 * mit derselben Stamm-ID sind Versionen desselben Dokuments. Die Kette
 * entsteht beim Kopieren (aufversionieren, siehe ProjectCopyController);
 * hier nur die Übersicht (Vorbild: Viettos "Versionen mit dieser Mat.-Nr.")
 * und die Korrektur "aus der Kette lösen".
 */
class StammIdController extends Controller
{
    public function chain(Request $request, Project $project): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($request->user()->can('project.view'), 403);

        return view('projekte.partials.stamm-id-chain', [
            'project' => $project,
            'chain' => $project->stammChain()->get(),
            'canManage' => $request->user()->can('project.stamm_id.manage'),
            // Die "Kundenversion" ist ein Zusatzfeld je Kunde - Spalte nur, wenn es eines gibt.
            'versionAttribute' => $project->incrementingVersionAttributes()->first(),
        ]);
    }

    /**
     * Korrektur, wenn ein Projekt fälschlich aufversioniert wurde: bekommt
     * eine neue, eigene Stamm-ID und beginnt eine neue Kette. Die übrigen
     * Versionen behalten ihre Stamm-ID.
     */
    public function detach(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($request->user()->can('project.stamm_id.manage'), 403);

        if ($project->stammChain()->count() < 2) {
            return response()->json(['message' => __('Dieses Projekt steht allein in seiner Versionskette.')], 422);
        }

        $oldId = $project->stamm_id;

        DB::transaction(function () use ($project, $oldId) {
            $project->update(['stamm_id' => StammId::generate(), 'stamm_position' => 1]);

            Activity::log(
                $project,
                ActivityType::StammIdDetached,
                __('Aus der Versionskette gelöst (Stamm-ID :old → :new).', [
                    'old' => StammId::format($oldId),
                    'new' => StammId::format($project->stamm_id),
                ])
            );
        });

        return response()->json(['stamm_id' => $project->stamm_id]);
    }
}
