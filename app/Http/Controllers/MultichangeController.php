<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Support\MultichangeFieldCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

/**
 * "Multichange" (Ralf, 2026-09-13, nach Vietto-Analyse - volle Herleitung
 * und Konzept in der Backlog-Memory). Zielmenge ist BEWUSST ausschließlich
 * eine bestehende Projektgruppe (Ralf: "damit man das nicht leichtfertig
 * macht. Das ist ein sehr gefährliches Feature in den falschen Händen!") -
 * kein direkter Weg über den aktuellen Projektfilter, das ist eine bewusste
 * Reibungsbremse, kein Bequemlichkeits-Detail. Nicht nachträglich ändern,
 * ohne das explizit mit Ralf neu abzustimmen.
 *
 * Sicherheitsprinzipien, direkt aus den in Vietto gefundenen
 * Zuverlässigkeitslücken abgeleitet: (1) die Zielmenge wird bei jedem
 * Schritt (Vorschau UND Anwenden) frisch aus der DB gelesen, nie aus
 * Client-Zustand übernommen. (2) Anwenden läuft in einer Transaktion -
 * alles oder nichts. (3) übersprungene Projekte werden explizit mit PN
 * gelistet, nie stillschweigend ignoriert. (4) jede Änderung bekommt einen
 * echten Activity-Log-Eintrag am Projekt.
 */
class MultichangeController extends Controller
{
    /**
     * Ralf, 2026-09-13: "Das Gruppieren soll losgelöst sein davon, quasi
     * die Grundlage. Das macht man auch nicht ständig... Multichange kann
     * immer mal wieder dazwischen vorkommen." Multichange ist deshalb ein
     * eigener, immer sichtbarer Button in der Übersicht (nicht mehr im
     * Gruppieren-Panel versteckt) und wählt seine Zielgruppe selbst -
     * unabhängig davon, ob/welche Gruppe im Gruppieren-Store gerade aktiv
     * ist. Gruppen-Auswahl ist deshalb Teil DIESES Formulars, nicht mehr
     * Teil der Route (kein {group}-Routenparameter mehr).
     */
    public function form(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        return response()->view('projekte.partials.multichange-body', [
            'groups' => $this->availableGroups(),
            'fields' => MultichangeFieldCatalog::available(),
            'selectedGroupId' => $request->integer('group_id') ?: '',
            // Ralf: "Wenn ich auf Zurück klicke, werde ich bestraft und muss
            // nochmal von vorne beginnen" - Feld+Wert bleiben beim
            // Zurück-Klick jetzt erhalten (siehe "Zurück"-Button in
            // multichange-body.blade.php), nicht nur die Gruppe.
            'selectedField' => (string) $request->string('field'),
            'selectedValue' => (string) $request->string('value'),
        ]);
    }

    public function preview(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        $group = $this->resolveGroup($request);
        if ($group === null) {
            return $this->invalidInputResponse($request, new MessageBag(['group' => [__('Bitte eine Gruppe auswählen.')]]));
        }

        $validation = $this->validateInput($request);
        if ($validation['errors']) {
            return $this->invalidInputResponse($request, $validation['errors'], $group, $validation['field']);
        }
        [$field, $value] = [$validation['field'], $validation['value']];
        $preview = $this->buildPreview($group, $field, $value);

        return response()->view('projekte.partials.multichange-body', [
            'groups' => $this->availableGroups(),
            'fields' => MultichangeFieldCatalog::available(),
            'group' => $group,
            'preview' => $preview,
            'field' => $field,
            'value' => $value,
            'valueLabel' => $this->describeValue($field, $value),
        ]);
    }

    public function apply(Request $request): Response
    {
        abort_unless($request->user()->can('project.multichange'), 403);

        $group = $this->resolveGroup($request);
        if ($group === null) {
            return $this->invalidInputResponse($request, new MessageBag(['group' => [__('Bitte eine Gruppe auswählen.')]]));
        }

        $validation = $this->validateInput($request);
        if ($validation['errors']) {
            return $this->invalidInputResponse($request, $validation['errors'], $group, $validation['field']);
        }
        [$field, $value] = [$validation['field'], $validation['value']];
        $preview = $this->buildPreview($group, $field, $value);

        $applied = DB::transaction(function () use ($preview, $field, $value) {
            foreach ($preview['applicable'] as $project) {
                $project->{$field['key']} = $value;
                $project->save();

                Activity::log($project, ActivityType::ProjectMultichanged, $this->describeChange($field, $value));
            }

            return $preview['applicable']->count();
        });

        return response()->view('projekte.partials.multichange-body', [
            'group' => $group,
            'fields' => MultichangeFieldCatalog::available(),
            'result' => [
                'applied' => $applied,
                'skipped' => $preview['skipped'],
                'fieldLabel' => $field['label'],
            ],
        ]);
    }

    /**
     * Bewusst KEIN ->validate() (das würde bei einem Fehler per Redirect
     * auf die vorherige Seite umleiten - für einen fetch()-Aufruf aus dem
     * Modal heraus landet die komplette umgeleitete Seite dann roh im
     * Modal-Inhalt, siehe Bug-Fund beim Testen). Fehler werden stattdessen
     * selbst behandelt und als normales Formular-Fragment mit Fehlertext
     * zurückgegeben (siehe invalidInputResponse()).
     *
     * @return array{field: ?array, value: mixed, errors: ?MessageBag}
     */
    private function validateInput(Request $request): array
    {
        $field = MultichangeFieldCatalog::find((string) $request->string('field'));
        if ($field === null) {
            return ['field' => null, 'value' => null, 'errors' => new MessageBag(['field' => [__('Bitte ein Feld auswählen.')]])];
        }

        $rules = match ($field['type']) {
            'text' => ($field['required'] ?? false) ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'textarea' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'select' => ['required', Rule::in(array_keys($field['options']))],
        };

        $validator = Validator::make($request->all(), ['value' => $rules], [], ['value' => __('Neuer Wert')]);
        if ($validator->fails()) {
            return ['field' => $field, 'value' => null, 'errors' => $validator->errors()];
        }

        $value = $validator->validated()['value'] ?? null;
        if ($field['type'] === 'select' && $value !== null) {
            $value = (int) $value;
        }

        return ['field' => $field, 'value' => $value, 'errors' => null];
    }

    private function invalidInputResponse(Request $request, MessageBag $errors, ?ProjectGroup $group = null, ?array $field = null): Response
    {
        return response()
            ->view('projekte.partials.multichange-body', [
                'groups' => $this->availableGroups(),
                'fields' => MultichangeFieldCatalog::available(),
                'formErrors' => $errors,
                'selectedGroupId' => $group?->id ?? '',
                'selectedField' => $field['key'] ?? '',
                // Eingegebener Wert bleibt auch bei einem Validierungsfehler
                // erhalten, gleicher Grund wie beim "Zurück"-Button.
                'selectedValue' => (string) $request->string('value'),
            ])
            ->setStatusCode(422);
    }

    /**
     * @return Collection<int, ProjectGroup>
     */
    private function availableGroups(): Collection
    {
        return Auth::user()->projectGroups()->withCount('projects')->orderBy('name')->get();
    }

    /**
     * Liest+prüft die Zielgruppe aus dem Request statt aus einem
     * Routen-Parameter (siehe Klassen-Docblock: Gruppen-Auswahl ist Teil
     * des Formulars, damit der Multichange-Button unabhängig vom
     * Gruppieren-Panel funktioniert).
     */
    private function resolveGroup(Request $request): ?ProjectGroup
    {
        $groupId = $request->integer('group_id');
        if (! $groupId) {
            return null;
        }

        $group = ProjectGroup::query()->find($groupId);
        if ($group === null) {
            return null;
        }

        $group->authorizeViewer();

        return $group;
    }

    /**
     * @return array{applicable: Collection<int, Project>, skipped: Collection<int, Project>}
     */
    private function buildPreview(ProjectGroup $group, array $field, mixed $value): array
    {
        // Frisch aus der DB, nicht aus irgendeinem Client-Zustand übernommen -
        // die Gruppen-Mitgliedschaft kann sich zwischen Vorschau und Anwenden
        // ändern, das soll sich dann auch auswirken (Vietto-Lektion).
        $projects = $group->projects()->with('projectWorkflowSteps')->get();

        if ($field['key'] === 'status') {
            $applicable = $projects->reject(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
            $skipped = $projects->filter(fn (Project $project) => $project->projectWorkflowSteps->contains('is_current', true))->values();
        } else {
            $applicable = $projects;
            $skipped = collect();
        }

        return ['applicable' => $applicable, 'skipped' => $skipped];
    }

    private function describeChange(array $field, mixed $value): string
    {
        return __(':field per Multichange auf „:value" gesetzt.', ['field' => $field['label'], 'value' => $this->describeValue($field, $value)]);
    }

    private function describeValue(array $field, mixed $value): string
    {
        return match ($field['type']) {
            'select' => $field['options'][$value] ?? (string) $value,
            'date' => $value ? Carbon::parse($value)->format('d.m.Y') : __('entfernt'),
            default => ($value !== null && $value !== '') ? $value : __('entfernt'),
        };
    }
}
