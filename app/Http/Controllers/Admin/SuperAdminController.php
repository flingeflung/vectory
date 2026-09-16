<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\AvailableLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuperAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.superadmin.index', [
            'multiTenantEnabled' => SystemSetting::multiTenantEnabled(),
            'translatableLocales' => AvailableLocales::translatable(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, $request->boolean('multi_tenant_enabled') ? '1' : '0');

        return redirect()->route('admin.superadmin')->with('status', 'superadmin-updated');
    }

    /**
     * Holt zuerst neu dazugekommene Texte nach (lang:sync), liefert die
     * Datei dann direkt zum Download - sie ist bereits der komplette
     * Export, kein separates Format nötig (siehe SyncLangJson-Docblock).
     * Sprache kommt aus AvailableLocales - eine weitere Sprache hinzufügen
     * braucht hier keine Code-Änderung.
     */
    public function downloadTranslations(Request $request): BinaryFileResponse
    {
        $locale = $request->string('locale', 'en');
        abort_unless(array_key_exists($locale, AvailableLocales::translatable()), 422);

        Artisan::call('lang:sync', ['locale' => $locale]);

        return response()->download(lang_path("{$locale}.json"), "vectory-uebersetzung-{$locale}.json");
    }

    /**
     * Ersetzt lang/{locale}.json durch die vom Übersetzer ausgefüllte
     * Datei. Grobe Plausibilitätsprüfung statt eines echten Diffs: reines
     * JSON-Objekt aus String-zu-String-Paaren, nicht drastisch weniger
     * Einträge als aktuell (Schutz gegen versehentlichen Upload der
     * falschen/einer unvollständigen Datei).
     */
    public function uploadTranslations(Request $request): RedirectResponse
    {
        $locale = $request->string('locale', 'en');
        abort_unless(array_key_exists($locale, AvailableLocales::translatable()), 422);

        $request->validate(['translation_file' => ['required', 'file']]);

        $decoded = json_decode((string) file_get_contents($request->file('translation_file')->getRealPath()), true);
        abort_if(! is_array($decoded), 422, 'Ungültige JSON-Datei.');
        abort_unless(collect($decoded)->every(fn ($v, $k) => is_string($k) && is_string($v)), 422, 'Die Datei muss eine einfache Zuordnung von Text zu Übersetzung sein.');

        $path = lang_path("{$locale}.json");
        $currentCount = File::exists($path) ? count(json_decode(File::get($path), true) ?? []) : 0;
        abort_if($currentCount > 0 && count($decoded) < $currentCount * 0.5, 422, 'Die hochgeladene Datei hat deutlich weniger Einträge als die aktuelle - Upload abgebrochen, um keine Übersetzungen zu verlieren.');

        ksort($decoded, SORT_STRING);
        File::put($path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return redirect()->route('admin.superadmin')->with('status', 'translations-uploaded');
    }
}
