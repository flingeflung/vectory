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
        'start_date' => 'Start',
        'end_date' => 'Ende',
        'publication_date' => 'Publikation',
        // Ralf, 2026-09-12: bewusst NUR das Änderungsprotokoll, nicht die
        // Bemerkungen (die bleiben rein intern, siehe ProjectNote) - beim
        // Einfügen immer das komplette, aktuelle Protokoll (alle Einträge),
        // kein einzelner Eintrag.
        'change_log' => 'Änderungsprotokoll',
    ];

    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $templates = MailTemplate::query()->where('tenant_id', $tenantId)->orderBy('name')->get();

        $selectedTemplate = $request->filled('template')
            ? $templates->firstWhere('id', (int) $request->query('template'))
            : null;

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
            'selectedTemplate' => $selectedTemplate,
            'placeholders' => $placeholders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Analog zu WorkflowController::store(): nur der Name ist beim
        // Anlegen Pflicht, Betreff/Text werden direkt danach rechts in der
        // Auswahl ausgefüllt - dort (in update()) sind sie dann Pflicht.
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $template = MailTemplate::query()->create([
            'tenant_id' => CurrentTenant::id(),
            'name' => $name,
            'subject' => '',
            'body' => '',
        ]);

        return redirect()->route('admin.mail-vorlagen', ['template' => $template->id])->with('status', 'mail-templates-updated');
    }

    public function update(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        abort_unless($mailTemplate->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        $subject = trim((string) $request->string('subject'));
        abort_if($name === '' || $subject === '', 422);

        $mailTemplate->update([
            'name' => $name,
            'subject' => $subject,
            'body' => (string) $request->string('body'),
        ]);

        return redirect()->route('admin.mail-vorlagen', ['template' => $mailTemplate->id])->with('status', 'mail-templates-updated');
    }

    public function destroy(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        abort_unless($mailTemplate->tenant_id === CurrentTenant::id(), 404);

        $mailTemplate->delete();

        return redirect()->route('admin.mail-vorlagen')->with('status', 'mail-templates-updated');
    }
}
