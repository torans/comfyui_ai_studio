<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\WorkflowTemplateController;
use App\Http\Controllers\Admin\WorkflowsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return redirect('/login');
})->name('home');

// 授权过期页面（不需要认证）
Route::get('/license-expired', function () {
    return inertia('license/expired');
})->name('license.expired');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    
    // 工作流管理
    Route::get('/admin/workflows', [WorkflowsController::class, 'index'])->name('admin.workflows');
    Route::get('/admin/workflows/create', [WorkflowTemplateController::class, 'create'])->name('admin.workflows.create');
    Route::get('/admin/workflows/{workflow_template}/edit', [WorkflowTemplateController::class, 'edit'])->name('admin.workflows.edit');
    Route::post('/admin/workflows', [WorkflowTemplateController::class, 'store'])->name('admin.workflows.store');
    Route::put('/admin/workflows/{workflow_template}', [WorkflowTemplateController::class, 'update'])->name('admin.workflows.update');
    Route::post('/admin/workflows/{workflow}/start', [WorkflowsController::class, 'start'])->name('admin.workflows.start');
    Route::post('/admin/workflows/{workflow}/stop', [WorkflowsController::class, 'stop'])->name('admin.workflows.stop');
    
    // 员工管理
    Route::get('/admin/employees', [EmployeeController::class, 'index'])->name('admin.employees.index');
    Route::get('/admin/employees/create', [EmployeeController::class, 'create'])->name('admin.employees.create');
    Route::get('/admin/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('admin.employees.edit');
    Route::post('/admin/employees', [EmployeeController::class, 'store'])->name('admin.employees.store');
    Route::put('/admin/employees/{employee}', [EmployeeController::class, 'update'])->name('admin.employees.update');
    Route::delete('/admin/employees/{employee}', [EmployeeController::class, 'destroy'])->name('admin.employees.destroy');
});

require __DIR__.'/settings.php';
