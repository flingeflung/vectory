<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\MailTemplate;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mail-Vorlagen-Verwaltung, Step 1 (Ralf, 2026-09-10) - eigener Admin-Reiter
 * (zunächst als Overlay aus der Workflows-Seite gebaut, war laut Ralf zu
 * versteckt). Bewusst generisch nutzbar, nicht workflow-spezifisch, nur
 * der ursprüngliche Anwendungsfall (Sonderbutton "Info-Mail") war dort.
 */
class MailTemplateController extends Controller
{
    /**
     * Feste Basisfelder, die immer als Platzhalter zur Verfügung stehen -
     * unabhängig von der (noch dünnen) Attribut-Konfiguration je Mandant.
     */
    private const BASE_PLACEHOLDERS = [
        'pn' => 'PN',
        'title' => 'Bezeichnung',
    ];

    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $templates = MailTemplate::query()->where('tenant_id', $tenantId)->orderBy('name')->get();

        $placeholders = collect(self::BASE_PLACEHOLDERS)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->concat(
                Attribute::query()
                    ->where('tenant_id', $tenantId)
                    ->where('available_in_mail_templates', true)
                    ->orderBy('label')
                    ->get(['key', 'label'])
            );

        return view('admin.mail-templates.index', [
            'templates' => $templates,
            'placeholders' => $placeholders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        MailTemplate::query()->create([
            'tenant_id' => CurrentTenant::id(),
            'name' => $name,
            'body' => (string) $request->string('body'),
        ]);

        return redirect()->route('admin.mail-vorlagen')->with('status', 'mail-templates-updated');
    }

    public function update(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        abort_unless($mailTemplate->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $mailTemplate->update([
            'name' => $name,
            'body' => (string) $request->string('body'),
        ]);

        return redirect()->route('admin.mail-vorlagen')->with('status', 'mail-templates-updated');
    }

    public function destroy(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        abort_unless($mailTemplate->tenant_id === CurrentTenant::id(), 404);

        $mailTemplate->delete();

        return redirect()->route('admin.mail-vorlagen')->with('status', 'mail-templates-updated');
    }
}
