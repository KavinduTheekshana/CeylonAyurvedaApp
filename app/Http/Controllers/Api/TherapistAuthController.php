<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerificationEmail;
use App\Models\Therapist;
use App\Models\PasswordResetToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Log;

class TherapistAuthController extends Controller
{

    /**
     * Change therapist password
     */
    public function changePassword(Request $request)
    {
        $therapist = $request->user();

        if (!$therapist) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the current password is correct
        if (!Hash::check($request->current_password, $therapist->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors' => [
                    'current_password' => ['The provided password does not match our records.']
                ]
            ], 401);
        }

        try {
            // Update the password
            $therapist->password = Hash::make($request->new_password);
            $therapist->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error changing therapist password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Register a new therapist (usually done by admin, but included for completeness)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:therapists'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'work_start_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate verification code (6 digits)
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Create the therapist
            $therapist = Therapist::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'bio' => $request->bio,
                'work_start_date' => $request->work_start_date,
                'status' => false, // Inactive until verified and approved
                'email_verified_at' => null,
                'verification_code' => $verificationCode,
            ]);

            // Send verification email (you'll need to create this mail class)
            // Mail::to($therapist->email)->send(new TherapistVerificationEmail($therapist, $verificationCode));

            return response()->json([
                'success' => true,
                'message' => 'Therapist registered successfully. Please verify your email and wait for admin approval.',
                'data' => [
                    'therapist' => [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'phone' => $therapist->phone,
                        'status' => $therapist->status,
                    ],
                    'requires_verification' => true
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Therapist registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Therapist login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the therapist
            $therapist = Therapist::where('email', $request->email)->first();

            // Check if therapist exists and password is correct
            if (!$therapist || !Hash::check($request->password, $therapist->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if therapist account is active
            if (!$therapist->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active. Please contact admin for approval.',
                    'data' => [
                        'account_status' => 'inactive',
                        'requires_approval' => true
                    ]
                ], 403);
            }

            // Check if email is verified
            if ($therapist->email_verified_at === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email verification required. Please verify your email to continue.',
                    'data' => [
                        'therapist' => [
                            'id' => $therapist->id,
                            'name' => $therapist->name,
                            'email' => $therapist->email,
                        ],
                        'requires_verification' => true
                    ]
                ], 403);
            }

            // Revoke previous tokens
            $therapist->tokens()->delete();

            // Generate new access token
            $token = $therapist->createToken('therapist_auth_token')->plainTextToken;

            // Update last login time
            $therapist->update(['last_login_at' => now()]);

            // Load relationships for response
            $therapist->load(['services:id,title', 'availabilities']);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'therapist' => [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'phone' => $therapist->phone,
                        'bio' => $therapist->bio,
                        'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                        'work_start_date' => $therapist->work_start_date,
                        'status' => $therapist->status,
                        'email_verified_at' => $therapist->email_verified_at,
                        'last_login_at' => $therapist->last_login_at,
                        'services' => $therapist->services,
                        'availability_count' => $therapist->availabilities->count(),
                    ],
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get authenticated therapist profile
     */
    public function profile(Request $request)
    {
        try {
            $therapist = $request->user();

            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Load relationships
            $therapist->load([
                'services:id,title,price,duration',
                'availabilities',
                'locations:id,name,city'
            ]);

            // Get some stats
            $todayBookings = $therapist->todaysBookings()->count();
            $upcomingBookings = $therapist->upcomingBookings()->count();
            $totalBookings = $therapist->bookings()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'therapist' => [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'phone' => $therapist->phone,
                        'bio' => $therapist->bio,
                        'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                        'work_start_date' => $therapist->work_start_date,
                        'status' => $therapist->status,
                        'email_verified_at' => $therapist->email_verified_at,
                        'last_login_at' => $therapist->last_login_at,
                        'years_of_experience' => $therapist->years_of_experience,
                        'formatted_work_start_date' => $therapist->formatted_work_start_date,
                        'services' => $therapist->services,
                        'availabilities' => $therapist->availabilities,
                        'locations' => $therapist->locations,
                    ],
                    'stats' => [
                        'today_bookings' => $todayBookings,
                        'upcoming_bookings' => $upcomingBookings,
                        'total_bookings' => $totalBookings,
                        'services_count' => $therapist->services->count(),
                        'availability_slots' => $therapist->availabilities->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching therapist profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update therapist profile
     */
    public function updateProfile(Request $request)
    {
        $therapist = $request->user();

        if (!$therapist) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],

            'phone' => ['required', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update basic info
            $therapist->name = $request->name;
       
            $therapist->phone = $request->phone;
            $therapist->bio = $request->bio;

            // Handle profile photo upload if provided
            if ($request->hasFile('profile_photo')) {
                // Delete old photo if exists
                if ($therapist->image) {
                    $oldPath = str_replace('storage/', '', $therapist->image);
                    if (\Storage::disk('public')->exists($oldPath)) {
                        \Storage::disk('public')->delete($oldPath);
                    }
                }

                // Store the new photo
                $photoPath = $request->file('profile_photo')->store('therapists', 'public');
                $therapist->image = $photoPath;
            }

            $therapist->save();

            // Load relationships for response
            $therapist->load([
                'services:id,title,price,duration',
                'availabilities',
                'locations:id,name,city'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'therapist' => [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'phone' => $therapist->phone,
                        'bio' => $therapist->bio,
                        'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                        'work_start_date' => $therapist->work_start_date,
                        'status' => $therapist->status,
                        'email_verified_at' => $therapist->email_verified_at,
                        'last_login_at' => $therapist->last_login_at,
                        'services' => $therapist->services,
                        'availabilities' => $therapist->availabilities,
                        'locations' => $therapist->locations,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating therapist profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update therapist password
     */
    public function updatePassword(Request $request)
    {
        $therapist = $request->user();

        if (!$therapist) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the current password is correct
        if (!Hash::check($request->current_password, $therapist->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors' => [
                    'current_password' => ['The provided password does not match our records.']
                ]
            ], 401);
        }

        try {
            // Update the password
            $therapist->password = Hash::make($request->password);
            $therapist->save();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating therapist password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Therapist forgot password
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:therapists,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $email = $request->email;
            $therapist = Therapist::where('email', $email)->first();

            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist not found'
                ], 404);
            }

            // Generate 6-digit OTP
            $otp = rand(100000, 999999);

            // Store OTP in password_resets table
            PasswordResetToken::updateOrCreate(
                ['email' => $email],
                [
                    'token' => $otp,
                    'created_at' => Carbon::now()
                ]
            );

            // Send email with OTP
            Mail::to($email)->send(new PasswordResetMail($otp));

            return response()->json([
                'success' => true,
                'message' => 'Password reset code sent to your email'
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist forgot password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset code',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify password reset code
     */
    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:therapists,email',
            'code' => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $passwordReset = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->code)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset code'
                ], 400);
            }

            // Check if code is not expired (1 hour validity)
            $createdAt = Carbon::parse($passwordReset->created_at);
            if (Carbon::now()->diffInMinutes($createdAt) > 60) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reset code has expired'
                ], 400);
            }

            // Generate a temporary token for the reset process
            $resetToken = Str::random(60);
            $passwordReset->update(['token' => $resetToken]);

            return response()->json([
                'success' => true,
                'message' => 'Code verified successfully',
                'data' => [
                    'reset_token' => $resetToken
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist verify reset code error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:therapists,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $passwordReset = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->reset_token)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset token'
                ], 400);
            }

            // Update therapist password
            $therapist = Therapist::where('email', $request->email)->first();
            $therapist->password = Hash::make($request->password);
            $therapist->save();

            // Delete the password reset record
            $passwordReset->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist reset password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Logout therapist
     */
    public function logout(Request $request)
    {
        try {
            $therapist = $request->user();

            if ($therapist) {
                // Revoke the current token
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get therapist dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $therapist = $request->user();

            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get today's bookings
            $todayBookings = $therapist->todaysBookings()
                ->with(['service:id,title,duration', 'user:id,name'])
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'time' => $booking->time,
                        'service' => $booking->service->title ?? 'Unknown Service',
                        'duration' => $booking->service->duration ?? 60,
                        'customer_name' => $booking->name,
                        'status' => $booking->status,
                        'reference' => $booking->reference,
                    ];
                });

            // Get upcoming bookings (next 7 days)
            $upcomingBookings = $therapist->upcomingBookings()
                ->where('date', '>', today())
                ->where('date', '<=', today()->addDays(7))
                ->with(['service:id,title'])
                ->limit(5)
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'date' => $booking->date,
                        'time' => $booking->time,
                        'service' => $booking->service->title ?? 'Unknown Service',
                        'customer_name' => $booking->name,
                        'status' => $booking->status,
                    ];
                });

            // Calculate statistics
            $stats = [
                'today_bookings' => $todayBookings->count(),
                'upcoming_bookings' => $therapist->upcomingBookings()->count(),
                'total_bookings' => $therapist->bookings()->count(),
                'completed_bookings' => $therapist->bookings()->where('status', 'completed')->count(),
                'services_count' => $therapist->services()->count(),
                'availability_slots' => $therapist->availabilities()->where('is_active', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'therapist' => [
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                    ],
                    'stats' => $stats,
                    'today_bookings' => $todayBookings,
                    'upcoming_bookings' => $upcomingBookings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching therapist dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}