<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WorkflowTemplateController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ComfyUiProxyController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 文件上传
    Route::post('/uploads/images', [UploadController::class, 'image']);
    Route::post('/comfyui/uploads/images', [UploadController::class, 'comfyImage']);

    // ComfyUI 代理
    Route::get('/comfyui/system-stats', [ComfyUiProxyController::class, 'systemStats']);
    Route::get('/comfyui/models', [ComfyUiProxyController::class, 'models']);
    Route::post('/comfyui/upload-image', [ComfyUiProxyController::class, 'uploadImage']);
    Route::get('/comfyui/history/{promptId}', [ComfyUiProxyController::class, 'history']);

    // 工作流
    Route::get('/workflows', [WorkflowTemplateController::class, 'index']);
    Route::get('/workflows/{workflow_template}', [WorkflowTemplateController::class, 'show']);
    Route::post('/workflow-templates/{workflowTemplate}/start', [WorkflowTemplateController::class, 'start']);
    Route::post('/workflow-templates/{workflowTemplate}/stop', [WorkflowTemplateController::class, 'stop']);
    Route::apiResource('workflow-templates', WorkflowTemplateController::class)->only(['index', 'show']);

    // 任务
    Route::apiResource('generation-jobs', \App\Http\Controllers\Api\GenerationJobController::class);
});
