<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ReportController;

// PÚBLICAS (sin token)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// PROTEGIDAS (requiere Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    
    // PERFIL
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile/upgrade', [ProfileController::class, 'upgrade']);
    
    // REPORTES
    Route::post('reports', [ReportController::class, 'generate']);
    Route::get('reports', [ReportController::class, 'index']);
    Route::get('reports/{id}', [ReportController::class, 'show']);
});
