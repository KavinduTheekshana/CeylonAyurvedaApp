<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    /**
     * Request password reset code
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
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
        try {
            Mail::to($email)->send(new PasswordResetMail($otp));
            // Log::info('Password reset code sent to: ' . $otp);
            // dump('Password reset code sent to: ' . $otp);

            return response()->json([
                'success' => true,
                'message' => 'Password reset code sent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP for password reset
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

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
            'reset_token' => $resetToken
        ]);
    }

    /**
     * Reset password with the token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $passwordReset = PasswordResetToken::where('email', $request->email)
            ->where('token', $request->reset_token)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token'
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the password reset record
        $passwordReset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);
    }

    /**
     * Resend password reset code
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
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
        try {
            Mail::to($email)->send(new PasswordResetMail($otp));


            return response()->json([
                // 'code' => $otp,
                'success' => true,
                'message' => 'Password reset code resent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend password reset code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}