<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mail-Vorlagen-Konzept, Step 1 (Ralf, 2026-09-10): Betreff + Text mit
 * einfügbaren Projekt-Feld-Platzhaltern (z.B. "{material_number}", siehe
 * App\Models\Attribute::available_in_mail_templates + die festen
 * Basisfelder in MailTemplateController::BASE_PLACEHOLDERS). Empfänger/CC
 * sind bewusst NICHT Teil dieses Modells - die werden laut Ralf erst im
 * jeweiligen Anwendungsfall festgelegt (Step 2, noch nicht gebaut).
 */
#[Fillable(['tenant_id', 'name', 'subject', 'body'])]
class MailTemplate extends Model
{
    use BelongsToTenant;
}
