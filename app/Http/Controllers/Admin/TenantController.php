<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Tenant::query()->create(['name' => $name]);

        return redirect()->route('admin.config');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $tenant->update(['name' => $name]);

        return redirect()->route('admin.config');
    }
}
