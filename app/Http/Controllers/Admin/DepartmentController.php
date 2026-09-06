<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abteilungs-Verwaltung als "klitzekleines" Unterbereich-Overlay aus dem
 * Personen-Overlay heraus - gleiches Muster wie CompanyController.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::query()->where('tenant_id', $request->user()->tenant_id)
            ->withCount('people')->orderBy('name')->get();

        return view('admin.departments.partials.manage-body', ['departments' => $departments]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Department::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $name,
        ]);

        return redirect()->route('admin.departments');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        abort_unless($department->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $department->update(['name' => $name, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.departments');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        abort_unless($department->tenant_id === $request->user()->tenant_id, 404);

        if ($department->people()->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? Department::query()->where('tenant_id', $department->tenant_id)->where('id', '!=', $department->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $department->people()->update(['department_id' => $reassignTo?->id]);
        }

        $department->delete();

        return redirect()->route('admin.departments');
    }
}
