<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GenerationJobController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/generation-jobs', [GenerationJobController::class, 'index']);
    Route::post('/generation-jobs', [GenerationJobController::class, 'store']);
});
