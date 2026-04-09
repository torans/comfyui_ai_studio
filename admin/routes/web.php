<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\WorkflowTemplateController;
use App\Http\Controllers\Admin\WorkflowsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/workflows', [WorkflowsController::class, 'index'])->name('admin.workflows');
    Route::get('/admin/workflows/create', [WorkflowTemplateController::class, 'create'])->name('admin.workflows.create');
    Route::get('/admin/workflows/{workflow_template}/edit', [WorkflowTemplateController::class, 'edit'])->name('admin.workflows.edit');
    Route::post('/admin/workflows', [WorkflowTemplateController::class, 'store'])->name('admin.workflows.store');
    Route::put('/admin/workflows/{workflow_template}', [WorkflowTemplateController::class, 'update'])->name('admin.workflows.update');
});

require __DIR__.'/settings.php';
