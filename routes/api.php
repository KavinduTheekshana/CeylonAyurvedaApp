<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\TreatmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DeleteAccountController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TherapistController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('user/save', [UserController::class, 'register']);
Route::post('/verify-email', [UserController::class, 'verifyEmail']);
Route::post('/resend-verification', [UserController::class, 'resendVerificationCode']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/auth/social/login', [SocialAuthController::class, 'socialLogin']);

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
});



// Service routes
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{treatmentId}', [ServiceController::class, 'getServicesForTreatment']);
Route::get('/services/detail/{serviceId}/with-bookings', [ServiceController::class, 'getServiceWithBookingCount']);
Route::get('/services/detail/{id}', [ServiceController::class, 'detail']);

// Therapist routes
Route::get('/services/{serviceId}/therapists', [TherapistController::class, 'getServiceTherapists']);


// Time slots and availability routes
Route::get('/timeslots', [TimeSlotController::class, 'getAvailableSlots']);
Route::get('/therapists/available', [TimeSlotController::class, 'getAvailableTherapists']);
Route::get('/therapists/{therapistId}/available-dates', [TimeSlotController::class, 'getAvailableDates']);
Route::get('/therapists/{therapistId}/schedule', [TimeSlotController::class, 'getTherapistSchedule']);
Route::post('/timeslots/check-availability', [TimeSlotController::class, 'checkSlotAvailability']);
Route::get('/therapists/{therapistId}/workload', [TimeSlotController::class, 'getTherapistWorkload']);

// User addresses
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/make-default', [AddressController::class, 'makeDefault']);

    Route::post('/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/password/update', [UserController::class, 'updatePassword']);
});

// Bookings
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/{id}', [BookingController::class, 'show']);

// User bookings (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/bookings', [BookingController::class, 'userBookings']);
    Route::get('/auth/bookings/list', [BookingController::class, 'getUserBookingsList']);
    Route::get('/bookings/show/{id}', [BookingController::class, 'showBooking']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);



    // Delete account route
    Route::post('/account/delete', [DeleteAccountController::class, 'deleteAccount']);
});

// Admin only routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin service management
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
});

Route::get('/therapists/{therapistId}/bookings', [TherapistController::class, 'getTherapistBookings']);
Route::get('/therapists/details/{id}', [TherapistController::class, 'show']);


// Treatment routes
// Route::get('/treatments', [TreatmentController::class, 'index']);
Route::get('/treatments/{id}', [TreatmentController::class, 'show']);
// routes/api.php - Add these routes
Route::get('locations', [LocationController::class, 'index']);
Route::get('locations/{id}', [LocationController::class, 'show']);

// Update existing routes to include location filtering
Route::get('treatments/{location_id?}', [TreatmentController::class, 'index']);
Route::get('services/{treatmentId}/{location_id?}', [ServiceController::class, 'getServicesForTreatment']);


Route::prefix('contact')->group(function () {
    // Public route for submitting messages (both guest and authenticated users)


    // Protected routes for authenticated users
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/message', [ContactMessageController::class, 'store']);
        Route::get('/messages', [ContactMessageController::class, 'getUserMessages']);

        Route::get('/messages/{id}', [ContactMessageController::class, 'show']);
    });
});


// Public routes (no auth required)
Route::get('/test', [InvestmentController::class, 'test']);
Route::get('/investments/opportunities', [InvestmentController::class, 'getOpportunities']);
Route::get('/locations/{locationId}', [InvestmentController::class, 'getLocationDetails']);

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // Alternative location routes for investment data
    Route::get('/locations/{locationId}/investments', [LocationController::class, 'getLocationInvestments']);

    // Location-specific investment routes
    Route::get('/investments/locations/{locationId}', [InvestmentController::class, 'getLocationInvestmentDetails']);
    Route::get('/investments/locations/{locationId}/investors', [InvestmentController::class, 'getLocationInvestors']);
   
     // Investment CRUD
     Route::post('/investments', [InvestmentController::class, 'createInvestment']);
     Route::get('/investments', [InvestmentController::class, 'getUserInvestments']);
     Route::get('/investments/{investmentId}', [InvestmentController::class, 'getInvestmentDetails']);

     // Payment processing
    Route::post('/investments/confirm-payment', [InvestmentController::class, 'confirmPayment']);
    Route::post('/investments/{investmentId}/refund', [InvestmentController::class, 'refundInvestment']);

    // Investment routes
    // Route::prefix('investments')->group(function () {
    //     Route::get('/', [InvestmentController::class, 'index']);
    //     Route::post('/', [InvestmentController::class, 'store']);
    //     Route::get('/summary', [InvestmentController::class, 'getUserSummary']);
    //     Route::get('/{investment}', [InvestmentController::class, 'show']);
    //     Route::post('/confirm-payment', [InvestmentController::class, 'confirmPayment']);
    // });
});

// Webhook routes (no auth, but should be protected by webhook signature)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);
