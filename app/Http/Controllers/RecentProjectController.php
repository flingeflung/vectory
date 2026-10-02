<?php

namespace App\Http\Controllers;

use App\Models\RecentlyViewedProject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecentProjectController extends Controller
{
    public function index(Request $request): View
    {
        $entries = RecentlyViewedProject::query()
            ->where('user_id', $request->user()->id)
            ->with(['project.hauptprojekt', 'project.projectTypeSub'])
            ->orderByDesc('viewed_at')
            ->limit(RecentlyViewedProject::LIMIT)
            ->get();

        return view('recent-projects.index', ['entries' => $entries]);
    }
}
