<?php

use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FunctionGroupController;
use App\Http\Middleware\RememberLastAdminPage;
use App\Http\Controllers\Admin\MarketController;
use App\Http\Controllers\Admin\LegacyRoleController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PersonController as AdminPersonController;
use App\Http\Controllers\Admin\ProjectTypeController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayFilterController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GraphicOrderController;
use App\Http\Controllers\IllustrationOverviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectScheduleController;
use App\Http\Controllers\ProjectDirectoryController;
use App\Http\Controllers\ProjectWorkflowStepController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantSwitchController;
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
    Route::get('/projekte', [ProjectController::class, 'index'])->name('projekte');
    Route::get('/schnellsuche', [ProjectController::class, 'quickSearch'])->name('projekte.schnellsuche');
    // Vor /projekte/{project} registriert - sonst würde "neu" als Projekt-ID interpretiert.
    Route::get('/projekte/neu', [ProjectController::class, 'createForm'])->name('projekte.create-form');
    Route::post('/projekte', [ProjectController::class, 'store'])->name('projekte.store');
    Route::get('/projekte/anfrage', [ProjectController::class, 'requestForm'])->name('projekte.request-form');
    Route::post('/projekte/anfrage', [ProjectController::class, 'submitRequest'])->name('projekte.request-submit');
    Route::get('/projekte/{project}', [ProjectController::class, 'show'])->name('projekte.show');
    Route::patch('/projekte/{project}', [ProjectController::class, 'update'])->name('projekte.update');
    Route::post('/projekte/{project}/favorite', [FavoriteController::class, 'toggle'])->name('projekte.favorite');
    Route::patch('/projekte/{project}/workflow-steps/{projectWorkflowStep}/due-date', [ProjectWorkflowStepController::class, 'updateDueDate'])->name('projekte.workflow-steps.due-date');
    Route::patch('/projekte/{project}/workflow-steps/{projectWorkflowStep}/freigabe', [ProjectWorkflowStepController::class, 'toggleFreigabe'])->name('projekte.workflow-steps.freigabe');
    Route::get('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activateForm'])->name('projekte.workflow-steps.activate-form');
    Route::post('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activate'])->name('projekte.workflow-steps.activate');
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

    Route::get('/favoriten', [FavoriteController::class, 'index'])->name('favoriten');

    Route::get('/illustrationen', [IllustrationOverviewController::class, 'index'])->name('illustrationen');

    Route::get('/aufgaben', [TaskController::class, 'index'])->name('aufgaben');
    Route::post('/aufgaben/{task}/sichtbarkeit', [TaskController::class, 'toggleVisibility'])->name('aufgaben.visibility');

});

Route::middleware(['auth', 'verified', 'can:access-admin', RememberLastAdminPage::class])->prefix('admin')->name('admin.')->group(function () {
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

    Route::get('/mail-vorlagen', [MailTemplateController::class, 'index'])->name('mail-vorlagen');
    Route::post('/mail-vorlagen', [MailTemplateController::class, 'store'])->name('mail-vorlagen.store');
    Route::post('/mail-vorlagen/{mailTemplate}', [MailTemplateController::class, 'update'])->name('mail-vorlagen.update');
    Route::delete('/mail-vorlagen/{mailTemplate}', [MailTemplateController::class, 'destroy'])->name('mail-vorlagen.destroy');

    Route::get('/projektattribute', [AttributeController::class, 'index'])->name('projektattribute');
    Route::post('/projektattribute', [AttributeController::class, 'store'])->name('projektattribute.store');
    Route::post('/projektattribute/{attribute}', [AttributeController::class, 'update'])->name('projektattribute.update');
    Route::delete('/projektattribute/{attribute}', [AttributeController::class, 'destroy'])->name('projektattribute.destroy');
    Route::post('/projektattribute/{attribute}/optionen', [AttributeController::class, 'storeOption'])->name('projektattribute.optionen.store');
    Route::post('/projektattribute/optionen/{option}', [AttributeController::class, 'updateOption'])->name('projektattribute.optionen.update');
    Route::delete('/projektattribute/optionen/{option}', [AttributeController::class, 'destroyOption'])->name('projektattribute.optionen.destroy');
    Route::post('/projektattribute/{attribute}/projektart', [AttributeController::class, 'toggleProjectType'])->name('projektattribute.projektart.toggle');
    Route::post('/workflows/{workflow}/veroeffentlichen', [WorkflowController::class, 'publish'])->name('workflows.publish');
    Route::post('/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');

    Route::get('/firmen', [CompanyController::class, 'index'])->name('companies');
    Route::post('/firmen', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/firmen/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::delete('/firmen/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

    Route::get('/personen', [AdminPersonController::class, 'index'])->name('personen');
    Route::post('/personen', [AdminPersonController::class, 'store'])->name('personen.store');
    Route::get('/personen/{person}', [AdminPersonController::class, 'edit'])->name('personen.edit');
    Route::post('/personen/{person}', [AdminPersonController::class, 'update'])->name('personen.update');
    Route::post('/personen/{person}/login', [AdminPersonController::class, 'createLogin'])->name('personen.login.store');
    Route::post('/personen/{person}/passwort', [AdminPersonController::class, 'resetPassword'])->name('personen.password.reset');
    Route::post('/personen/{person}/kunden', [AdminPersonController::class, 'updateTenantAccess'])->name('personen.tenant-access.update');
    Route::post('/personen/{person}/rolle', [AdminPersonController::class, 'updateRole'])->name('personen.role.update');
    Route::delete('/personen/{person}', [AdminPersonController::class, 'destroy'])->name('personen.destroy');

    Route::get('/konfig', [ConfigController::class, 'index'])->name('config');
    Route::post('/konfig', [ConfigController::class, 'update'])->name('config.update');

    Route::get('/kunden', [TenantController::class, 'index'])->name('kunden');
    Route::post('/kunden', [TenantController::class, 'store'])->name('kunden.store');
    Route::post('/kunden/{tenant}', [TenantController::class, 'update'])->name('kunden.update');
    Route::delete('/kunden/{tenant}', [TenantController::class, 'destroy'])->name('kunden.destroy');
});

Route::middleware(['auth', 'verified', 'can:access-superadmin', RememberLastAdminPage::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/superadmin', [SuperAdminController::class, 'index'])->name('superadmin');
    Route::post('/superadmin', [SuperAdminController::class, 'update'])->name('superadmin.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/mandant-wechseln', [TenantSwitchController::class, 'update'])->name('mandant.wechseln');
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
});

require __DIR__.'/auth.php';
