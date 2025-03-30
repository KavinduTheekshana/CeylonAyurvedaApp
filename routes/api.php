<?php

use App\Http\Controllers\Api\TreatmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/treatments', [TreatmentController::class, 'index']);
Route::get('/treatments/{id}', [TreatmentController::class, 'show']);

Route::get('/services/{treatmentId}', [ServiceController::class, 'getServicesForTreatment']);
