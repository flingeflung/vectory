<?php

namespace App\Http\Controllers;

use App\Models\PermissionTemplate;
use App\Support\CurrentTenant;
use App\Support\Morph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Morphen starten und beenden (Ralf, 2026-10-06) - siehe App\Support\Morph. Nur für den echten Super-Admin; die Prüfung nimmt bewusst
 * die gespeicherte Rolle, damit "Morphen beenden" auch dann geht, wenn die gemorphte Rolle keine Rechte dafür hätte.
 */
class MorphController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(Morph::isRealSuperAdmin($request->user()), 403);

        $data = $request->validate([
            'role' => ['required', Rule::in(Morph::ROLES)],
            'template_id' => ['nullable', 'integer', Rule::exists('permission_templates', 'id')->where('is_baustein', false)],
        ]);

        $templateId = isset($data['template_id']) ? (int) $data['template_id'] : null;
        $tenantId = (int) CurrentTenant::id();
        Morph::start($data['role'], $templateId, $tenantId);

        Log::info('Morphen gestartet', ['user_id' => $request->user()->getKey(), 'role' => $data['role'], 'template_id' => $templateId, 'tenant_id' => $tenantId]);

        return response()->json(['label' => Morph::label()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless(Morph::isRealSuperAdmin($request->user()), 403);

        Morph::end();

        Log::info('Morphen beendet', ['user_id' => $request->user()->getKey()]);

        return response()->json(['ok' => true]);
    }
}
