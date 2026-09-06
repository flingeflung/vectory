<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Geschäftsbereichs-Verwaltung als "klitzekleines" Unterbereich-Overlay aus
 * dem Personen-Overlay heraus - gleiches Muster wie CompanyController. Die
 * frühere eigenständige Seite (admin/business-units) ist damit obsolet.
 */
class BusinessUnitController extends Controller
{
    public function index(Request $request): View
    {
        $businessUnits = BusinessUnit::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('people')
            ->orderBy('name')
            ->get();

        return view('admin.business-units.partials.manage-body', ['businessUnits' => $businessUnits]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        BusinessUnit::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $name,
        ]);

        return redirect()->route('admin.geschaeftsbereiche');
    }

    public function update(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        abort_unless($businessUnit->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $businessUnit->update(['name' => $name, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.geschaeftsbereiche');
    }

    public function destroy(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        abort_unless($businessUnit->tenant_id === $request->user()->tenant_id, 404);

        if ($businessUnit->people()->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? BusinessUnit::query()->where('tenant_id', $businessUnit->tenant_id)->where('id', '!=', $businessUnit->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $businessUnit->people()->update(['business_unit_id' => $reassignTo?->id]);
        }

        $businessUnit->delete();

        return redirect()->route('admin.geschaeftsbereiche');
    }
}
