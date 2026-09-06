<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function index(Request $request): View
    {
        $existing = Setting::query()->where('tenant_id', $request->user()->tenant_id)
            ->get()->keyBy('key');

        $settings = collect(Setting::DEFINITIONS)->map(fn ($definition, $key) => [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'value' => $existing->get($key)?->value ?? $definition['default'],
        ])->values();

        return view('admin.config.index', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->array('values');

        foreach (Setting::DEFINITIONS as $key => $definition) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'key' => $key],
                ['value' => trim((string) $values[$key])],
            );
        }

        return redirect()->route('admin.config')->with('status', 'config-updated');
    }
}
