<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Tenant::query()->create(['name' => $name]);

        return redirect()->route('admin.superadmin');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant->update(['name' => $name]);

        return redirect()->route('admin.superadmin');
    }
}
