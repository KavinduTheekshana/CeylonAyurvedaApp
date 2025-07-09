<?php

// app/Http/Controllers/Api/InvestmentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Location;
use App\Models\LocationInvestment;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvestmentConfirmationMail;
use Illuminate\Support\Facades\Log;

class InvestmentController extends Controller
{
    private InvestmentService $investmentService;

    public function __construct(InvestmentService $investmentService)
    {
        $this->investmentService = $investmentService;
    }

    public function createInvestment(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'amount' => 'required|numeric|min:10|max:10000',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Set Stripe API key
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

            $user = auth()->user();
            $locationId = $request->location_id;
            $amount = $request->amount;

            // Check if location is open for investment
            $locationInvestment = LocationInvestment::where('location_id', $locationId)->first();

            if (!$locationInvestment) {
                // Create location investment record if it doesn't exist
                $locationInvestment = LocationInvestment::create([
                    'location_id' => $locationId,
                    'total_invested' => 0,
                    'investment_limit' => 10000,
                    'total_investors' => 0,
                    'is_open_for_investment' => true,
                ]);
            }

            if (!$locationInvestment->is_open_for_investment) {
                return response()->json([
                    'success' => false,
                    'message' => 'This location is currently closed for investment'
                ], 400);
            }

            // Check if amount exceeds remaining limit
            $remainingAmount = $locationInvestment->investment_limit - $locationInvestment->total_invested;
            if ($amount > $remainingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Investment amount exceeds the remaining limit of £{$remainingAmount}"
                ], 400);
            }

            // Generate unique reference
            $reference = 'INV-' . strtoupper(Str::random(8));

            // Create investment record
            $investment = Investment::create([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'amount' => $amount,
                'currency' => 'GBP',
                'status' => 'pending',
                'reference' => $reference,
                'invested_at' => now(),
                'notes' => $request->notes,
            ]);

            // Create Stripe PaymentIntent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100, // Amount in pence
                'currency' => 'gbp',
                'metadata' => [
                    'investment_id' => $investment->id,
                    'user_id' => $user->id,
                    'location_id' => $locationId,
                    'reference' => $reference,
                ],
            ]);

            // Update investment with Stripe payment intent ID
            $investment->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_metadata' => json_encode($paymentIntent->toArray()),
            ]);

            // Create transaction record
            InvestmentTransaction::create([
                'investment_id' => $investment->id,
                'type' => 'payment',
                'amount' => $amount,
                'stripe_transaction_id' => $paymentIntent->id,
                'status' => 'pending',
                'stripe_response' => json_encode($paymentIntent->toArray()),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'investment' => $investment,
                    'payment_intent' => [
                        'client_secret' => $paymentIntent->client_secret,
                        'payment_intent_id' => $paymentIntent->id,
                    ],
                ]
            ]);
        } catch (ApiErrorException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create investment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLocationInvestors($locationId)
    {
        try {
            $investors = Investment::where('location_id', $locationId)
                ->where('status', 'completed')
                ->with('user')
                ->orderBy('invested_at', 'desc')
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

            return response()->json([
                'success' => true,
                'data' => $investors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserInvestments(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->get('per_page', 15);

            $investments = Investment::where('user_id', $user->id)
                ->with(['location', 'transactions'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $investments->items(),
                'pagination' => [
                    'total' => $investments->total(),
                    'count' => $investments->count(),
                    'per_page' => $investments->perPage(),
                    'current_page' => $investments->currentPage(),
                    'total_pages' => $investments->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investments',
                'error' => $e->getMessage()
            ], 500);
        }
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
    public function getOpportunities()
    {
        try {
            $locations = Location::active()
                ->with('locationInvestment')
                ->orderBy('name')
                ->get()
                ->map(function ($location) {
                    // Create location investment record if it doesn't exist
                    if (!$location->locationInvestment) {
                        $location->locationInvestment = LocationInvestment::create([
                            'location_id' => $location->id,
                            'total_invested' => 0,
                            'investment_limit' => 10000,
                            'total_investors' => 0,
                            'is_open_for_investment' => true,
                        ]);
                    }

                    $investment = $location->locationInvestment;

                    return [
                        'id' => $location->id,
                        'name' => $location->name,
                        'city' => $location->city,
                        'address' => $location->address,
                        'description' => $location->description,
                        'image' => $location->image_url,
                        'total_invested' => (float) $investment->total_invested,
                        'investment_limit' => (float) $investment->investment_limit,
                        'total_investors' => (int) $investment->total_investors,
                        'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                        'remaining_amount' => (float) $investment->remaining_amount,
                        'progress_percentage' => (float) $investment->progress_percentage,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $locations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment opportunities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get location investment details
     */
    public function getLocationDetails($locationId)
    {
        try {
            $location = Location::active()
                ->with(['locationInvestment'])
                ->find($locationId);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found',
                ], 404);
            }

            // Create location investment record if it doesn't exist
            if (!$location->locationInvestment) {
                $location->locationInvestment = LocationInvestment::create([
                    'location_id' => $location->id,
                    'total_invested' => 0,
                    'investment_limit' => 10000,
                    'total_investors' => 0,
                    'is_open_for_investment' => true,
                ]);
            }

            // Get recent investments for this location
            $recentInvestments = $location->investments()
                ->with('user:id,name')
                ->where('status', 'completed')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(function ($investment) {
                    return [
                        'amount' => (float) $investment->amount,
                        'investor_name' => $investment->user->name ?? 'Anonymous',
                        'invested_at' => $investment->created_at->toISOString(),
                    ];
                });

            $investment = $location->locationInvestment;

            $data = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'address' => $location->address,
                'description' => $location->description,
                'image' => $location->image_url,
                'investment_stats' => [
                    'total_invested' => (float) $investment->total_invested,
                    'investment_limit' => (float) $investment->investment_limit,
                    'remaining_amount' => (float) $investment->remaining_amount,
                    'progress_percentage' => (float) $investment->progress_percentage,
                    'total_investors' => (int) $investment->total_investors,
                    'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                ],
                'recent_investments' => $recentInvestments,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm payment (webhook or client confirmation)
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Retrieve payment intent from Stripe
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not successful'
                ], 400);
            }

            // Find investment by payment intent ID
            $investment = Investment::where('stripe_payment_intent_id', $request->payment_intent_id)->first();

            if (!$investment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment not found'
                ], 404);
            }

            // Update investment status
            $investment->update([
                'status' => 'completed',
                'invested_at' => now(),
            ]);

            // Update transaction status
            InvestmentTransaction::where('investment_id', $investment->id)
                ->where('stripe_transaction_id', $request->payment_intent_id)
                ->update([
                    'status' => 'succeeded',
                    'stripe_response' => json_encode($paymentIntent->toArray()),
                ]);

            // Update location investment totals
            $locationInvestment = LocationInvestment::where('location_id', $investment->location_id)->first();

            if ($locationInvestment) {
                $locationInvestment->increment('total_invested', $investment->amount);

                // Check if this is a new investor for this location
                $existingInvestorCount = Investment::where('location_id', $investment->location_id)
                    ->where('user_id', $investment->user_id)
                    ->where('status', 'completed')
                    ->count();

                if ($existingInvestorCount === 1) { // First investment by this user
                    $locationInvestment->increment('total_investors');
                }

                // Check if investment limit is reached
                if ($locationInvestment->total_invested >= $locationInvestment->investment_limit) {
                    $locationInvestment->update(['is_open_for_investment' => false]);
                }
            }

            DB::commit();

            // Send confirmation email
            try {
                Log::info('Sending investment confirmation email to user: ' . $investment->user->email);
                // Mail::to($investment->user->email)->send(new InvestmentConfirmationMail($investment));
                Mail::to('kavindutheekshana@gmail.com')->send(new InvestmentConfirmationMail($investment));
            } catch (\Exception $emailException) {
                // Log email error but don't fail the response
                Log::error('Failed to send investment confirmation email: ' . $emailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'investment' => $investment->fresh(),
                    'message' => 'Investment completed successfully'
                ]
            ]);
        } catch (ApiErrorException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user investment summary
     */
    public function getUserSummary()
    {
        try {
            $user = Auth::user();

            // Get user's confirmed investments
            $investments = $user->investments()
                ->with('location:id,name,city')
                ->where('status', 'completed')
                ->get();

            $totalInvested = $investments->sum('amount');
            $totalInvestments = $investments->count();

            // Get pending investments count
            $pendingInvestments = $user->investments()
                ->where('status', 'pending')
                ->count();

            // Group investments by location
            $investmentsByLocation = $investments->groupBy('location_id')->map(function ($locationInvestments) {
                $location = $locationInvestments->first()->location;
                return [
                    'location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'city' => $location->city,
                    ],
                    'total_amount' => (float) $locationInvestments->sum('amount'),
                    'investment_count' => $locationInvestments->count(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_invested' => (float) $totalInvested,
                    'total_investments' => $totalInvestments,
                    'pending_investments' => $pendingInvestments,
                    'investments_by_location' => $investmentsByLocation,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user investment summary',
                'error' => $e->getMessage(),
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
