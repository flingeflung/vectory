<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $favoriteProjectIds = Favorite::query()->where('user_id', $request->user()->id)->pluck('project_id');

        $projects = Project::query()
            ->whereIn('id', $favoriteProjectIds)
            ->orderBy('source_pn')
            ->get();

        return view('favoriten.index', ['projects' => $projects]);
    }

    public function toggle(Request $request, Project $project): JsonResponse
    {
        $favorite = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('project_id', $project->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['is_favorite' => false]);
        }

        Favorite::create([
            'tenant_id' => $project->tenant_id,
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
        ]);

        return response()->json(['is_favorite' => true]);
    }
}
