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
use App\Mail\BankTransferRequestMail;
use App\Mail\BankTransferConfirmedMail;
use App\Mail\AdminBankTransferNotificationMail;

class InvestmentController extends Controller
{
    private InvestmentService $investmentService;

    public function __construct(InvestmentService $investmentService)
    {
        $this->investmentService = $investmentService;
    }





    /**
     * Create new investment with support for card and bank transfer payments
     */
    public function createInvestment(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'amount' => 'required|numeric|min:10|max:10000',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:card,bank_transfer',
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $locationId = $request->location_id;
            $amount = $request->amount;
            $paymentMethod = $request->payment_method;


            // Check if location is open for investment
            $locationInvestment = $this->getOrCreateLocationInvestment($locationId);

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
            $reference = $this->generateInvestmentReference();

            // Create investment record
            $investment = Investment::create([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'amount' => $amount,
                'currency' => 'GBP',
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'reference' => $reference,
                'invested_at' => $paymentMethod === 'card' ? now() : null,
                'notes' => $request->notes,
            ]);

            // Handle different payment methods
            if ($paymentMethod === 'card') {
                $result = $this->processCardPayment($investment, $amount, $reference);
            } else {
                $result = $this->processBankTransferPayment($investment);
            }

            DB::commit();
            return $result;

        } catch (ApiErrorException $e) {
            DB::rollback();
            Log::error('Stripe API error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Investment creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create investment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm bank transfer by admin
     */
    public function confirmBankTransfer(Request $request, $investmentId): JsonResponse
    {
        $request->validate([
            'bank_transfer_details' => 'required|string|max:1000',
            'confirmed' => 'required|boolean',
        ]);

        try {
            $investment = Investment::findOrFail($investmentId);

            // Authorization check - only admin can confirm
            if (!Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Validate investment type and status
            if ($investment->payment_method !== 'bank_transfer') {
                return response()->json([
                    'success' => false,
                    'message' => 'This investment is not a bank transfer'
                ], 400);
            }

            if ($investment->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'This investment is already confirmed'
                ], 400);
            }

            DB::beginTransaction();

            if ($request->confirmed) {
                $this->confirmBankTransferPayment($investment, $request->bank_transfer_details);
                $message = 'Bank transfer confirmed successfully';
            } else {
                $this->rejectBankTransferPayment($investment, $request->bank_transfer_details);
                $message = 'Bank transfer rejected';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $investment->fresh(),
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bank transfer confirmation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bank transfer confirmation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get investors for a specific location
     */
    public function getLocationInvestors($locationId): JsonResponse
    {
        try {
            $investors = Investment::where('location_id', $locationId)
                ->with('user:id,name')
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
                        'payment_method' => $investment->payment_method, // Added this field
                        'currency' => $investment->currency,
                        'notes' => $investment->notes,
                        // Additional useful fields
                        'bank_transfer_confirmed_at' => $investment->bank_transfer_confirmed_at,
                        'stripe_payment_intent_id' => $investment->stripe_payment_intent_id ? 'stripe_' . substr($investment->stripe_payment_intent_id, -8) : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $investors
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch investors', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get paginated user investments
     */
    public function getUserInvestments(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = min($request->get('per_page', 15), 100); // Limit max per page

            $investments = Investment::where('user_id', $user->id)
                ->with(['location:id,name,city', 'transactions'])
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
            Log::error('Failed to fetch user investments', ['error' => $e->getMessage()]);
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
        $perPage = min($request->get('per_page', 15), 100);

        $investments = $user->investments()
            ->with(['location:id,name,city', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $investments,
            'meta' => [
                'total_invested' => $user->total_invested ?? 0,
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
     * Create new investment using InvestmentService
     */
    public function store(InvestmentRequest $request): JsonResponse
    {
        try {
            $location = Location::findOrFail($request->location_id);
            $user = Auth::user();

            Log::info('Creating investment via service', [
                'user_id' => $user->id,
                'location_id' => $location->id,
                'amount' => $request->amount
            ]);

            $investment = $this->investmentService->createInvestment(
                $user,
                $location,
                $request->amount,
                $request->only(['notes'])
            );

            // Create payment intent
            $paymentData = $this->investmentService->createPaymentIntent($investment);

            Log::info('Investment created successfully', ['investment_id' => $investment->id]);

            return response()->json([
                'success' => true,
                'data' => [
                    'investment' => $investment->load('location:id,name'),
                    'payment_intent' => $paymentData
                ],
                'message' => 'Investment created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating investment via service', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get investment opportunities (locations)
     */
    public function getOpportunities(): JsonResponse
    {
        try {
            $locations = Location::active()
                ->with(['locationInvestment', 'therapists']) // Load therapists too
                ->orderBy('name')
                ->get()
                ->map(function ($location) {
                    $investment = $this->getOrCreateLocationInvestment($location->id);

                    return [
                        'id' => $location->id,
                        'name' => $location->name,
                        'city' => $location->city,
                        'address' => $location->address,
                        'description' => $location->description,
                        'image' => $location->image_url,
                        'owner_name' => $location->owner_name,
                        'owner_email' => $location->owner_email,
                        'manager_name' => $location->manager_name,
                        'manager_email' => $location->manager_email,
                        'branch_phone' => $location->branch_phone,

                        'total_invested' => (float) $investment->total_invested,
                        'investment_limit' => (float) $investment->investment_limit,
                        'total_investors' => (int) $investment->total_investors,
                        'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                        'remaining_amount' => (float) $investment->remaining_amount,
                        'progress_percentage' => (float) $investment->progress_percentage,

                        // Include therapists list
                        'therapists' => $location->therapists->map(function ($therapist) {
                            return [
                                'id' => $therapist->id,
                                'name' => $therapist->name,
                                'email' => $therapist->email,
                                'phone' => $therapist->phone,
                                'image' => $therapist->image,
                                'bio' => $therapist->bio,
                                'work_start_date' => $therapist->work_start_date,
                                'status' => (bool) $therapist->status,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $locations,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch investment opportunities', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment opportunities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOpportunity($locationId): JsonResponse
    {
        try {
            $location = Location::active()
                ->with(['locationInvestment', 'therapists'])
                ->find($locationId);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found',
                ], 404);
            }

            $investment = $this->getOrCreateLocationInvestment($location->id);

            $locationData = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'address' => $location->address,
                'description' => $location->description,
                'image' => $location->image_url,
                'franchisee_name' => $location->franchisee_name,
                'franchisee_email' => $location->franchisee_email,
                'franchisee_phone' => $location->franchisee_phone,
                'franchisee_photo' => $location->franchisee_photo,
                'franchisee_activate_date' => $location->franchisee_activate_date,
                'total_invested' => (float) $investment->total_invested,
                'investment_limit' => (float) $investment->investment_limit,
                'total_investors' => (int) $investment->total_investors,
                'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                'remaining_amount' => (float) $investment->remaining_amount,
                'progress_percentage' => (float) $investment->progress_percentage,
                'therapists' => $location->therapists->map(function ($therapist) {
                    return [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'email' => $therapist->email,
                        'phone' => $therapist->phone,
                        'image' => $therapist->image,
                        'bio' => $therapist->bio,
                        'work_start_date' => $therapist->work_start_date,
                        'status' => (bool) $therapist->status,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $locationData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch investment opportunity', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment opportunity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get location investment details
     */
    public function getLocationDetails($locationId): JsonResponse
    {
        try {
            $location = Location::active()->find($locationId);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found',
                ], 404);
            }

            $investment = $this->getOrCreateLocationInvestment($locationId);

            // Get recent investments for this location with more details
            $recentInvestments = $location->investments()
                ->with('user:id,name')
                ->where('status', 'completed')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(function ($investment) {
                    return [
                        'id' => $investment->id,
                        'amount' => (float) $investment->amount,
                        'investor_name' => $investment->user->name ?? 'Anonymous',
                        'invested_at' => $investment->created_at->toISOString(),
                        'status' => $investment->status,
                        'reference' => $investment->reference ?? 'N/A',
                        'payment_method' => $investment->payment_method ?? 'card',
                    ];
                });

            // Get therapists data manually to avoid relationship issues
            $therapists = collect();
            try {
                // Try to get therapists via the many-to-many relationship
                $therapistIds = \DB::table('location_therapist')
                    ->where('location_id', $locationId)
                    ->pluck('therapist_id');

                if ($therapistIds->isNotEmpty()) {
                    $therapistsData = \DB::table('therapists')
                        ->leftJoin('users', function ($join) {
                            // Handle different possible column names for the relationship
                            $join->on('therapists.user_id', '=', 'users.id')
                                ->orOn('therapists.id', '=', 'users.therapist_id');
                        })
                        ->whereIn('therapists.id', $therapistIds)
                        ->select([
                            'therapists.id',
                            'therapists.bio',
                            'therapists.work_start_date',
                            'therapists.status',
                            'users.name',
                            'users.email',
                            'users.phone',
                            'users.profile_image'
                        ])
                        ->get();

                    $therapists = $therapistsData->map(function ($therapist) {
                        return [
                            'id' => $therapist->id,
                            'name' => $therapist->name ?? 'Unknown',
                            'email' => $therapist->email ?? '',
                            'phone' => $therapist->phone ?? '',
                            'image' => $therapist->profile_image ?
                                (str_starts_with($therapist->profile_image, 'http') ?
                                    $therapist->profile_image :
                                    url('storage/' . $therapist->profile_image)
                                ) : null,
                            'bio' => $therapist->bio ?? '',
                            'work_start_date' => $therapist->work_start_date ?? '',
                            'status' => $therapist->status ?? true,
                        ];
                    });
                }
            } catch (\Exception $e) {
                // If therapists relationship fails, continue without therapists
                Log::warning('Could not load therapists for location: ' . $e->getMessage());
                $therapists = collect();
            }

            $data = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'address' => $location->address,
                'postcode' => $location->postcode ?? '',
                'phone' => $location->phone,
                'email' => $location->email,
                'description' => $location->description,
                'image' => $location->image_url,

                // Management Information
                'owner_name' => $location->owner_name,
                'owner_email' => $location->owner_email,
                'manager_name' => $location->manager_name,
                'manager_email' => $location->manager_email,
                'branch_phone' => $location->branch_phone,

                // Additional Details
                'operating_hours' => $location->operating_hours ?
                    json_decode($location->operating_hours, true) : null,
                'service_radius_miles' => $location->service_radius_miles ?? 5,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'status' => $location->status,

                // Investment Statistics
                'investment_stats' => [
                    'total_invested' => (float) $investment->total_invested,
                    'investment_limit' => (float) $investment->investment_limit,
                    'remaining_amount' => (float) $investment->remaining_amount,
                    'progress_percentage' => (float) $investment->progress_percentage,
                    'total_investors' => (int) $investment->total_investors,
                    'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                ],

                // Investment Data (keeping original structure for compatibility)
                'total_invested' => (float) $investment->total_invested,
                'investment_limit' => (float) $investment->investment_limit,
                'total_investors' => (int) $investment->total_investors,
                'is_open_for_investment' => (bool) $investment->is_open_for_investment,
                'remaining_amount' => (float) $investment->remaining_amount,
                'progress_percentage' => (float) $investment->progress_percentage,

                // Related Data
                'recent_investments' => $recentInvestments,
                'therapists' => $therapists->toArray(),

                // Timestamps
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Location details retrieved successfully',
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch location details', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Confirm payment (webhook or client confirmation)
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Set Stripe API key
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

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

            // Prevent double processing
            if ($investment->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'data' => $investment,
                    'message' => 'Investment already completed'
                ]);
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
            $this->updateLocationInvestmentTotals($investment);

            DB::commit();

            // Send confirmation email
            $this->sendInvestmentConfirmationEmail($investment);

            return response()->json([
                'success' => true,
                'data' => [
                    'investment' => $investment->fresh(),
                    'message' => 'Investment completed successfully'
                ]
            ]);
        } catch (ApiErrorException $e) {
            DB::rollback();
            Log::error('Stripe API error during payment confirmation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Payment confirmation failed', ['error' => $e->getMessage()]);
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
    public function getUserSummary(): JsonResponse
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
            Log::error('Failed to fetch user investment summary', ['error' => $e->getMessage()]);
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
            Log::error('API test failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'API test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getOrCreateLocationInvestment($locationId): LocationInvestment
    {
        return LocationInvestment::firstOrCreate(
            ['location_id' => $locationId],
            [
                'total_invested' => 0,
                'investment_limit' => 10000,
                'total_investors' => 0,
                'is_open_for_investment' => true,
            ]
        );
    }

    private function generateInvestmentReference(): string
    {
        do {
            $reference = 'INV-' . strtoupper(Str::random(8));
        } while (Investment::where('reference', $reference)->exists());

        return $reference;
    }

    private function processCardPayment(Investment $investment, float $amount, string $reference): JsonResponse
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = PaymentIntent::create([
            'amount' => $amount * 100, // Amount in pence
            'currency' => 'gbp',
            'metadata' => [
                'investment_id' => $investment->id,
                'user_id' => $investment->user_id,
                'location_id' => $investment->location_id,
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
    }

    private function processBankTransferPayment(Investment $investment): JsonResponse
    {
        // Send notification to admin team
        $this->notifyAdminTeamOfBankTransferRequest($investment);

        // Send confirmation email to user
        $this->sendBankTransferRequestEmail($investment);

        return response()->json([
            'success' => true,
            'data' => [
                'investment' => $investment,
                'message' => 'Investment request submitted successfully. You will receive an email confirmation shortly, and our admin team will contact you within 24 hours with bank transfer details.'
            ]
        ]);
    }

    private function sendBankTransferRequestEmail(Investment $investment): void
    {
        try {
            Log::info('Sending bank transfer request email', [
                'investment_id' => $investment->id,
                'user_email' => $investment->user->email
            ]);

            Mail::to($investment->user->email)->send(new BankTransferRequestMail($investment));

            Log::info('Bank transfer request email sent successfully', [
                'investment_id' => $investment->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bank transfer request email', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function confirmBankTransferPayment(Investment $investment, string $bankTransferDetails): void
    {
        $investment->update([
            'status' => 'completed',
            'bank_transfer_details' => $bankTransferDetails,
            'bank_transfer_confirmed_at' => now(),
            'confirmed_by_admin_id' => Auth::id(),
            'invested_at' => now(),
        ]);

        $this->updateLocationInvestmentTotals($investment);

        InvestmentTransaction::create([
            'investment_id' => $investment->id,
            'type' => 'bank_transfer',
            'amount' => $investment->amount,
            'status' => 'completed',
            'stripe_response' => json_encode(['bank_transfer_confirmed' => true]),
        ]);

        Log::info('Bank transfer confirmed', ['investment_id' => $investment->id]);
        // Send confirmation email to investor
        $this->sendBankTransferConfirmationEmail($investment);
    }

    private function sendBankTransferConfirmationEmail(Investment $investment): void
    {
        try {
            Log::info('Sending bank transfer confirmation email', [
                'investment_id' => $investment->id,
                'user_email' => $investment->user->email
            ]);

            Mail::to($investment->user->email)->send(new BankTransferConfirmedMail($investment));

            Log::info('Bank transfer confirmation email sent successfully', [
                'investment_id' => $investment->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bank transfer confirmation email', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function rejectBankTransferPayment(Investment $investment, string $bankTransferDetails): void
    {
        $investment->update([
            'status' => 'failed',
            'bank_transfer_details' => $bankTransferDetails,
            'confirmed_by_admin_id' => Auth::id(),
        ]);
    }

    private function updateLocationInvestmentTotals(Investment $investment): void
    {
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
    }

    private function sendInvestmentConfirmationEmail(Investment $investment): void
    {
        try {
            Log::info('Sending investment confirmation email', ['user_email' => $investment->user->email]);
            Mail::to($investment->user->email)->send(new InvestmentConfirmationMail($investment));
        } catch (\Exception $emailException) {
            Log::error('Failed to send investment confirmation email', ['error' => $emailException->getMessage()]);
        }
    }

    private function notifyAdminTeamOfBankTransferRequest(Investment $investment): void
    {
        try {
            // Send notification email to admin team
            $adminEmails = config('mail.admin_emails', ['admin@example.com']);

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new AdminBankTransferNotificationMail($investment));
            }

            Log::info('Admin notification sent for bank transfer request', [
                'investment_id' => $investment->id,
                'admin_emails' => $adminEmails
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification for bank transfer', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}