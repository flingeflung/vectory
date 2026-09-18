<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\ChecklistController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\CopyTemplateController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FunctionGroupController;
use App\Http\Controllers\Admin\HelpArticleController;
use App\Http\Controllers\Admin\JobTypeController;
use App\Http\Controllers\Admin\LegacyRoleController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MarketController;
use App\Http\Controllers\Admin\PaperFormatCombinationController;
use App\Http\Controllers\Admin\PaperFormatController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PersonController as AdminPersonController;
use App\Http\Controllers\Admin\ProjectTypeController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayFilterController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GraphicOrderController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\IllustrationOverviewController;
use App\Http\Controllers\JobloadController;
use App\Http\Controllers\JobloadOverviewController;
use App\Http\Controllers\LocaleSwitchController;
use App\Http\Controllers\MultichangeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectChecklistController;
use App\Http\Controllers\ProjectConnectionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGanttPeopleController;
use App\Http\Controllers\ProjectGanttPreferenceController;
use App\Http\Controllers\ProjectCopyController;
use App\Http\Controllers\ProjectDirectoryController;
use App\Http\Controllers\ProjectFormatController;
use App\Http\Controllers\ProjectGroupController;
use App\Http\Controllers\ProjectNoteController;
use App\Http\Controllers\ProjectProductController;
use App\Http\Controllers\ProjectScheduleController;
use App\Http\Controllers\ProjectWorkflowStepController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\VerbundController;
use App\Http\Middleware\RememberLastAdminPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/layout', [DashboardController::class, 'updateLayout'])->name('dashboard.layout');
    Route::delete('/dashboard/recent/{recentlyViewedProject}', [DashboardController::class, 'removeRecent'])->name('dashboard.recent.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/jobload', [JobloadController::class, 'index'])->name('jobload');
    Route::get('/jobload/uebersicht', [JobloadOverviewController::class, 'index'])->name('jobload.overview');
    Route::get('/jobload/uebersicht/wochenwerte', [JobloadOverviewController::class, 'weekDetail'])->name('jobload.overview.week-detail');
    Route::post('/jobload/stunden', [JobloadController::class, 'saveHours'])->name('jobload.hours');
    Route::post('/jobload/jobs', [JobloadController::class, 'saveJobs'])->name('jobload.jobs');
    Route::post('/jobload/wochenende', [JobloadController::class, 'saveWeekendPreference'])->name('jobload.weekend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/projekte', [ProjectController::class, 'index'])->name('projekte');
    Route::get('/projekte/mehr', [ProjectController::class, 'more'])->name('projekte.mehr');
    Route::get('/projekte/gantt/projekte', [ProjectController::class, 'ganttProjects'])->name('projekte.gantt.projekte');
    Route::get('/projekte/gantt/personen', ProjectGanttPeopleController::class)->name('projekte.gantt.personen');
    Route::get('/projekte/gantt/einstellungen', [ProjectGanttPreferenceController::class, 'show'])->name('projekte.gantt.einstellungen.show');
    Route::put('/projekte/gantt/einstellungen', [ProjectGanttPreferenceController::class, 'update'])->name('projekte.gantt.einstellungen.update');
    Route::get('/schnellsuche', [ProjectController::class, 'quickSearch'])->name('projekte.schnellsuche');

    Route::get('/projektgruppen', [ProjectGroupController::class, 'panel'])->name('projektgruppen.panel');
    Route::post('/projektgruppen', [ProjectGroupController::class, 'store'])->name('projektgruppen.store');
    Route::patch('/projektgruppen/{group}', [ProjectGroupController::class, 'update'])->name('projektgruppen.update');
    Route::delete('/projektgruppen/{group}', [ProjectGroupController::class, 'destroy'])->name('projektgruppen.destroy');
    Route::delete('/projektgruppen/{group}/verlassen', [ProjectGroupController::class, 'leave'])->name('projektgruppen.leave');
    Route::post('/projektgruppen/{group}/leeren', [ProjectGroupController::class, 'clear'])->name('projektgruppen.clear');
    Route::put('/projektgruppen/{group}/projekte/{project}', [ProjectGroupController::class, 'addProject'])->name('projektgruppen.projekte.add');
    Route::delete('/projektgruppen/{group}/projekte/{project}', [ProjectGroupController::class, 'removeProject'])->name('projektgruppen.projekte.remove');
    Route::post('/projektgruppen/{group}/alle', [ProjectGroupController::class, 'addAllFiltered'])->name('projektgruppen.alle.add');
    Route::delete('/projektgruppen/{group}/alle', [ProjectGroupController::class, 'removeAllFiltered'])->name('projektgruppen.alle.remove');
    Route::get('/projektgruppen/{group}/mitglieder', [ProjectGroupController::class, 'memberIds'])->name('projektgruppen.mitglieder');
    Route::get('/projektgruppen/{group}/personen', [ProjectGroupController::class, 'shareOptions'])->name('projektgruppen.personen');
    Route::post('/projektgruppen/{group}/personen/{user}', [ProjectGroupController::class, 'share'])->name('projektgruppen.personen.share');
    Route::delete('/projektgruppen/{group}/personen/{user}', [ProjectGroupController::class, 'unshare'])->name('projektgruppen.personen.unshare');
    Route::get('/projektgruppen/{group}/anzeigen', [ProjectGroupController::class, 'showInOverview'])->name('projektgruppen.anzeigen');
    Route::get('/projektgruppen/{group}/verbund', [VerbundController::class, 'panel'])->name('projektgruppen.verbund.panel');
    Route::post('/projektgruppen/{group}/verbund', [VerbundController::class, 'store'])->name('projektgruppen.verbund.store');
    Route::delete('/projektgruppen/{group}/verbund', [VerbundController::class, 'destroy'])->name('projektgruppen.verbund.destroy');
    // Ralf, 2026-09-13: "Das Gruppieren soll losgelöst sein davon" -
    // Multichange wählt seine Zielgruppe selbst im Formular, deshalb kein
    // {group}-Routenparameter (anders als alle /projektgruppen/{group}/...-
    // Routen oben). Vor /projekte/{project} registriert - sonst würde
    // "multichange" als Projekt-ID interpretiert.
    Route::get('/projekte/multichange', [MultichangeController::class, 'form'])->name('projekte.multichange.form');
    Route::post('/projekte/multichange/vorschau', [MultichangeController::class, 'preview'])->name('projekte.multichange.preview');
    Route::post('/projekte/multichange/anwenden', [MultichangeController::class, 'apply'])->name('projekte.multichange.apply');
    // Vor /projekte/{project} registriert - sonst würde "neu" als Projekt-ID interpretiert.
    Route::get('/projekte/neu', [ProjectController::class, 'createForm'])->name('projekte.create-form');
    Route::post('/projekte', [ProjectController::class, 'store'])->name('projekte.store');
    Route::get('/projekte/anfrage', [ProjectController::class, 'requestForm'])->name('projekte.request-form');
    Route::post('/projekte/anfrage', [ProjectController::class, 'submitRequest'])->name('projekte.request-submit');
    Route::get('/projekte/{project}', [ProjectController::class, 'show'])->name('projekte.show');
    Route::get('/projekte/{project}/projektbeteiligte', [ProjectController::class, 'peopleField'])->name('projekte.projektbeteiligte.show');
    Route::patch('/projekte/{project}', [ProjectController::class, 'update'])->name('projekte.update');
    Route::post('/projekte/{project}/favorite', [FavoriteController::class, 'toggle'])->name('projekte.favorite');
    Route::patch('/projekte/{project}/workflow-steps/{projectWorkflowStep}/due-date', [ProjectWorkflowStepController::class, 'updateDueDate'])->name('projekte.workflow-steps.due-date');
    Route::patch('/projekte/{project}/workflow-steps/{projectWorkflowStep}/freigabe', [ProjectWorkflowStepController::class, 'toggleFreigabe'])->name('projekte.workflow-steps.freigabe');
    Route::get('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activateForm'])->name('projekte.workflow-steps.activate-form');
    Route::post('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activate'])->name('projekte.workflow-steps.activate');
    Route::get('/projekte/{project}/workflow-steps/{projectWorkflowStep}/personen-anzeige', [ProjectWorkflowStepController::class, 'peopleSummary'])->name('projekte.workflow-steps.personen.summary');
    Route::get('/projekte/{project}/workflow-steps/{projectWorkflowStep}/personen/{functionGroup}', [ProjectWorkflowStepController::class, 'peopleForm'])->name('projekte.workflow-steps.personen.form');
    Route::post('/projekte/{project}/workflow-steps/{projectWorkflowStep}/personen/{functionGroup}', [ProjectWorkflowStepController::class, 'updatePeople'])->name('projekte.workflow-steps.personen.update');
    Route::post('/projekte/{project}/checklisten', [ProjectChecklistController::class, 'update'])->name('projekte.checklisten.update');
    Route::patch('/projekte/{project}/checklisten/punkte/{point}', [ProjectChecklistController::class, 'togglePoint'])->name('projekte.checklisten.punkte.toggle');

    Route::post('/projekte/{project}/format-kombination', [ProjectFormatController::class, 'saveCombination'])->name('projekte.format.kombination');
    Route::get('/projekte/{project}/produkte', [ProjectProductController::class, 'picker'])->name('projekte.produkte.picker');
    Route::get('/projekte/{project}/produkte/mehr', [ProjectProductController::class, 'more'])->name('projekte.produkte.mehr');
    Route::post('/projekte/{project}/produkte/{product}', [ProjectProductController::class, 'toggle'])->name('projekte.produkte.toggle');
    Route::get('/projekte/{project}/termine', [ProjectScheduleController::class, 'form'])->name('projekte.termine.form');
    Route::post('/projekte/{project}/termine/berechnen', [ProjectScheduleController::class, 'recalculate'])->name('projekte.termine.recalculate');
    Route::post('/projekte/{project}/termine/uebernehmen', [ProjectScheduleController::class, 'apply'])->name('projekte.termine.apply');
    Route::patch('/projekte/{project}/termine/{projectWorkflowStep}', [ProjectScheduleController::class, 'updateField'])->name('projekte.termine.update-field');
    Route::patch('/projekte/{project}/termine/{projectWorkflowStep}/start-end', [ProjectScheduleController::class, 'setStartEnd'])->name('projekte.termine.start-end');
    Route::get('/projekte/{project}/illustrationsauftraege', [GraphicOrderController::class, 'index'])->name('projekte.illustration-orders.index');
    Route::post('/projekte/{project}/illustrationsauftraege', [GraphicOrderController::class, 'store'])->name('projekte.illustration-orders.store');
    Route::patch('/projekte/{project}/illustrationsauftraege/{graphicOrder}', [GraphicOrderController::class, 'update'])->name('projekte.illustration-orders.update');

    Route::get('/projekte/{project}/verzeichnis', [ProjectDirectoryController::class, 'show'])->name('projekte.verzeichnis');
    Route::post('/projekte/{project}/verzeichnis', [ProjectDirectoryController::class, 'store'])->name('projekte.verzeichnis.store');

    Route::get('/projekte/{project}/kopieren', [ProjectCopyController::class, 'form'])->name('projekte.kopieren.form');
    Route::post('/projekte/{project}/kopieren', [ProjectCopyController::class, 'store'])->name('projekte.kopieren.store');

    Route::get('/projekte/{project}/verknuepfungen/neu', [ProjectConnectionController::class, 'form'])->name('projekte.verknuepfungen.form');
    Route::patch('/projekte/verknuepfungen/sortierung', [ProjectConnectionController::class, 'toggleSort'])->name('projekte.verknuepfungen.sortierung');
    Route::get('/projekte/{project}/verknuepfungen/mehr', [ProjectConnectionController::class, 'moreOtherProjects'])->name('projekte.verknuepfungen.mehr');
    Route::post('/projekte/{project}/verknuepfungen', [ProjectConnectionController::class, 'store'])->name('projekte.verknuepfungen.store');
    Route::delete('/projekte/{project}/verknuepfungen/{connection}', [ProjectConnectionController::class, 'destroy'])->name('projekte.verknuepfungen.destroy');

    Route::get('/projekte/{project}/notizen', [ProjectNoteController::class, 'index'])->name('projekte.notizen.index');
    Route::post('/projekte/{project}/notizen', [ProjectNoteController::class, 'store'])->name('projekte.notizen.store');
    Route::delete('/projekte/{project}/notizen/{note}', [ProjectNoteController::class, 'destroy'])->name('projekte.notizen.destroy');

    Route::get('/favoriten', [FavoriteController::class, 'index'])->name('favoriten');

    Route::get('/illustrationen', [IllustrationOverviewController::class, 'index'])->name('illustrationen');

    Route::get('/produkte', [ProductController::class, 'index'])->name('produkte');
    Route::get('/produkte/mehr', [ProductController::class, 'more'])->name('produkte.mehr');

    Route::get('/aufgaben', [TaskController::class, 'index'])->name('aufgaben');
    Route::post('/aufgaben/{task}/sichtbarkeit', [TaskController::class, 'toggleVisibility'])->name('aufgaben.visibility');

});

Route::middleware(['auth', 'verified', 'can:access-admin', RememberLastAdminPage::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/jobtypen', [JobTypeController::class, 'index'])->name('jobtypen');
    Route::post('/jobtypen/gruppen', [JobTypeController::class, 'storeGroup'])->name('jobtypen.gruppen.store');
    Route::post('/jobtypen/gruppen/reihenfolge', [JobTypeController::class, 'reorderGroups'])->name('jobtypen.gruppen.reorder');
    Route::post('/jobtypen/gruppen/{jobGroup}', [JobTypeController::class, 'updateGroup'])->name('jobtypen.gruppen.update');
    Route::post('/jobtypen', [JobTypeController::class, 'store'])->name('jobtypen.store');
    Route::post('/jobtypen/{jobType}', [JobTypeController::class, 'update'])->name('jobtypen.update');
    Route::redirect('/', '/admin/personen')->name('index');
    Route::get('/rechte', [AdminPermissionController::class, 'index'])->name('rechte');
    Route::post('/rechte/sets', [AdminPermissionController::class, 'store'])->name('rechte.sets.store');
    Route::post('/rechte/sets/reorder', [AdminPermissionController::class, 'reorderSets'])->name('rechte.sets.reorder');
    Route::post('/rechte/sets/{template}', [AdminPermissionController::class, 'update'])->name('rechte.sets.update');
    Route::delete('/rechte/sets/{template}', [AdminPermissionController::class, 'destroy'])->name('rechte.sets.destroy');
    Route::post('/rechte/sets/{template}/personen', [AdminPermissionController::class, 'assignPeopleToTemplate'])->name('rechte.sets.assign-people');
    Route::post('/rechte/personen/{person}', [AdminPermissionController::class, 'assignPerson'])->name('rechte.personen.update');

    Route::get('/geschaeftsbereiche', [BusinessUnitController::class, 'index'])->name('geschaeftsbereiche');
    Route::post('/geschaeftsbereiche', [BusinessUnitController::class, 'store'])->name('geschaeftsbereiche.store');
    Route::post('/geschaeftsbereiche/{businessUnit}', [BusinessUnitController::class, 'update'])->name('geschaeftsbereiche.update');
    Route::delete('/geschaeftsbereiche/{businessUnit}', [BusinessUnitController::class, 'destroy'])->name('geschaeftsbereiche.destroy');

    Route::get('/abteilungen', [DepartmentController::class, 'index'])->name('departments');
    Route::post('/abteilungen', [DepartmentController::class, 'store'])->name('departments.store');
    Route::post('/abteilungen/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/abteilungen/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::get('/rollen', [LegacyRoleController::class, 'index'])->name('legacy-roles');
    Route::post('/rollen', [LegacyRoleController::class, 'store'])->name('legacy-roles.store');
    Route::post('/rollen/{legacyRole}', [LegacyRoleController::class, 'update'])->name('legacy-roles.update');
    Route::delete('/rollen/{legacyRole}', [LegacyRoleController::class, 'destroy'])->name('legacy-roles.destroy');

    Route::get('/funktionsgruppen', [FunctionGroupController::class, 'index'])->name('function-groups');
    Route::post('/funktionsgruppen', [FunctionGroupController::class, 'store'])->name('function-groups.store');
    Route::post('/funktionsgruppen/{group}', [FunctionGroupController::class, 'update'])->name('function-groups.update');
    Route::delete('/funktionsgruppen/{group}', [FunctionGroupController::class, 'destroy'])->name('function-groups.destroy');
    Route::post('/funktionsgruppen/{group}/mitglieder', [FunctionGroupController::class, 'updateMembers'])->name('function-groups.members.update');
    Route::post('/funktionsgruppen/personen/{person}', [FunctionGroupController::class, 'updatePersonGroups'])->name('function-groups.personen.update');

    Route::get('/maerkte', [MarketController::class, 'index'])->name('maerkte');
    Route::post('/maerkte/gruppen', [MarketController::class, 'setsStore'])->name('maerkte.gruppen.store');
    Route::post('/maerkte/gruppen/{set}', [MarketController::class, 'setsUpdate'])->name('maerkte.gruppen.update');
    Route::delete('/maerkte/gruppen/{set}', [MarketController::class, 'setsDestroy'])->name('maerkte.gruppen.destroy');
    Route::post('/maerkte/gruppen/{set}/mitglieder', [MarketController::class, 'setsMembersUpdate'])->name('maerkte.gruppen.mitglieder.update');
    Route::post('/maerkte/{market}/keine-uebersetzung', [MarketController::class, 'toggleNoTranslation'])->name('maerkte.keine-uebersetzung.toggle');

    Route::get('/projektkategorien', [ProjectTypeController::class, 'index'])->name('projektkategorien');
    Route::post('/projektkategorien', [ProjectTypeController::class, 'mainStore'])->name('projektkategorien.store');
    // Feste Pfade (reorder/arten) vor den {category}/{sub}-Wildcards registriert -
    // sonst würden sie als ID interpretiert (404, schon mal passiert).
    Route::post('/projektkategorien/reorder', [ProjectTypeController::class, 'reorderMain'])->name('projektkategorien.reorder');
    Route::post('/projektkategorien/arten', [ProjectTypeController::class, 'subStore'])->name('projektkategorien.arten.store');
    Route::post('/projektkategorien/arten/reorder', [ProjectTypeController::class, 'reorderSub'])->name('projektkategorien.arten.reorder');
    Route::post('/projektkategorien/arten/{sub}', [ProjectTypeController::class, 'subUpdate'])->name('projektkategorien.arten.update');
    Route::delete('/projektkategorien/arten/{sub}', [ProjectTypeController::class, 'subDestroy'])->name('projektkategorien.arten.destroy');
    Route::post('/projektkategorien/{category}', [ProjectTypeController::class, 'mainUpdate'])->name('projektkategorien.update');
    Route::delete('/projektkategorien/{category}', [ProjectTypeController::class, 'mainDestroy'])->name('projektkategorien.destroy');

    Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows');
    Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
    // Feste Pfade (reorder/schritte) vor den {workflow}/{step}-Wildcards registriert -
    // sonst würden sie als ID interpretiert (404, gleiche Falle wie bei Projektkategorien).
    Route::post('/workflows/reorder', [WorkflowController::class, 'reorder'])->name('workflows.reorder');
    Route::post('/workflows/schritte', [WorkflowController::class, 'stepStore'])->name('workflows.schritte.store');
    Route::post('/workflows/schritte/reorder', [WorkflowController::class, 'stepReorder'])->name('workflows.schritte.reorder');
    Route::post('/workflows/schritte/speichern', [WorkflowController::class, 'stepsBulkUpdate'])->name('workflows.schritte.bulk-update');
    Route::delete('/workflows/schritte/{step}', [WorkflowController::class, 'stepDestroy'])->name('workflows.schritte.destroy');
    Route::post('/workflows/{workflow}/neue-version', [WorkflowController::class, 'newVersion'])->name('workflows.new-version');
    Route::post('/workflows/{workflow}/kopieren', [WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
    Route::post('/workflows/{workflow}/kopieren-zu', [WorkflowController::class, 'copyToTenant'])->name('workflows.copy-to-tenant');

    Route::get('/checklisten', [ChecklistController::class, 'index'])->name('checklisten');
    Route::post('/checklisten', [ChecklistController::class, 'store'])->name('checklisten.store');
    // Feste Pfade (reorder/abschnitte/punkte) vor den {checklist}/{section}/
    // {point}-Wildcards registriert - sonst würden sie als ID interpretiert
    // (gleiche Falle wie bei Projektkategorien/Workflows).
    Route::post('/checklisten/reorder', [ChecklistController::class, 'reorder'])->name('checklisten.reorder');
    Route::post('/checklisten/abschnitte', [ChecklistController::class, 'sectionStore'])->name('checklisten.abschnitte.store');
    Route::post('/checklisten/abschnitte/reorder', [ChecklistController::class, 'sectionReorder'])->name('checklisten.abschnitte.reorder');
    Route::post('/checklisten/abschnitte/{section}', [ChecklistController::class, 'sectionUpdate'])->name('checklisten.abschnitte.update');
    Route::delete('/checklisten/abschnitte/{section}', [ChecklistController::class, 'sectionDestroy'])->name('checklisten.abschnitte.destroy');
    Route::post('/checklisten/punkte', [ChecklistController::class, 'pointStore'])->name('checklisten.punkte.store');
    Route::post('/checklisten/punkte/reorder', [ChecklistController::class, 'pointReorder'])->name('checklisten.punkte.reorder');
    Route::post('/checklisten/punkte/{point}', [ChecklistController::class, 'pointUpdate'])->name('checklisten.punkte.update');
    Route::delete('/checklisten/punkte/{point}', [ChecklistController::class, 'pointDestroy'])->name('checklisten.punkte.destroy');
    Route::post('/checklisten/{checklist}/kopieren-zu', [ChecklistController::class, 'copyToTenant'])->name('checklisten.copy-to-tenant');
    Route::post('/checklisten/{checklist}', [ChecklistController::class, 'update'])->name('checklisten.update');
    Route::delete('/checklisten/{checklist}', [ChecklistController::class, 'destroy'])->name('checklisten.destroy');

    Route::get('/mail-vorlagen', [MailTemplateController::class, 'index'])->name('mail-vorlagen');
    Route::post('/mail-vorlagen', [MailTemplateController::class, 'store'])->name('mail-vorlagen.store');
    Route::post('/mail-vorlagen/{mailTemplate}', [MailTemplateController::class, 'update'])->name('mail-vorlagen.update');
    Route::delete('/mail-vorlagen/{mailTemplate}', [MailTemplateController::class, 'destroy'])->name('mail-vorlagen.destroy');

    Route::get('/projektattribute', [AttributeController::class, 'index'])->name('projektattribute');
    Route::post('/projektattribute', [AttributeController::class, 'store'])->name('projektattribute.store');
    // Fester Pfad vor dem {attribute}-Wildcard registriert - sonst würde
    // "reorder" als ID interpretiert (gleiche Falle wie bei Workflows/
    // Projektkategorien).
    Route::post('/projektattribute/reorder', [AttributeController::class, 'reorder'])->name('projektattribute.reorder');
    Route::post('/projektattribute/{attribute}', [AttributeController::class, 'update'])->name('projektattribute.update');
    Route::delete('/projektattribute/{attribute}', [AttributeController::class, 'destroy'])->name('projektattribute.destroy');
    Route::post('/projektattribute/{attribute}/pulldown', [AttributeController::class, 'updatePulldown'])->name('projektattribute.pulldown.update');
    Route::post('/projektattribute/{attribute}/projektart', [AttributeController::class, 'toggleProjectType'])->name('projektattribute.projektart.toggle');

    Route::get('/papierformate', [PaperFormatController::class, 'index'])->name('papierformate');
    Route::get('/papierformate/katalog', [PaperFormatController::class, 'catalog'])->name('papierformate.katalog');
    Route::post('/papierformate/uebernehmen', [PaperFormatController::class, 'importFromTenant'])->name('papierformate.uebernehmen');
    Route::post('/papierformate', [PaperFormatController::class, 'store'])->name('papierformate.store');
    // Fester Pfad vor dem {paperFormat}-Wildcard registriert - sonst würde
    // "reorder" als ID interpretiert (gleiche Falle wie bei Workflows/
    // Projektkategorien/Projektattributen).
    Route::post('/papierformate/reorder', [PaperFormatController::class, 'reorder'])->name('papierformate.reorder');
    Route::post('/papierformate/{paperFormat}', [PaperFormatController::class, 'update'])->name('papierformate.update');
    Route::delete('/papierformate/{paperFormat}', [PaperFormatController::class, 'destroy'])->name('papierformate.destroy');

    Route::post('/papierformate-kombinationen', [PaperFormatCombinationController::class, 'store'])->name('papierformate.kombinationen.store');
    Route::post('/papierformate-kombinationen/{combination}', [PaperFormatCombinationController::class, 'update'])->name('papierformate.kombinationen.update');
    Route::delete('/papierformate-kombinationen/{combination}', [PaperFormatCombinationController::class, 'destroy'])->name('papierformate.kombinationen.destroy');

    Route::get('/projektkopie-vorlagen', [CopyTemplateController::class, 'index'])->name('projektkopie-vorlagen');
    Route::post('/projektkopie-vorlagen', [CopyTemplateController::class, 'store'])->name('projektkopie-vorlagen.store');
    Route::post('/projektkopie-vorlagen/max-kopien', [CopyTemplateController::class, 'updateMaxCopies'])->name('projektkopie-vorlagen.max-kopien.update');
    Route::post('/projektkopie-vorlagen/{template}', [CopyTemplateController::class, 'update'])->name('projektkopie-vorlagen.update');
    Route::delete('/projektkopie-vorlagen/{template}', [CopyTemplateController::class, 'destroy'])->name('projektkopie-vorlagen.destroy');
    Route::post('/projektkopie-vorlagen/{template}/feld', [CopyTemplateController::class, 'toggleField'])->name('projektkopie-vorlagen.feld.toggle');
    Route::post('/projektkopie-vorlagen/{template}/alle-markieren', [CopyTemplateController::class, 'markAll'])->name('projektkopie-vorlagen.alle-markieren');
    Route::post('/projektkopie-vorlagen/{template}/keinen-markieren', [CopyTemplateController::class, 'markNone'])->name('projektkopie-vorlagen.keinen-markieren');

    Route::post('/workflows/{workflow}/veroeffentlichen', [WorkflowController::class, 'publish'])->name('workflows.publish');
    Route::post('/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');

    Route::get('/firmen', [CompanyController::class, 'index'])->name('companies');
    Route::post('/firmen', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/firmen/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::delete('/firmen/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

    Route::get('/personen', [AdminPersonController::class, 'index'])->name('personen');
    Route::post('/personen', [AdminPersonController::class, 'store'])->name('personen.store');
    Route::get('/personen/zugriffsmatrix', [AdminPersonController::class, 'accessMatrix'])->name('personen.zugriffsmatrix');
    Route::get('/personen/{person}', [AdminPersonController::class, 'edit'])->name('personen.edit');
    Route::post('/personen/{person}', [AdminPersonController::class, 'update'])->name('personen.update');
    Route::post('/personen/{person}/login', [AdminPersonController::class, 'createLogin'])->name('personen.login.store');
    Route::post('/personen/{person}/passwort', [AdminPersonController::class, 'resetPassword'])->name('personen.password.reset');
    Route::post('/personen/{person}/kunden', [AdminPersonController::class, 'updateTenantAccess'])->name('personen.tenant-access.update');
    Route::post('/personen/{person}/rolle', [AdminPersonController::class, 'updateRole'])->name('personen.role.update');
    Route::delete('/personen/{person}', [AdminPersonController::class, 'destroy'])->name('personen.destroy');

    Route::get('/konfig', [ConfigController::class, 'index'])->name('config');

    Route::get('/kunden', [TenantController::class, 'index'])->name('kunden');
    Route::post('/kunden', [TenantController::class, 'store'])->name('kunden.store');
    Route::post('/kunden/{tenant}', [TenantController::class, 'update'])->name('kunden.update');
    Route::delete('/kunden/{tenant}', [TenantController::class, 'destroy'])->name('kunden.destroy');
});

Route::middleware(['auth', 'verified', 'can:access-superadmin', RememberLastAdminPage::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/superadmin', [SuperAdminController::class, 'index'])->name('superadmin');
    Route::post('/superadmin', [SuperAdminController::class, 'update'])->name('superadmin.update');
    Route::get('/superadmin/uebersetzung', [SuperAdminController::class, 'downloadTranslations'])->name('superadmin.uebersetzung.download');
    Route::post('/superadmin/uebersetzung', [SuperAdminController::class, 'uploadTranslations'])->name('superadmin.uebersetzung.upload');

    Route::get('/hilfeseiten', [HelpArticleController::class, 'index'])->name('hilfeseiten');
    Route::post('/hilfeseiten', [HelpArticleController::class, 'store'])->name('hilfeseiten.store');
    Route::post('/hilfeseiten/reorder', [HelpArticleController::class, 'reorder'])->name('hilfeseiten.reorder');
    Route::post('/hilfeseiten/vorschau', [HelpArticleController::class, 'preview'])->name('hilfeseiten.vorschau');
    Route::post('/hilfeseiten/{helpArticle}', [HelpArticleController::class, 'update'])->name('hilfeseiten.update');
    Route::delete('/hilfeseiten/{helpArticle}', [HelpArticleController::class, 'destroy'])->name('hilfeseiten.destroy');
    Route::post('/hilfeseiten/{helpArticle}/einruecken', [HelpArticleController::class, 'indent'])->name('hilfeseiten.einruecken');
    Route::post('/hilfeseiten/{helpArticle}/ausruecken', [HelpArticleController::class, 'outdent'])->name('hilfeseiten.ausruecken');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/mandant-wechseln', [TenantSwitchController::class, 'update'])->name('mandant.wechseln');
    Route::post('/sprache-wechseln', [LocaleSwitchController::class, 'update'])->name('sprache.wechseln');
});

Route::middleware(['auth', 'verified'])->prefix('projekte/anzeigefilter')->name('projekte.anzeigefilter.')->group(function () {
    Route::post('/', [DisplayFilterController::class, 'update'])->name('update');
    Route::post('/sets', [DisplayFilterController::class, 'store'])->name('sets.store');
    Route::post('/sets/{displayFilterSet}/activate', [DisplayFilterController::class, 'activate'])->name('sets.activate');
    Route::delete('/sets/{displayFilterSet}', [DisplayFilterController::class, 'destroy'])->name('sets.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/einstellungen', [SettingsController::class, 'index'])->name('settings');
    Route::post('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/einstellungen/abwesenheit', [SettingsController::class, 'updateAbsence'])->name('settings.absence.update');

    // Hilfesystem: für jeden eingeloggten Nutzer erreichbar (erklärt das
    // Tool selbst), Pflege der Artikel läuft separat unter Admin (siehe
    // unten, Super-Admin-only).
    Route::get('/hilfe', [HelpController::class, 'results'])->name('hilfe');
    Route::get('/hilfe/artikel/{helpArticle:key}', [HelpController::class, 'show'])->name('hilfe.artikel');
});

require __DIR__.'/auth.php';
