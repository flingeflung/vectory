<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function index(Request $request): View
    {
        $existing = Setting::query()->where('tenant_id', CurrentTenant::id())
            ->get()->keyBy('key');

        $settings = collect(Setting::DEFINITIONS)->map(fn ($definition, $key) => [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'value' => $existing->get($key)?->value ?? $definition['default'],
        ])->values();

        $multiTenantEnabled = SystemSetting::multiTenantEnabled();

        return view('admin.config.index', [
            'settings' => $settings,
            'multiTenantEnabled' => $multiTenantEnabled,
            // Ohne Mandantenfähigkeit gibt's keine "Kunden verwalten"-Liste -
            // der Projektpfad des einzigen Mandanten braucht trotzdem eine
            // Stelle zum Bearbeiten (siehe TenantController::update()).
            // Bei aktiver MF ist "Kunden verwalten" auf den eigenen
            // Admin-Tab "Kunden" umgezogen (siehe TenantController::index())
            // - bei 100+ echten Kunden eine andere Größenordnung als die
            // Konfig des aktiven Kunden (Ralf: "oben die 4 Buttons und
            // drunter die vielen Kunden").
            'currentTenant' => $multiTenantEnabled ? null : Tenant::query()->find(CurrentTenant::id()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'values' => ['array'],
                'values.*' => ['nullable', 'string', 'max:500'],
            ],
            attributes: collect(Setting::DEFINITIONS)
                ->mapWithKeys(fn ($definition, $key) => ["values.$key" => $definition['label']])
                ->all(),
        );

        foreach (array_keys(Setting::DEFINITIONS) as $key) {
            if (! array_key_exists($key, $validated['values'] ?? [])) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['tenant_id' => CurrentTenant::id(), 'key' => $key],
                ['value' => trim((string) $validated['values'][$key])],
            );
        }

        return redirect()->route('admin.config')->with('status', 'config-updated');
    }
}
