<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseValidationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Endpoint nuevo con protección anti-piratería por installation_id
Route::post('/validate', [LicenseValidationController::class, 'validateKey']);
