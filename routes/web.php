<?php

use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FunctionGroupController;
use App\Http\Controllers\Admin\LegacyRoleController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PersonController as AdminPersonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayFilterController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GraphicOrderController;
use App\Http\Controllers\IllustrationOverviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectWorkflowStepController;
use App\Http\Controllers\TaskController;
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
    Route::get('/projekte/{project}', [ProjectController::class, 'show'])->name('projekte.show');
    Route::patch('/projekte/{project}', [ProjectController::class, 'update'])->name('projekte.update');
    Route::post('/projekte/{project}/favorite', [FavoriteController::class, 'toggle'])->name('projekte.favorite');
    Route::patch('/projekte/{project}/workflow-steps/{projectWorkflowStep}/due-date', [ProjectWorkflowStepController::class, 'updateDueDate'])->name('projekte.workflow-steps.due-date');
    Route::get('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activateForm'])->name('projekte.workflow-steps.activate-form');
    Route::post('/projekte/{project}/workflow-steps/{projectWorkflowStep}/activate', [ProjectWorkflowStepController::class, 'activate'])->name('projekte.workflow-steps.activate');
    Route::get('/projekte/{project}/illustrationsauftraege', [GraphicOrderController::class, 'index'])->name('projekte.illustration-orders.index');
    Route::post('/projekte/{project}/illustrationsauftraege', [GraphicOrderController::class, 'store'])->name('projekte.illustration-orders.store');
    Route::patch('/projekte/{project}/illustrationsauftraege/{graphicOrder}', [GraphicOrderController::class, 'update'])->name('projekte.illustration-orders.update');

    Route::get('/favoriten', [FavoriteController::class, 'index'])->name('favoriten');

    Route::get('/illustrationen', [IllustrationOverviewController::class, 'index'])->name('illustrationen');

    Route::get('/aufgaben', [TaskController::class, 'index'])->name('aufgaben');
    Route::post('/aufgaben/{task}/sichtbarkeit', [TaskController::class, 'toggleVisibility'])->name('aufgaben.visibility');

});

Route::middleware(['auth', 'verified', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
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
});

require __DIR__.'/auth.php';
