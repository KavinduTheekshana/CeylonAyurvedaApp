<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\TreatmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DeleteAccountController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/verify-email', [UserController::class, 'verifyEmail']);
Route::post('/resend-verification', [UserController::class, 'resendVerificationCode']);
Route::post('/login', [UserController::class, 'login']);

// Password Reset Routes
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/verify-reset-code', [PasswordResetController::class, 'verifyResetCode']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('/resend-reset-code', [PasswordResetController::class, 'resendResetCode']);

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


// Time slots
Route::get('timeslots', [TimeSlotController::class, 'getAvailableSlots']);

// User addresses
Route::middleware('auth:sanctum')->group(function() {
    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{id}', [AddressController::class, 'update']);
    Route::delete('addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('addresses/{id}/make-default', [AddressController::class, 'makeDefault']);

    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/password/update', [UserController::class, 'updatePassword']);
});

// Bookings
Route::post('bookings', [BookingController::class, 'store']);
Route::get('bookings/{id}', [BookingController::class, 'show']);

// User bookings (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/bookings', [BookingController::class, 'userBookings']);
});

// Service routes
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{treatmentId}', [ServiceController::class, 'getServicesForTreatment']);
Route::get('/services/detail/{serviceId}/with-bookings', [ServiceController::class, 'getServiceWithBookingCount']);
Route::get('/services/detail/{id}', [ServiceController::class, 'detail']);

// Admin only routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin service management
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
});


// Add these to your existing routes/api.php file

Route::middleware('auth:sanctum')->group(function () {
    // Booking routes
    Route::get('/auth/bookings/list', [BookingController::class, 'getUserBookingsList']);
    Route::get('/bookings/show/{id}', [BookingController::class, 'showBooking']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);

    // Delete account route
    Route::post('/account/delete', [DeleteAccountController::class, 'deleteAccount']);
});