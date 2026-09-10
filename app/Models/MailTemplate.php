<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mail-Vorlagen-Konzept, Step 1 (Ralf, 2026-09-10): reiner Textbaustein mit
 * einfügbaren Projekt-Feld-Platzhaltern (z.B. "{material_number}", siehe
 * App\Models\Attribute::available_in_mail_templates + die festen
 * Basisfelder in MailTemplateController::PLACEHOLDER_FIELDS). Wo eine
 * Vorlage ausgewählt und mit wem/welchem Betreff sie tatsächlich verschickt
 * wird, ist bewusst nicht Teil dieses Modells - das kommt in Step 2.
 */
#[Fillable(['tenant_id', 'name', 'body'])]
class MailTemplate extends Model
{
    use BelongsToTenant;
}
