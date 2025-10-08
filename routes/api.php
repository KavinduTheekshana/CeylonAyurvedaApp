<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\TherapistAuthController;
use App\Http\Controllers\Api\TherapistBookingController;
use App\Http\Controllers\Api\TherapistPatientController;
use App\Http\Controllers\Api\TherapistPreferencesController;
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
use App\Http\Controllers\Api\FCMTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserPreferencesController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Api\PatientTreatmentHistoryController;
use App\Http\Controllers\Api\TherapistFCMTokenController;
use App\Http\Controllers\Api\TherapistChatController;




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

// Get services for a specific therapist (public endpoint)
Route::get('/therapists/{therapist}/services', [TherapistController::class, 'getTherapistServices']);

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

    // Add User Preferences Routes
    Route::prefix('user/preferences')->group(function () {
        Route::get('/', [UserPreferencesController::class, 'getPreferences']);
        Route::post('/', [UserPreferencesController::class, 'updatePreferences']);
        Route::post('/reset', [UserPreferencesController::class, 'resetPreferences']);
    });
});

// Bookings
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
});
Route::get('/bookings/{id}', [BookingController::class, 'show']);
Route::post('/bookings/confirm-payment', [BookingController::class, 'confirmPayment']);

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
Route::get('/opportunities/{locationId}', [InvestmentController::class, 'getOpportunity']);
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
    Route::post('/investments/create', [InvestmentController::class, 'createInvestment']);
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


Route::middleware('auth:sanctum')->group(function () {
    // Your existing routes...

    // Chat routes
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats/therapist/{therapistId}', [ChatController::class, 'createOrAccess']);
    Route::get('/chats/{chatRoomId}/messages', [ChatController::class, 'getMessages']);
    Route::post('/chats/{chatRoomId}/messages', [ChatController::class, 'sendMessage']);
    Route::get('/chats/stats', [ChatController::class, 'getStats']);
});


// Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
Route::get('/services/{serviceId}/coupons', [CouponController::class, 'getServiceCoupons']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    Route::get('/user/coupon-history', [CouponController::class, 'getUserCouponHistory']);
});

// Webhook routes (no auth, but should be protected by webhook signature)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);



Route::prefix('therapist')->group(function () {

    // Public Authentication Routes
    Route::post('login', [TherapistAuthController::class, 'login']);
    Route::post('register', [TherapistAuthController::class, 'register']);


    Route::post('/registertherapist', [TherapistController::class, 'register']);
    Route::post('/verify-otp', [TherapistController::class, 'verifyOtp']);
    Route::post('/resend-otp', [TherapistController::class, 'resendOtp']);

    // Password Reset Routes  
    Route::post('forgot-password', [TherapistAuthController::class, 'forgotPassword']);
    Route::post('verify-reset-code', [TherapistAuthController::class, 'verifyResetCode']);
    Route::post('reset-password', [TherapistAuthController::class, 'resetPassword']);


    // Protected Routes (Require Authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('account', [TherapistController::class, 'deleteAccount']);
        Route::get('account/deletion-info', [TherapistController::class, 'getAccountDeletionInfo']);

        Route::post('fcm-token', [TherapistFCMTokenController::class, 'store']);
        Route::delete('fcm-token', [TherapistFCMTokenController::class, 'destroy']);

        Route::prefix('preferences')->group(function () {
            Route::get('/', [TherapistPreferencesController::class, 'getPreferences']);
            Route::post('/', [TherapistPreferencesController::class, 'updatePreferences']);
            Route::post('/reset', [TherapistPreferencesController::class, 'resetPreferences']);
        });

        Route::post('change-password', [TherapistAuthController::class, 'changePassword']);
        // In the therapist protected routes section
        Route::post('profile', [TherapistAuthController::class, 'updateProfile']);

        // Profile & Authentication
        Route::get('profile', [TherapistAuthController::class, 'profile']);
        Route::post('profile/update', [TherapistAuthController::class, 'updateProfile']);
        Route::post('password/update', [TherapistAuthController::class, 'updatePassword']);
        Route::post('logout', [TherapistAuthController::class, 'logout']);

        // 🎯 ADD HOLIDAY ROUTES HERE:
        Route::get('holiday-requests', [App\Http\Controllers\Api\TherapistHolidayController::class, 'index']);
        Route::get('holiday-requests/calendar', [App\Http\Controllers\Api\TherapistHolidayController::class, 'getCalendarHolidays']);
        Route::post('holiday-requests', [App\Http\Controllers\Api\TherapistHolidayController::class, 'store']);
        Route::get('holiday-requests/{id}', [App\Http\Controllers\Api\TherapistHolidayController::class, 'show']);
        Route::delete('holiday-requests/{id}', [App\Http\Controllers\Api\TherapistHolidayController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', [TherapistAuthController::class, 'dashboard']);
        Route::post('online-status', [TherapistController::class, 'updateOnlineStatus']);

        // ==============================================
        // BOOKING MANAGEMENT ROUTES
        // ==============================================

        // Get all bookings with optional filtering
        Route::get('bookings', [TherapistBookingController::class, 'getBookings']);

        // Get today's bookings
        Route::get('bookings/today', [TherapistBookingController::class, 'getTodayBookings']);

        // Get specific booking details
        Route::get('bookings/{bookingId}', [TherapistBookingController::class, 'getBookingDetails']);

        // Update booking status
        Route::post('bookings/{bookingId}/status', [TherapistBookingController::class, 'updateBookingStatus']);

        // Get booking statistics
        Route::get('bookings/stats', [TherapistBookingController::class, 'getBookingStats']);

        // Get schedule for date range
        Route::get('schedule', [TherapistBookingController::class, 'getSchedule']);


        // Patient Details 
        Route::get('patients', [TherapistPatientController::class, 'getPatients']);

        // Get specific patient details and booking history
        Route::get('patients/{patientId}', [TherapistPatientController::class, 'getPatientDetails']);

        // Get patient statistics for dashboard
        Route::get('patients-stats', [TherapistPatientController::class, 'getPatientStats']);

        // Search patients by name or email
        Route::get('patients-search', [TherapistPatientController::class, 'searchPatients']);

        // Get treatment history for a specific patient
        Route::get('patients/{patientId}/treatment-history', [App\Http\Controllers\Api\TherapistPatientController::class, 'getPatientTreatmentHistory']);

        // Get specific treatment history details
        Route::get('patients/{patientId}/treatment-history/{historyId}', [App\Http\Controllers\Api\TherapistPatientController::class, 'getPatientTreatmentHistoryDetails']);

        // Get treatment summary/stats for a patient
        Route::get('patients/{patientId}/treatment-summary', [App\Http\Controllers\Api\TherapistPatientController::class, 'getPatientTreatmentSummary']);



        // Get therapist's services
        Route::get('services', function (Request $request) {
            $therapist = $request->user();
            $services = $therapist->services()
                ->where('status', true)
                ->get([
                    'services.id',          // specify table name
                    'services.title',
                    'services.price',
                    'services.duration',
                    'services.description',
                    'services.benefits'
                ]);

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        });

        // Get therapist's availability
        Route::get('availability', function (Request $request) {
            $therapist = $request->user();
            $availabilities = $therapist->availabilities()
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(function ($availability) {
                    return [
                        'id' => $availability->id,
                        'day_of_week' => $availability->day_of_week,
                        'start_time' => $availability->start_time->format('H:i'),
                        'end_time' => $availability->end_time->format('H:i'),
                        'is_active' => $availability->is_active,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $availabilities
            ]);
        });

        // Get therapist locations
        Route::get('locations', function (Request $request) {
            $therapist = $request->user();
            $locations = $therapist->locations()
                ->where('status', true)
                ->get(['id', 'name', 'city', 'address', 'phone', 'email']);

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);
        });



        // Get upcoming bookings (next 7 days)
        Route::get('bookings/upcoming', function (Request $request) {
            $therapist = $request->user();
            $limit = $request->query('limit', 10);

            $bookings = $therapist->bookings()
                ->where('date', '>', Carbon::today())
                ->where('date', '<=', Carbon::today()->addDays(7))
                ->whereIn('status', ['confirmed', 'pending'])
                ->with(['service:id,title,duration'])
                ->orderBy('date')
                ->orderBy('time')
                ->limit($limit)
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'date' => $booking->date,
                        'time' => $booking->time,
                        'service' => $booking->service->title,
                        'customer' => $booking->name,
                        'status' => $booking->status,
                        'reference' => $booking->reference,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        });

        // Get bookings for specific date
        Route::get('bookings/date/{date}', function (Request $request, $date) {
            $therapist = $request->user();

            try {
                $bookingDate = Carbon::createFromFormat('Y-m-d', $date);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Use YYYY-MM-DD'
                ], 400);
            }

            $bookings = $therapist->bookings()
                ->whereDate('date', $bookingDate)
                ->whereIn('status', ['confirmed', 'pending', 'completed'])
                ->with(['service:id,title,duration'])
                ->orderBy('time')
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'time' => $booking->time,
                        'service' => $booking->service->title,
                        'customer' => $booking->name,
                        'customer_phone' => $booking->phone,
                        'duration' => $booking->service->duration,
                        'status' => $booking->status,
                        'reference' => $booking->reference,
                        'notes' => $booking->notes,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'meta' => [
                    'date' => $date,
                    'total_bookings' => $bookings->count(),
                ]
            ]);
        });

        // Get monthly booking overview
        Route::get('bookings/monthly/{year}/{month}', function (Request $request, $year, $month) {
            $therapist = $request->user();

            try {
                $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid year or month'
                ], 400);
            }

            $bookings = $therapist->bookings()
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('status', ['confirmed', 'pending', 'completed'])
                ->selectRaw('DATE(date) as booking_date, COUNT(*) as booking_count')
                ->groupBy('booking_date')
                ->get()
                ->keyBy('booking_date');

            $monthlyData = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->toDateString();
                $monthlyData[] = [
                    'date' => $dateStr,
                    'day' => $currentDate->day,
                    'day_name' => $currentDate->format('D'),
                    'booking_count' => $bookings->get($dateStr)->booking_count ?? 0,
                ];
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData,
                'meta' => [
                    'year' => (int) $year,
                    'month' => (int) $month,
                    'month_name' => $startDate->format('F'),
                    'total_days' => $endDate->day,
                ]
            ]);
        });



        Route::prefix('treatment-history')->group(function () {
            // Get all treatment histories for therapist
            Route::get('/', [App\Http\Controllers\Api\TreatmentHistoryController::class, 'index']);

            // Create new treatment history
            Route::post('/', [App\Http\Controllers\Api\TreatmentHistoryController::class, 'store']);

            // Get specific treatment history
            Route::get('/{id}', [App\Http\Controllers\Api\TreatmentHistoryController::class, 'show']);

            // Update treatment history (only within 24 hours)
            Route::put('/{id}', [App\Http\Controllers\Api\TreatmentHistoryController::class, 'update']);

            // Get treatment history by booking ID
            Route::get('/booking/{bookingId}', [App\Http\Controllers\Api\TreatmentHistoryController::class, 'getByBooking']);
        });

        // Quick route to check if booking has treatment history
        Route::get('bookings/{bookingId}/has-treatment-history', function (Request $request, $bookingId) {
            $therapist = $request->user();

            $hasHistory = App\Models\TreatmentHistory::where('booking_id', $bookingId)
                ->where('therapist_id', $therapist->id)
                ->exists();

            return response()->json([
                'success' => true,
                'has_history' => $hasHistory
            ]);
        });



         // THERAPIST CHAT ROUTES - ADD THESE
        Route::prefix('chats')->group(function () {
            Route::get('/', [TherapistChatController::class, 'index']);
            Route::get('/{chatRoomId}', [TherapistChatController::class, 'show']);
            Route::get('/{chatRoomId}/messages', [TherapistChatController::class, 'getMessages']);
            Route::post('/{chatRoomId}/messages', [TherapistChatController::class, 'sendMessage']);
            Route::post('/{chatRoomId}/mark-read', [TherapistChatController::class, 'markAsRead']);
        });

    });
});


Route::prefix('therapists')->group(function () {
    // Public routes (no authentication required)
    Route::get('/online', [TherapistController::class, 'getOnlineTherapists']);
    Route::get('/all', [TherapistController::class, 'getAllTherapists']);
    Route::get('/{id}', [TherapistController::class, 'getTherapist']);


});

// FCM Token routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/fcm-token', [FCMTokenController::class, 'store']);
    Route::delete('/fcm-token', [FCMTokenController::class, 'destroy']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/treatment-histories', [PatientTreatmentHistoryController::class, 'getUserTreatmentHistories']);
    Route::get('/user/bookings/{bookingId}/treatment-history', [PatientTreatmentHistoryController::class, 'getByBooking']);
});





