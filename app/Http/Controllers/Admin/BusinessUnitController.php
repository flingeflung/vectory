<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung der Geschäftsbereiche - reine, flache Stammdatenliste ohne
 * Abhängigkeit zu Firmen/Abteilungen (siehe BusinessUnit-Model).
 */
class BusinessUnitController extends Controller
{
    public function index(Request $request): View
    {
        $businessUnits = BusinessUnit::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return view('admin.business-units.index', ['businessUnits' => $businessUnits]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        BusinessUnit::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $name,
        ]);

        return redirect()->route('admin.geschaeftsbereiche')->with('status', 'business-unit-updated');
    }

    public function update(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        abort_unless($businessUnit->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $businessUnit->update(['name' => $name, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.geschaeftsbereiche')->with('status', 'business-unit-updated');
    }
}
