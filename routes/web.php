<?php

use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
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
    Route::redirect('/', '/admin/rechte')->name('index');
    Route::get('/rechte', [AdminPermissionController::class, 'index'])->name('rechte');
    Route::post('/rechte/funktionsgruppen/{group}', [AdminPermissionController::class, 'updateFunctionGroup'])->name('rechte.funktionsgruppen.update');
    Route::post('/rechte/personen/{person}', [AdminPermissionController::class, 'updatePerson'])->name('rechte.personen.update');
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
