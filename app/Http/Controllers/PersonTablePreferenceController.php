<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\PersonTableColumnCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PersonTablePreferenceController extends Controller
{
    public function update(Request $request): Response
    {
        $data = $request->validate([
            'columns' => ['required', 'array', 'min:1', 'max:20'],
            'columns.*' => ['required', 'string', 'distinct', 'max:64'],
        ]);

        $availableKeys = array_column(PersonTableColumnCatalog::available(SystemSetting::multiTenantEnabled()), 'key');
        abort_if(array_diff($data['columns'], $availableKeys) !== [], 422);

        PersonTableColumnCatalog::persistOrder(
            $request->user(),
            array_values($data['columns']),
            SystemSetting::multiTenantEnabled(),
        );

        return response()->noContent();
    }
}
