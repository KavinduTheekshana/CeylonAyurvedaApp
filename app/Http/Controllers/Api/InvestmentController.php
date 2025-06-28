<?php

// app/Http/Controllers/Api/InvestmentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Models\Location;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    private InvestmentService $investmentService;

    public function __construct(InvestmentService $investmentService)
    {
        $this->investmentService = $investmentService;
    }


    /**
     * Get all investments for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $investments = $user->investments()
            ->with(['location:id,name,city', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $investments,
            'meta' => [
                'total_invested' => $user->total_invested,
            ]
        ]);
    }

    /**
     * Get investment details
     */
    public function show(Investment $investment): JsonResponse
    {
        if ($investment->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to investment'
            ], 403);
        }

        $investment->load(['location', 'transactions']);

        return response()->json([
            'success' => true,
            'data' => $investment
        ]);
    }

    /**
     * Create new investment
     */
    public function store(InvestmentRequest $request): JsonResponse
    {
        try {
            $location = Location::findOrFail($request->location_id);
            $user = Auth::user();

            Log::info('Creating investment for user: ' . $user->id . ', location: ' . $location->id . ', amount: ' . $request->amount);

            $investment = $this->investmentService->createInvestment(
                $user,
                $location,
                $request->amount,
                $request->only(['notes'])
            );

            // Create payment intent
            $paymentData = $this->investmentService->createPaymentIntent($investment);

            Log::info('Investment created successfully with ID: ' . $investment->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'investment' => $investment->load('location:id,name'),
                    'payment_intent' => $paymentData
                ],
                'message' => 'Investment created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating investment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get investment opportunities (locations)
     */
    public function opportunities(): JsonResponse
    {
        try {
            Log::info('Fetching investment opportunities');
            
            // Get all active locations
            $locations = Location::where('status', true)->get();
            Log::info('Found locations count: ' . $locations->count());
            
            if ($locations->isEmpty()) {
                Log::warning('No active locations found in database');
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No locations found'
                ]);
            }

            $opportunities = $locations->map(function ($location) {
                Log::info('Processing location: ' . $location->name . ' (ID: ' . $location->id . ')');
                
                $stats = $this->investmentService->getLocationInvestmentStats($location);
                Log::info('Location stats for ' . $location->name . ':', $stats);
                
                $opportunity = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'city' => $location->city,
                    'address' => $location->address,
                    'image' => $location->image,
                    'description' => $location->description,
                    'investment_stats' => $stats
                ];
                
                Log::info('Processed opportunity:', $opportunity);
                return $opportunity;
            });

            Log::info('Total opportunities processed: ' . $opportunities->count());

            return response()->json([
                'success' => true,
                'data' => $opportunities->values()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching investment opportunities: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment opportunities',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get location investment details
     */
    public function locationDetails(Location $location): JsonResponse
    {
        try {
            Log::info('Fetching location details for: ' . $location->name);
            
            $stats = $this->investmentService->getLocationInvestmentStats($location);
            
            // Get recent investments for this location (anonymized)
            $recentInvestments = Investment::where('location_id', $location->id)
                ->where('status', 'completed')
                ->with('user:id,name')
                ->latest('invested_at')
                ->limit(10)
                ->get()
                ->map(function ($investment) {
                    return [
                        'amount' => $investment->amount,
                        'investor_name' => substr($investment->user->name, 0, 1) . str_repeat('*', strlen($investment->user->name) - 1),
                        'invested_at' => $investment->invested_at->format('Y-m-d H:i:s')
                    ];
                });

            $response = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'address' => $location->address,
                'image' => $location->image,
                'description' => $location->description,
                'investment_stats' => $stats,
                'recent_investments' => $recentInvestments
            ];

            Log::info('Location details response:', $response);

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching location details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Confirm payment (webhook or client confirmation)
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string'
        ]);

        try {
            $investment = $this->investmentService->handleSuccessfulPayment($request->payment_intent_id);

            return response()->json([
                'success' => true,
                'data' => $investment->load('location:id,name'),
                'message' => 'Investment completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Payment confirmation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user investment summary
     */
    public function summary(): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info('Fetching investment summary for user: ' . $user->id);
            
            $totalInvested = $user->investments()->where('status', 'completed')->sum('amount');
            $totalInvestments = $user->investments()->where('status', 'completed')->count();
            $pendingInvestments = $user->investments()->where('status', 'pending')->count();
            
            $investmentsByLocation = $user->investments()
                ->where('status', 'completed')
                ->with('location:id,name,city')
                ->get()
                ->groupBy('location_id')
                ->map(function ($investments) {
                    return [
                        'location' => $investments->first()->location,
                        'total_amount' => $investments->sum('amount'),
                        'investment_count' => $investments->count()
                    ];
                })
                ->values();

            $summary = [
                'total_invested' => $totalInvested,
                'total_investments' => $totalInvestments,
                'pending_investments' => $pendingInvestments,
                'investments_by_location' => $investmentsByLocation
            ];

            Log::info('User investment summary:', $summary);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching investment summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment summary'
            ], 500);
        }
    }

    /**
     * Test endpoint for debugging
     */
    public function test(): JsonResponse
    {
        try {
            $locationsCount = Location::count();
            $activeLocationsCount = Location::where('status', true)->count();
            $investmentsCount = Investment::count();
            
            return response()->json([
                'success' => true,
                'message' => 'API is working',
                'data' => [
                    'timestamp' => now()->toISOString(),
                    'total_locations' => $locationsCount,
                    'active_locations' => $activeLocationsCount,
                    'total_investments' => $investmentsCount,
                    'database_connected' => true,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

