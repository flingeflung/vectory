<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.superadmin.index', [
            'multiTenantEnabled' => SystemSetting::multiTenantEnabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, $request->boolean('multi_tenant_enabled') ? '1' : '0');

        return redirect()->route('admin.superadmin')->with('status', 'superadmin-updated');
    }
}
