<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses;

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    public function store(Request $request)
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

        DB::transaction(function() use ($user, $request) {
            // If this is the default address, unset any existing default
            if ($request->is_default) {
                $user->addresses()->update(['is_default' => false]);
            }

            // If this is the first address, make it default
            $hasAddresses = $user->addresses()->count() > 0;
            $makeDefault = $request->is_default || !$hasAddresses;

            $user->addresses()->create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address_line1' => $request->address_line1,
                'address_line2' => $request->address_line2,
                'city' => $request->city,
                'postcode' => $request->postcode,
                'is_default' => $makeDefault
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully',
            'data' => $user->addresses()->get()
        ]);
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
