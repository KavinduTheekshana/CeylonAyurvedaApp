<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    public function index()
    {
        // Get the authenticated user
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user',
            ], 401);
        }

        // Get all addresses belonging to the user
        $addresses = Address::where('user_id', $user->id)
            ->orderBy('is_default', 'desc') // Default addresses first
            ->orderBy('created_at', 'desc') // Then by date created
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    public function store(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'required|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'postcode' => 'required|string|max:20',
            'is_default' => 'boolean'
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the authenticated user
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. User must be logged in to save addresses.'
                ], 401);
            }

            // If this is set as default, update all other addresses to non-default
            if ($request->is_default) {
                Address::where('user_id', $user->id)
                    ->update(['is_default' => false]);
            }
            // If this is the first address, make it default regardless
            elseif (Address::where('user_id', $user->id)->count() === 0) {
                $request->merge(['is_default' => true]);
            }

            // Create the new address
            $address = new Address();
            $address->user_id = $user->id;
            $address->name = $request->name;
            $address->email = $request->email;
            $address->phone = $request->phone;
            $address->address_line1 = $request->address_line1;
            $address->address_line2 = $request->address_line2;
            $address->city = $request->city;
            $address->postcode = strtoupper($request->postcode); // Convert postcode to uppercase
            $address->is_default = $request->is_default ?? false;
            $address->save();

            return response()->json([
                'success' => true,
                'message' => 'Address saved successfully',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error creating address: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving the address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'postcode' => 'required|string|max:20',
            'is_default' => 'boolean'
        ]);

        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        DB::transaction(function() use ($user, $address, $request) {
            // If this is being set as default, unset any existing default
            if ($request->is_default) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address_line1' => $request->address_line1,
                'address_line2' => $request->address_line2,
                'city' => $request->city,
                'postcode' => $request->postcode,
                'is_default' => $request->is_default ?? $address->is_default
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address->fresh()
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;

        $address->delete();

        // If the deleted address was the default, set another as default if available
        if ($wasDefault) {
            $newDefault = $user->addresses()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }

    public function makeDefault(Request $request, $id)
    {
        $user = $request->user();

        DB::transaction(function() use ($user, $id) {
            // Unset current default
            $user->addresses()->update(['is_default' => false]);

            // Set new default
            $user->addresses()->findOrFail($id)->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Default address updated successfully'
        ]);
    }
}
