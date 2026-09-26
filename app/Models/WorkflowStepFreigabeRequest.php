<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hält die Auswahl fest, die der TR beim Auslösen eines Freigabe-WFS
 * trifft, bis der externe Empfänger über den Mail-Link reagiert - siehe
 * ProjectWorkflowStepController::activate() (Anlage) und
 * WorkflowStepFreigabeActionController (Reaktion, ganz ohne Login).
 *
 * source_path ist optional und aus dem lokalen Arbeitsverzeichnis
 * gewählt (Ralf, 2026-09-26: Checkout/Checkin gibt's noch nicht, das
 * gesperrte Verzeichnis wird für dieses Feature nicht angefasst) - kann
 * eine einzelne Datei sein (Komfort-Weg: wird der Mail als Anhang
 * beigelegt, nur wenn's ein einzelnes PDF ist - sonst nur als Pfadangabe
 * im Mailtext) oder ein Ordner (komplexerer Fall: nur der Pfad wird im
 * Mailtext genannt, z.B. mehrere Illustrationen oder eine PPT - der
 * externe Empfänger schaut dann selbst im Arbeitsverzeichnis nach).
 * korrektur_target_path ist dagegen Pflicht - dort landet der Upload bei
 * "Korrekturen einarbeiten".
 */
#[Fillable([
    'tenant_id', 'project_id', 'project_workflow_step_id', 'triggered_by_person_id',
    'source_path', 'korrektur_target_path',
    'status', 'decided_at',
])]
class WorkflowStepFreigabeRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FREIGEGEBEN = 'freigegeben';

    public const STATUS_KORREKTUR_HOCHGELADEN = 'korrektur_hochgeladen';

    /** Durch eine neuere Anfrage bzw. erneute Aktivierung des Schritts überholt - Links nicht mehr nutzbar. */
    public const STATUS_ERSETZT = 'ersetzt';

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflowStep::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'triggered_by_person_id')->withoutGlobalScope('tenant');
    }

    /**
     * Beendet alle noch offenen Mail-Anfragen eines Schritts (Ralf,
     * 2026-09-26): z.B. weil die Freigabe inzwischen per Button erteilt
     * wurde oder der Schritt neu ausgelöst wird. Sonst könnte ein alter
     * Mail-Link wieder gültig werden, sobald der Schritt erneut aktuell ist.
     */
    public static function closePendingFor(int $projectWorkflowStepId, string $status): void
    {
        self::query()
            ->withoutGlobalScope('tenant')
            ->where('project_workflow_step_id', $projectWorkflowStepId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => $status, 'decided_at' => now()]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
