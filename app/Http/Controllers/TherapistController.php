<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Therapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TherapistController extends Controller
{
    /**
     * Get therapists assigned to a specific service
     * Public endpoint for booking flow
     */
    public function getServiceTherapists($serviceId)
    {
        try {
            Log::info("Fetching therapists for service ID: " . $serviceId);

            // Find the service first
            $service = Service::find($serviceId);

            if (!$service) {
                Log::warning("Service not found: " . $serviceId);
                return response()->json([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            // Try to get therapists with proper relationship
            $therapists = $service->therapists()
                ->where('status', true)
                ->orderBy('name')
                ->get();

            Log::info("Found " . $therapists->count() . " therapists for service " . $serviceId);

            // Format therapist data
            $therapistData = $therapists->map(function($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? url('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'status' => $therapist->status
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $therapistData
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching therapists: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch therapists',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all therapists (Admin only)
     */
    public function index()
    {
        try {
            $therapists = Therapist::with('services')->orderBy('name')->get();

            $therapistData = $therapists->map(function($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'status' => $therapist->status,
                    'services' => $therapist->services->map(function($service) {
                        return [
                            'id' => $service->id,
                            'title' => $service->title,
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $therapistData
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching all therapists: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch therapists',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new therapist (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:therapists,email',
            'phone' => 'required|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
            'services' => 'array',
            'services.*' => 'exists:services,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapistData = $request->only(['name', 'email', 'phone', 'bio']);
            $therapistData['status'] = $request->get('status', true);

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('therapists', 'public');
                $therapistData['image'] = $imagePath;
            }

            $therapist = Therapist::create($therapistData);

            // Attach services if provided
            if ($request->has('services')) {
                $therapist->services()->attach($request->services);
            }

            return response()->json([
                'success' => true,
                'data' => $therapist->load('services'),
                'message' => 'Therapist created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error("Error creating therapist: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update therapist (Admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:therapists,email,' . $id,
            'phone' => 'sometimes|required|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
            'services' => 'array',
            'services.*' => 'exists:services,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapist = Therapist::findOrFail($id);

            $therapistData = $request->only(['name', 'email', 'phone', 'bio', 'status']);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($therapist->image) {
                    Storage::disk('public')->delete($therapist->image);
                }

                $image = $request->file('image');
                $imagePath = $image->store('therapists', 'public');
                $therapistData['image'] = $imagePath;
            }

            $therapist->update($therapistData);

            // Update services if provided
            if ($request->has('services')) {
                $therapist->services()->sync($request->services);
            }

            return response()->json([
                'success' => true,
                'data' => $therapist->load('services'),
                'message' => 'Therapist updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error updating therapist: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete therapist (Admin only)
     */
    public function destroy($id)
    {
        try {
            $therapist = Therapist::findOrFail($id);

            // Check if therapist has active bookings
            $hasActiveBookings = $therapist->bookings()
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete therapist with active bookings. Please reassign or complete existing bookings first.'
                ], 400);
            }

            // Delete image if it exists
            if ($therapist->image) {
                Storage::disk('public')->delete($therapist->image);
            }

            // Detach from services
            $therapist->services()->detach();

            // Soft delete the therapist
            $therapist->delete();

            return response()->json([
                'success' => true,
                'message' => 'Therapist deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error deleting therapist: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Assign therapist to service (Admin only)
     */
    public function assignToService($therapistId, $serviceId)
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);
            $service = Service::findOrFail($serviceId);

            // Check if already assigned
            if ($therapist->services()->where('service_id', $serviceId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist is already assigned to this service'
                ], 400);
            }

            $therapist->services()->attach($serviceId);

            return response()->json([
                'success' => true,
                'message' => 'Therapist assigned to service successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error assigning therapist to service: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign therapist to service',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove therapist from service (Admin only)
     */
    public function removeFromService($therapistId, $serviceId)
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);

            // Check if therapist has active bookings for this service
            $hasActiveBookings = $therapist->bookings()
                ->where('service_id', $serviceId)
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove therapist from service with active bookings'
                ], 400);
            }

            $therapist->services()->detach($serviceId);

            return response()->json([
                'success' => true,
                'message' => 'Therapist removed from service successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error removing therapist from service: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove therapist from service',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}