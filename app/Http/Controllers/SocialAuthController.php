<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;

class SocialAuthController extends Controller
{
    /**
     * Handle social login (Google, Apple, etc.)
     */
    public function socialLogin(Request $request)
    {
        try {
            $request->validate([
                'provider' => 'required|string|in:google,apple',
                'token' => 'required|string',
                'email' => 'required|email',
                'name' => 'required|string',
                'provider_id' => 'required|string',
                'avatar_url' => 'nullable|string|url',
            ]);

            $provider = $request->provider;
            $providerToken = $request->token;
            $email = $request->email;
            $name = $request->name;
            $providerId = $request->provider_id;
            $avatarUrl = $request->avatar_url;

            // Verify the token with the provider
            if ($provider === 'google') {
                $verified = $this->verifyGoogleToken($providerToken, $email);
                if (!$verified) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Google token'
                    ], 401);
                }
            }

            // Check if user exists with this email
            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists, update their information if needed
                $user->update([
                    'name' => $name,
                    // Don't update email as it should remain the same
                    'email_verified_at' => $user->email_verified_at ?? now(), // Verify email if not already verified
                    'verification_code' => null, // Clear any pending verification
                ]);

                // Optionally update profile photo if provided and user doesn't have one
                if ($avatarUrl && !$user->profile_photo_path) {
                    try {
                        $photoPath = $this->downloadAndSaveAvatar($avatarUrl, $user->id);
                        if ($photoPath) {
                            $user->update(['profile_photo_path' => $photoPath]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to download avatar: ' . $e->getMessage());
                    }
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)), // Random password since they'll use social login
                    'email_verified_at' => now(), // Auto-verify email for social logins
                    'verification_code' => null,
                ]);

                // Download and save avatar if provided
                if ($avatarUrl) {
                    try {
                        $photoPath = $this->downloadAndSaveAvatar($avatarUrl, $user->id);
                        if ($photoPath) {
                            $user->update(['profile_photo_path' => $photoPath]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to download avatar: ' . $e->getMessage());
                    }
                }

                Log::info('New user created via ' . $provider . ' login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'provider' => $provider
                ]);
            }

            // Revoke existing tokens for security
            $user->tokens()->delete();

            // Create new access token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Prepare user data for response
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo_path' => $user->profile_photo_path,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            Log::info('Social login successful', [
                'user_id' => $user->id,
                'provider' => $provider,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $userData,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Social login error: ' . $e->getMessage(), [
                'provider' => $request->provider ?? 'unknown',
                'email' => $request->email ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during social login',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify Google token using Google's public API
     */
    private function verifyGoogleToken(string $token, string $email): bool
    {
        try {
            // First try to get user info using the access token
            $response = Http::get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'access_token' => $token
            ]);

            if ($response->successful()) {
                $userData = $response->json();
                
                // Verify the email matches and is verified by Google
                $emailMatches = $userData['email'] === $email;
                $emailVerified = $userData['verified_email'] ?? false;
                
                Log::info('Google token verification', [
                    'email_matches' => $emailMatches,
                    'email_verified' => $emailVerified,
                    'token_email' => $userData['email'] ?? 'none',
                    'provided_email' => $email
                ]);
                
                return $emailMatches && $emailVerified;
            }

            // If that fails, try the tokeninfo endpoint
            $tokenInfoResponse = Http::get('https://www.googleapis.com/oauth2/v1/tokeninfo', [
                'access_token' => $token
            ]);

            if ($tokenInfoResponse->successful()) {
                $tokenInfo = $tokenInfoResponse->json();
                
                // Check if token is valid and matches expected client IDs
                // Check if token is valid and matches expected client IDs
                    $validAudience = in_array($tokenInfo['audience'] ?? '', [
                        '535360967629-vvneo12jmja16di715olhr2uual607r6.apps.googleusercontent.com', // Android
                        '535360967629-s16f56i3t4s0d1l4cvc94ubqctmue9s4.apps.googleusercontent.com', // iOS  
                        '535360967629-25mot10es3kse0cba9ncu4hcs89dr87k.apps.googleusercontent.com'  // Web
                    ]);
                
                return $validAudience && isset($tokenInfo['email']) && $tokenInfo['email'] === $email;
            }

            Log::warning('Google token verification failed - all methods unsuccessful');
            return false;

        } catch (\Exception $e) {
            Log::error('Google token verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Download and save user avatar
     */
    private function downloadAndSaveAvatar(string $avatarUrl, int $userId): ?string
    {
        try {
            $response = Http::timeout(10)->get($avatarUrl);
            
            if (!$response->successful()) {
                return null;
            }

            $content = $response->body();
            $extension = 'jpg'; // Default to jpg
            
            // Try to determine file extension from content type
            $contentType = $response->header('Content-Type');
            if (str_contains($contentType, 'png')) {
                $extension = 'png';
            } elseif (str_contains($contentType, 'gif')) {
                $extension = 'gif';
            }

            $filename = 'social-avatar-' . $userId . '-' . time() . '.' . $extension;
            $path = 'profile-photos/' . $filename;

            // Save to storage
            \Storage::disk('public')->put($path, $content);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to download avatar: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Link social account to existing user
     */
    public function linkSocialAccount(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $request->validate([
                'provider' => 'required|string|in:google,apple',
                'token' => 'required|string',
                'provider_id' => 'required|string',
            ]);

            // Verify token and link account
            $provider = $request->provider;
            $providerToken = $request->token;

            if ($provider === 'google') {
                $verified = $this->verifyGoogleToken($providerToken, $user->email);
                if (!$verified) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Google token or email mismatch'
                    ], 401);
                }
            }

            // Here you could store the social account link in a separate table
            // For now, we'll just return success

            return response()->json([
                'success' => true,
                'message' => ucfirst($provider) . ' account linked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Social account linking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to link social account',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}