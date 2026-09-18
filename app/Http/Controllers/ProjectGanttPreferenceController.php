<?php

namespace App\Http\Controllers;

use App\Models\ProjectGanttPreference;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectGanttPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $preference = ProjectGanttPreference::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', CurrentTenant::id())
            ->first();

        return response()->json([
            'mode' => $preference?->mode ?? 'per-project',
            'scale' => $preference?->scale ?? 28,
            'selected_person_ids' => $preference?->selected_person_ids ?? [],
            'known_person_ids' => $preference?->known_person_ids ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['per-project', 'all'])],
            'scale' => ['required', 'integer', 'between:18,60'],
            'selected_person_ids' => ['required', 'array', 'max:5000'],
            'selected_person_ids.*' => ['integer', 'distinct', 'min:1'],
            'known_person_ids' => ['required', 'array', 'max:5000'],
            'known_person_ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        ProjectGanttPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'tenant_id' => CurrentTenant::id()],
            $validated,
        );

        return response()->json(['saved' => true]);
    }
}
