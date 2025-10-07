<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\TreatmentHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TherapistPatientController extends Controller
{
    /**
     * Get all patients who have booked services with the authenticated therapist
     */
    public function getPatients(Request $request)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get pagination parameters
            $perPage = min($request->get('per_page', 20), 100);
            $search = $request->get('search', '');

            // Get unique patients who have booked with this therapist
            $patientsQuery = User::select([
                'users.id',
                'users.name', 
                'users.email',
                'users.profile_photo_path',
                DB::raw('COUNT(DISTINCT bookings.id) as total_appointments'),
                DB::raw('MAX(bookings.date) as last_appointment_date'),
                DB::raw('MIN(bookings.date) as first_appointment_date'),
                DB::raw('COUNT(CASE WHEN bookings.status = "completed" THEN 1 END) as completed_appointments'),
                DB::raw('COUNT(CASE WHEN bookings.status = "confirmed" THEN 1 END) as upcoming_appointments'),
                DB::raw('SUM(bookings.price) as total_spent')
            ])
            ->join('bookings', 'users.id', '=', 'bookings.user_id')
            ->where('bookings.therapist_id', $therapist->id)
            ->whereNotNull('bookings.user_id') // Exclude guest bookings
            ->groupBy('users.id', 'users.name', 'users.email', 'users.profile_photo_path');

            // Apply search filter if provided
            if (!empty($search)) {
                $patientsQuery->where(function ($query) use ($search) {
                    $query->where('users.name', 'LIKE', "%{$search}%")
                          ->orWhere('users.email', 'LIKE', "%{$search}%");
                });
            }

            // Order by most recent appointment first
            $patients = $patientsQuery
                ->orderBy('last_appointment_date', 'desc')
                ->paginate($perPage);

            // Transform the data to match the expected format
            $transformedPatients = $patients->getCollection()->map(function ($patient) {
                // Format profile photo URL
                $profilePhotoUrl = null;
                if ($patient->profile_photo_path) {
                    $profilePhotoUrl = str_starts_with($patient->profile_photo_path, 'http') 
                        ? $patient->profile_photo_path 
                        : asset('storage/' . $patient->profile_photo_path);
                }

                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => null, // We don't store phone in users table, only in bookings
                    'image' => $profilePhotoUrl,
                    'total_appointments' => (int) $patient->total_appointments,
                    'completed_appointments' => (int) $patient->completed_appointments,
                    'upcoming_appointments' => (int) $patient->upcoming_appointments,
                    'last_appointment' => $patient->last_appointment_date,
                    'first_appointment' => $patient->first_appointment_date,
                    'total_spent' => (float) ($patient->total_spent ?? 0),
                    'patient_since' => $patient->first_appointment_date ? 
                        Carbon::parse($patient->first_appointment_date)->format('M Y') : 'Unknown',
                    'last_visit_formatted' => $patient->last_appointment_date ? 
                        Carbon::parse($patient->last_appointment_date)->format('d M Y') : 'Never',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedPatients,
                'pagination' => [
                    'current_page' => $patients->currentPage(),
                    'last_page' => $patients->lastPage(),
                    'per_page' => $patients->perPage(),
                    'total' => $patients->total(),
                    'count' => $transformedPatients->count(),
                ],
                'meta' => [
                    'therapist_id' => $therapist->id,
                    'search_query' => $search,
                    'total_unique_patients' => $patients->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching therapist patients: ' . $e->getMessage(), [
                'therapist_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patients',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific patient for this therapist
     */
    public function getPatientDetails(Request $request, $patientId)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Verify this patient has bookings with this therapist
            $hasBookings = Booking::where('therapist_id', $therapist->id)
                ->where('user_id', $patientId)
                ->exists();

            if (!$hasBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found or no bookings with this therapist'
                ], 404);
            }

            // Get patient basic info
            $patient = User::find($patientId);
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Get patient's booking history with this therapist
            $bookings = Booking::where('therapist_id', $therapist->id)
                ->where('user_id', $patientId)
                ->with(['service:id,title,duration'])
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'date' => $booking->date,
                        'time' => $booking->time,
                        'status' => $booking->status,
                        'reference' => $booking->reference,
                        'service' => [
                            'id' => $booking->service->id,
                            'title' => $booking->service->title,
                            'duration' => $booking->service->duration,
                        ],
                        'price' => (float) $booking->price,
                        'notes' => $booking->notes,
                        'created_at' => $booking->created_at->toISOString(),
                    ];
                });

            // Calculate statistics
            $stats = [
                'total_bookings' => $bookings->count(),
                'completed_bookings' => $bookings->where('status', 'completed')->count(),
                'upcoming_bookings' => $bookings->where('status', 'confirmed')->count(),
                'cancelled_bookings' => $bookings->where('status', 'cancelled')->count(),
                'total_spent' => $bookings->where('status', '!=', 'cancelled')->sum('price'),
                'average_booking_value' => $bookings->where('status', '!=', 'cancelled')->avg('price') ?? 0,
                'first_booking_date' => $bookings->min('date'),
                'last_booking_date' => $bookings->max('date'),
            ];

            // Format profile photo URL
            $profilePhotoUrl = null;
            if ($patient->profile_photo_path) {
                $profilePhotoUrl = str_starts_with($patient->profile_photo_path, 'http') 
                    ? $patient->profile_photo_path 
                    : asset('storage/' . $patient->profile_photo_path);
            }

            $patientDetails = [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'image' => $profilePhotoUrl,
                'created_at' => $patient->created_at->toISOString(),
                'email_verified_at' => $patient->email_verified_at?->toISOString(),
                'stats' => $stats,
                'recent_bookings' => $bookings->take(10)->values(), // Last 10 bookings
                'all_bookings' => $bookings->values(),
            ];

            return response()->json([
                'success' => true,
                'data' => $patientDetails,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient details: ' . $e->getMessage(), [
                'therapist_id' => $request->user()?->id,
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get patient statistics for the therapist dashboard
     */
    public function getPatientStats(Request $request)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get various patient statistics
            $totalUniquePatients = User::whereHas('bookings', function ($query) use ($therapist) {
                $query->where('therapist_id', $therapist->id);
            })->count();

            $newPatientsThisMonth = User::whereHas('bookings', function ($query) use ($therapist) {
                $query->where('therapist_id', $therapist->id)
                      ->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
            })->count();

            $returningPatients = User::whereHas('bookings', function ($query) use ($therapist) {
                $query->where('therapist_id', $therapist->id);
            })->whereHas('bookings', function ($query) use ($therapist) {
                $query->where('therapist_id', $therapist->id);
            }, '>', 1)->count();

            $activePatients = User::whereHas('bookings', function ($query) use ($therapist) {
                $query->where('therapist_id', $therapist->id)
                      ->where('date', '>=', Carbon::now()->subMonths(3));
            })->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_patients' => $totalUniquePatients,
                    'new_patients_this_month' => $newPatientsThisMonth,
                    'returning_patients' => $returningPatients,
                    'active_patients' => $activePatients, // Patients with bookings in last 3 months
                    'patient_retention_rate' => $totalUniquePatients > 0 
                        ? round(($returningPatients / $totalUniquePatients) * 100, 1) 
                        : 0,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient stats: ' . $e->getMessage(), [
                'therapist_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Search patients by name or email
     */
    public function searchPatients(Request $request)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $searchQuery = $request->get('query', '');
            $limit = min($request->get('limit', 10), 50);

            if (strlen($searchQuery) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query must be at least 2 characters long'
                ], 400);
            }

            $patients = User::select([
                'users.id',
                'users.name',
                'users.email',
                'users.profile_photo_path',
                DB::raw('COUNT(bookings.id) as total_appointments'),
                DB::raw('MAX(bookings.date) as last_appointment')
            ])
            ->join('bookings', 'users.id', '=', 'bookings.user_id')
            ->where('bookings.therapist_id', $therapist->id)
            ->where(function ($query) use ($searchQuery) {
                $query->where('users.name', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('users.email', 'LIKE', "%{$searchQuery}%");
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.profile_photo_path')
            ->orderBy('users.name')
            ->limit($limit)
            ->get()
            ->map(function ($patient) {
                $profilePhotoUrl = null;
                if ($patient->profile_photo_path) {
                    $profilePhotoUrl = str_starts_with($patient->profile_photo_path, 'http') 
                        ? $patient->profile_photo_path 
                        : asset('storage/' . $patient->profile_photo_path);
                }

                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'image' => $profilePhotoUrl,
                    'total_appointments' => (int) $patient->total_appointments,
                    'last_appointment' => $patient->last_appointment,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $patients,
                'meta' => [
                    'search_query' => $searchQuery,
                    'results_count' => $patients->count(),
                    'limit' => $limit,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching patients: ' . $e->getMessage(), [
                'therapist_id' => $request->user()?->id,
                'search_query' => $request->get('query', ''),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search patients',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getPatientTreatmentHistory(Request $request, $patientId)
    {
        try {
            $therapist = $request->user();
            
            // Get treatment histories for this patient and therapist
            $treatmentHistories = TreatmentHistory::where('therapist_id', $therapist->id)
                ->whereHas('booking', function ($query) use ($patientId) {
                    $query->where('user_id', $patientId);
                })
                ->with([
                    'booking:id,reference,date,time,user_id,service_id',
                    'service:id,title,duration'
                ])
                ->orderBy('treatment_completed_at', 'desc')
                ->get();

            // Format the response
            $formattedHistories = $treatmentHistories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'booking_reference' => $history->booking->reference,
                    'booking_date' => $history->booking->date,
                    'booking_time' => $history->booking->time,
                    'service_title' => $history->service->title,
                    'service_duration' => $history->service->duration,
                    'patient_name' => $history->patient_name,
                    'treatment_notes' => $history->treatment_notes,
                    'observations' => $history->observations,
                    'recommendations' => $history->recommendations,
                    'patient_condition' => $history->patient_condition,
                    'pain_level_before' => $history->pain_level_before,
                    'pain_level_after' => $history->pain_level_after,
                    'areas_treated' => $history->areas_treated,
                    'next_treatment_plan' => $history->next_treatment_plan,
                    'treatment_completed_at' => $history->treatment_completed_at,
                    'created_at' => $history->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedHistories,
                'total' => $treatmentHistories->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get treatment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}