<?php

use App\Http\Controllers\Api\TreatmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Add other protected routes here
});

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/treatments', [TreatmentController::class, 'index']);
Route::get('/treatments/{id}', [TreatmentController::class, 'show']);

Route::get('/services/{treatmentId}', [ServiceController::class, 'getServicesForTreatment']);
