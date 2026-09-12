{{--
    Ralf, 2026-09-12 ("WFS: alle 4 Lifecycle-Status abgedeckt?"): weiche
    Warnung (kein Blocker, gleiches Prinzip wie die offene-Illustrations-
    aufträge-Warnung in ProjectWorkflowStepController) statt eines harten
    Publizieren-Sperre - manche Workflows brauchen absichtlich nicht jeden
    Status (z. B. keinen eigenen "Verworfen"-Schritt). Erklärt bewusst die
    WIRKUNG (Projekt-Status, Kopieren-Feature), nicht nur "Kastenfarbe
    fehlt" - das ist sonst an keiner Stelle im UI ersichtlich.
--}}
@if ($missingLifecycleStatuses->isNotEmpty())
    <div class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        {{ __('Kein Schritt für: :statuses. Ein Projekt auf diesem Workflow kann diesen Status dann nie automatisch erreichen (betrifft auch "Projekt kopieren", das einen Schritt mit Status "Geplant" als Startschritt braucht).', [
            'statuses' => $missingLifecycleStatuses->map(fn ($status) => __($lifecycleStatusLabels[$status]))->join(', '),
        ]) }}
    </div>
@endif
