<?php
namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // public function index()
    // {
    //     $locations = Location::active()
    //         ->select(['id', 'name', 'slug', 'city', 'address', 'image'])
    //         ->orderBy('name')
    //         ->get();

    //     return response()->json([
    //         'success' => true,
    //         'data' => $locations->map(function ($location) {
    //             return [
    //                 'id' => $location->id,
    //                 'name' => $location->name,
    //                 'slug' => $location->slug,
    //                 'city' => $location->city,
    //                 'address' => $location->address,
    //                 'image' => $location->image_url,
    //             ];
    //         })
    //     ]);
    // }

    public function index()
    {
        try {
            $locations = Location::where('status', true)
                               ->orderBy('name')
                               ->get()
                               ->map(function ($location) {
                                   return [
                                       'id' => $location->id,
                                       'name' => $location->name,
                                       'slug' => $location->slug,
                                       'address' => $location->address,
                                       'city' => $location->city,
                                       'postcode' => $location->postcode,
                                       'latitude' => $location->latitude,
                                       'longitude' => $location->longitude,
                                       'phone' => $location->phone,
                                       'email' => $location->email,
                                       'description' => $location->description,
                                       'operating_hours' => $location->operating_hours,
                                       'image' => $location->image,
                                       'status' => $location->status,
                                       'service_radius_miles' => $location->service_radius_miles,
                                       'created_at' => $location->created_at,
                                       'updated_at' => $location->updated_at
                                   ];
                               });

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching locations: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // public function show($id)
    // {
    //     $location = Location::active()
    //         ->with(['therapists', 'services'])
    //         ->find($id);

    //     if (!$location) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Location not found'
    //         ], 404);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'id' => $location->id,
    //             'name' => $location->name,
    //             'slug' => $location->slug,
    //             'address' => $location->address,
    //             'city' => $location->city,
    //             'postcode' => $location->postcode,
    //             'phone' => $location->phone,
    //             'email' => $location->email,
    //             'description' => $location->description,
    //             'operating_hours' => $location->operating_hours,
    //             'image' => $location->image_url,
    //             'service_radius_miles' => $location->service_radius_miles,
    //             'therapists_count' => $location->therapists->count(),
    //             'services_count' => $location->services->count(),
    //         ]
    //     ]);
    // }

    public function show($id)
    {
        try {
            $location = Location::where('status', true)->find($id);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'slug' => $location->slug,
                    'address' => $location->address,
                    'city' => $location->city,
                    'postcode' => $location->postcode,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'phone' => $location->phone,
                    'email' => $location->email,
                    'description' => $location->description,
                    'operating_hours' => $location->operating_hours,
                    'image' => $location->image,
                    'status' => $location->status,
                    'service_radius_miles' => $location->service_radius_miles,
                    'created_at' => $location->created_at,
                    'updated_at' => $location->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching location: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}