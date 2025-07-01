<?php
namespace App\Http\Controllers;

use App\Models\Investment;
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

    public function getLocationInvestments($locationId)
    {
        try {
            $location = Location::with(['locationInvestment'])->findOrFail($locationId);
            $investment = $location->locationInvestment;

            // Get recent investments with investor names
            $recentInvestments = Investment::where('location_id', $locationId)
                ->where('status', 'completed')
                ->with('user')
                ->orderBy('invested_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($investment) {
                    return [
                        'id' => $investment->id,
                        'amount' => $investment->amount,
                        'investor_name' => $investment->user->name,
                        'invested_at' => $investment->invested_at,
                        'status' => $investment->status,
                        'reference' => $investment->reference,
                    ];
                });

            $data = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'address' => $location->address,
                'postcode' => $location->postcode,
                'image' => $location->image ? asset('storage/' . $location->image) : null,
                'description' => $location->description,
                'investment_stats' => [
                    'total_invested' => $investment ? $investment->total_invested : 0,
                    'investment_limit' => $investment ? $investment->investment_limit : 10000,
                    'remaining_amount' => $investment ?
                        ($investment->investment_limit - $investment->total_invested) : 10000,
                    'progress_percentage' => $investment && $investment->investment_limit > 0 ?
                        ($investment->total_invested / $investment->investment_limit) * 100 : 0,
                    'total_investors' => $investment ? $investment->total_investors : 0,
                    'is_open_for_investment' => $investment ? $investment->is_open_for_investment : true,
                ],
                'recent_investments' => $recentInvestments,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location investments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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