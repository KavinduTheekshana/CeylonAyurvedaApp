<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeleteAccountController extends Controller
{
    /**
     * Delete user account and all related data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function deleteAccount(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'password' => 'required',
            ]);

            $user = Auth::user();

            // Check if password is correct
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Password is incorrect',
                    'success' => false
                ], 401);
            }

            // Start database transaction
            DB::beginTransaction();

            // Get user ID for reference
            $userId = $user->id;

            // Delete all user addresses
            DB::table('addresses')->where('user_id', $userId)->delete();

            // Delete all user bookings
            DB::table('bookings')->where('user_id', $userId)->delete();

            // Delete any other user-related data here
            // For example: payment methods, notifications, etc.

            // Finally, delete the user
            $user->delete();

            // Commit the transaction
            DB::commit();

            // Revoke tokens if using sanctum/passport
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            return response()->json([
                'message' => 'Account deleted successfully',
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete account: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}
