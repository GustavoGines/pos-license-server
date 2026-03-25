<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\LicenseValidationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Endpoint legado (mantener para compatibilidad con versiones anteriores del Sistema-POS)
Route::post('/check-license', [LicenseController::class, 'check']);

// Endpoint nuevo con protección anti-piratería por installation_id
Route::post('/validate', [LicenseValidationController::class, 'validateKey']);
