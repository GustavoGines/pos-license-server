<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseValidationController;
use App\Http\Controllers\Api\ReleaseController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Endpoint nuevo con protección anti-piratería por installation_id
Route::post('/validate', [LicenseValidationController::class, 'validateKey']);

// Endpoint para consultar actualizaciones (Público)
Route::get('/check-update', [ReleaseController::class, 'checkUpdate']);

// Endpoint para que el CI/CD registre un nuevo release (Protegido por token secreto)
Route::post('/releases/new', [ReleaseController::class, 'store']);
