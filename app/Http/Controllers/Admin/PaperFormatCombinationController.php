<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaperFormat;
use App\Models\PaperFormatCombination;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verwaltung freigegebener Format-Kombinationen (Vietto: `formate_cx`,
 * Schritt 2 von 5 des Print-Formate-Rebuilds, siehe Backlog-Memory).
 * Eingebettet in dieselbe Seite wie der Papierformate-Katalog
 * (PaperFormatController::index()), keine eigene index()-Methode nötig.
 */
class PaperFormatCombinationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        $data = $this->validated($request, $tenantId);

        PaperFormatCombination::query()->create([...$data, 'tenant_id' => $tenantId]);

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-kombination-updated');
    }

    public function update(Request $request, PaperFormatCombination $combination): RedirectResponse
    {
        abort_unless($combination->tenant_id === CurrentTenant::id(), 404);

        $data = $this->validated($request, $combination->tenant_id, $combination);

        $combination->update($data);

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-kombination-updated');
    }

    public function destroy(PaperFormatCombination $combination): RedirectResponse
    {
        abort_unless($combination->tenant_id === CurrentTenant::id(), 404);
        abort_if($combination->isUsedByProjects(), 422, 'Diese Format-Kombination wird bereits von Projekten verwendet und kann nicht gelöscht werden.');

        $combination->delete();

        return redirect()->route('admin.papierformate')->with('status', 'papierformate-kombination-deleted');
    }

    /**
     * @return array{input_format_id: int, output_format_id: int, fold_count: ?int, remark: ?string, active: bool}
     */
    private function validated(Request $request, int $tenantId, ?PaperFormatCombination $ignore = null): array
    {
        $inputFormatId = $request->integer('input_format_id');
        $outputFormatId = $request->integer('output_format_id');

        abort_unless(
            PaperFormat::query()->where('tenant_id', $tenantId)->whereKey($inputFormatId)->exists()
            && PaperFormat::query()->where('tenant_id', $tenantId)->whereKey($outputFormatId)->exists(),
            422
        );

        abort_if(
            PaperFormatCombination::query()
                ->where('tenant_id', $tenantId)
                ->where('input_format_id', $inputFormatId)
                ->where('output_format_id', $outputFormatId)
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                ->exists(),
            422,
            'Diese Kombination aus Ausgangs- und Endformat gibt es bereits.'
        );

        return [
            'input_format_id' => $inputFormatId,
            'output_format_id' => $outputFormatId,
            'fold_count' => $request->filled('fold_count') ? $request->integer('fold_count') : null,
            'remark' => trim((string) $request->string('remark')) ?: null,
            'active' => $request->boolean('active'),
        ];
    }
}
