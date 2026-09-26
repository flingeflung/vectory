<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\WorkflowStepFreigabeRequest;
use App\Services\ProjectDirectoryLocator;
use App\Services\WorkflowStepActivator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Öffentliche Endpunkte (KEIN Login, nur signierter Link + throttle) für
 * die beiden Links der Freigabe-Mail (WorkflowStepFreigabeMail).
 * Sicherheitsmodell: Signatur + Ablaufdatum in der URL, Einmal-Nutzung
 * über WorkflowStepFreigabeRequest::status (Zeilensperre gegen
 * Doppelklick), Aktion erst per POST auf der Bestätigungsseite - ein
 * bloßes Öffnen des Links (z.B. Mail-Virenscanner, Link-Vorschau) löst
 * nichts aus. Im login-freien Request greift der Mandanten-Scope nicht
 * (BelongsToTenant prüft Auth::check()) - alle Zugriffe laufen hier über
 * die per signierter ID gefundene Request-Zeile.
 */
class WorkflowStepFreigabeActionController extends Controller
{
    public function __construct(
        private readonly WorkflowStepActivator $activator,
        private readonly ProjectDirectoryLocator $locator,
    ) {}

    public function confirm(WorkflowStepFreigabeRequest $freigabeRequest): View
    {
        if ($reason = $this->unavailableReason($freigabeRequest)) {
            return $this->notice($reason);
        }

        return view('freigabe.confirm', $this->viewData($freigabeRequest));
    }

    public function grant(WorkflowStepFreigabeRequest $freigabeRequest): View
    {
        $reason = null;

        DB::transaction(function () use ($freigabeRequest, &$reason) {
            $locked = WorkflowStepFreigabeRequest::query()->lockForUpdate()->find($freigabeRequest->id);
            $reason = $this->unavailableReason($locked);
            if ($reason) {
                return;
            }

            $locked->update(['status' => WorkflowStepFreigabeRequest::STATUS_FREIGEGEBEN, 'decided_at' => now()]);
            $this->activator->grantFreigabe(
                $locked->project,
                $locked->project->projectWorkflowSteps->firstWhere('id', $locked->project_workflow_step_id),
                null,
                __('per E-Mail-Link'),
            );
        });

        if ($reason) {
            return $this->notice($reason);
        }

        return view('freigabe.done', [
            'title' => __('Freigabe erteilt'),
            'message' => __('Vielen Dank, die Freigabe wurde erteilt.'),
        ]);
    }

    public function korrekturForm(WorkflowStepFreigabeRequest $freigabeRequest): View
    {
        if ($reason = $this->unavailableReason($freigabeRequest)) {
            return $this->notice($reason);
        }

        return view('freigabe.korrektur', $this->viewData($freigabeRequest) + ['errorMessage' => null]);
    }

    public function korrekturUpload(Request $request, WorkflowStepFreigabeRequest $freigabeRequest): View
    {
        if ($reason = $this->unavailableReason($freigabeRequest)) {
            return $this->notice($reason);
        }

        $request->validate([
            'file' => ['required', 'file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:'.(40 * 1024)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'file.required' => __('Bitte wählen Sie eine PDF-Datei aus.'),
            'file.uploaded' => __('Der Upload ist fehlgeschlagen - die Datei ist vermutlich zu groß (maximal 40 MB).'),
            'file.extensions' => __('Es sind nur PDF-Dateien erlaubt.'),
            'file.mimetypes' => __('Es sind nur PDF-Dateien erlaubt.'),
            'file.max' => __('Die Datei ist zu groß (maximal 40 MB).'),
        ]);

        $fileName = null;
        $errorMessage = null;
        $reason = null;
        $comment = trim((string) $request->input('comment')) ?: null;

        DB::transaction(function () use ($request, $freigabeRequest, $comment, &$fileName, &$errorMessage, &$reason) {
            $locked = WorkflowStepFreigabeRequest::query()->lockForUpdate()->find($freigabeRequest->id);
            $reason = $this->unavailableReason($locked);
            if ($reason) {
                return;
            }

            $project = $locked->project;
            $contact = $locked->triggeredBy?->fullName();
            $hint = $contact ? ' '.__('Bitte informieren Sie :name.', ['name' => $contact]) : '';

            $projectPath = $this->locator->arbeitsverzeichnisProjectPath($project);
            if ($projectPath === null) {
                $errorMessage = __('Das Projektverzeichnis für Projekt :pn wurde im Arbeitsverzeichnis nicht gefunden.', ['pn' => $project->source_pn]).$hint;

                return;
            }

            $targetDir = $this->locator->resolveRelative($projectPath, $locked->korrektur_target_path);
            if ($targetDir === null || ! is_dir($targetDir)) {
                $errorMessage = __('Erwartetes Verzeichnis: :dir nicht gefunden.', ['dir' => $locked->korrektur_target_path]).$hint;

                return;
            }

            $fileName = $this->uniqueFileName($targetDir, $request->file('file')->getClientOriginalName());
            $request->file('file')->move($targetDir, $fileName);

            $locked->update([
                'status' => WorkflowStepFreigabeRequest::STATUS_KORREKTUR_HOCHGELADEN,
                'korrektur_kommentar' => $comment,
                'decided_at' => now(),
            ]);

            $pws = $project->projectWorkflowSteps->firstWhere('id', $locked->project_workflow_step_id);
            $title = $pws->workflowStep->title;

            Activity::log($project, ActivityType::WorkflowStepActivated, trim(
                __('Korrektur zu ":title" per E-Mail-Link hochgeladen: :file.', ['title' => $title, 'file' => $fileName])
                .($comment ? ' '.__('Kommentar: :comment', ['comment' => $comment]) : '')
            ));

            $previous = $this->activator->previousStep($project, $pws);
            if ($previous) {
                $this->activator->activate(
                    $project,
                    $previous,
                    null,
                    sendEmail: true,
                    message: trim(
                        __('Korrektur zu ":title" wurde hochgeladen: :file.', ['title' => $title, 'file' => $fileName])
                        .($comment ? "\n".__('Kommentar: :comment', ['comment' => $comment]) : '')
                    ),
                    fallbackToTenantEmail: true,
                );
            }
        });

        if ($reason) {
            return $this->notice($reason);
        }

        if ($errorMessage) {
            return view('freigabe.korrektur', $this->viewData($freigabeRequest) + ['errorMessage' => $errorMessage]);
        }

        return view('freigabe.done', [
            'title' => __('Korrektur hochgeladen'),
            'message' => __('Vielen Dank, Ihre Datei „:file“ wurde abgelegt.', ['file' => $fileName]),
        ]);
    }

    /**
     * Warum der Link nicht (mehr) genutzt werden kann - null = alles gut.
     */
    private function unavailableReason(WorkflowStepFreigabeRequest $freigabeRequest): ?string
    {
        if (! $freigabeRequest->isPending()) {
            return __('Diese Anfrage wurde bereits bearbeitet. Die Links in der Mail funktionieren nur einmal.');
        }

        $pws = $freigabeRequest->projectWorkflowStep;
        if (! $pws || ! $pws->is_current) {
            return __('Dieser Schritt ist nicht mehr aktuell, die Anfrage ist daher nicht mehr gültig.');
        }

        return null;
    }

    private function viewData(WorkflowStepFreigabeRequest $freigabeRequest): array
    {
        return [
            'freigabeRequest' => $freigabeRequest,
            'project' => $freigabeRequest->project,
            'step' => $freigabeRequest->projectWorkflowStep->workflowStep,
        ];
    }

    private function notice(string $message): View
    {
        return view('freigabe.done', ['title' => __('Hinweis'), 'message' => $message]);
    }

    /**
     * Ursprünglichen Namen möglichst beibehalten, aber unschädlich machen
     * und nie eine vorhandene Datei überschreiben (Name_2.pdf, ...).
     */
    private function uniqueFileName(string $dir, string $originalName): string
    {
        $base = $this->locator->sanitizeFolderName(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'Korrektur';
        $candidate = $base.'.pdf';

        for ($i = 2; file_exists($dir.DIRECTORY_SEPARATOR.$candidate); $i++) {
            $candidate = $base.'_'.$i.'.pdf';
        }

        return $candidate;
    }
}
