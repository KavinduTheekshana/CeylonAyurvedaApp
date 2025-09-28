<?php
// app/Http/Controllers/Api/PatientTreatmentHistoryController.php - Updated for Full Details

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentHistory;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientTreatmentHistoryController extends Controller
{
    /**
     * Get treatment history for a specific booking (patient view)
     */
    public function getByBooking(Request $request, $bookingId)
    {
        try {
            $user = Auth::user();

            // Verify booking belongs to the authenticated user
            $booking = Booking::where('id', $bookingId)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or not accessible'
                ], 404);
            }

            // Get treatment history for this booking
            $history = TreatmentHistory::where('booking_id', $bookingId)
                ->with([
                    'booking:id,reference,date,time,address_line1,city,postcode',
                    'service:id,title,duration',
                    'therapist:id,name'
                ])
                ->first();

            if (!$history) {
                return response()->json([
                    'success' => false,
                    'message' => 'No treatment history found for this booking'
                ], 404);
            }

            // Return patient-safe data with correct field mapping
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $history->id,
                    'booking' => [
                        'reference' => $booking->reference,
                        'date' => $booking->date,
                        'time' => $booking->time,
                        'formatted_date' => \Carbon\Carbon::parse($booking->date)->format('l, F j, Y'),
                        'formatted_time' => \Carbon\Carbon::parse($booking->time)->format('g:i A'),
                        'address' => [
                            'line1' => $booking->address_line1,
                            'city' => $booking->city,
                            'postcode' => $booking->postcode,
                        ]
                    ],
                    'service' => [
                        'title' => $history->service->title,
                        'duration' => $history->service->duration,
                    ],
                    'therapist' => [
                        'name' => $history->therapist->name,
                    ],
                    'treatment_details' => [
                        'treatment_notes' => $history->treatment_notes,
                        'observations' => $history->observations,
                        'patient_condition' => $history->patient_condition,
                        'condition_description' => $this->getConditionDescription($history->patient_condition),
                        'pain_improvement' => $this->getPainImprovement($history->pain_level_before, $history->pain_level_after),
                        'areas_treated' => $history->areas_treated,
                        'treatment_completed_at' => $history->treatment_completed_at,
                        'formatted_treatment_date' => $history->treatment_completed_at->format('F j, Y \a\t g:i A'),
                    ],
                    'recommendations' => $this->sanitizeRecommendations($history->recommendations),
                    'next_treatment_plan' => $this->sanitizeNextTreatmentPlan($history->next_treatment_plan),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all treatment histories for authenticated user - Updated with Full Details
     */
    public function getUserTreatmentHistories(Request $request)
    {
        try {
            $user = Auth::user();

            $histories = TreatmentHistory::whereHas('booking', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('status', 'completed');
                })
                ->with([
                    'booking:id,reference,date,time,address_line1,city,postcode',
                    'service:id,title,duration',
                    'therapist:id,name'
                ])
                ->orderBy('treatment_completed_at', 'desc')
                ->paginate(10);

            $data = $histories->getCollection()->map(function ($history) {
                return [
                    'id' => $history->id,
                    'booking_id' => $history->booking->id,
                    'booking_reference' => $history->booking->reference,
                    'service_title' => $history->service->title,
                    'therapist_name' => $history->therapist->name,
                    'treatment_date' => $history->booking->date,
                    'treatment_time' => \Carbon\Carbon::parse($history->booking->time)->format('g:i A'),
                    'formatted_date' => \Carbon\Carbon::parse($history->booking->date)->format('M j, Y'),
                    'patient_condition' => $history->patient_condition,
                    'condition_description' => $this->getConditionDescription($history->patient_condition),
                    'pain_improvement' => $this->getPainImprovement($history->pain_level_before, $history->pain_level_after),
                    'treatment_completed_at' => $history->treatment_completed_at,
                    'has_recommendations' => !empty($history->recommendations),
                    'has_treatment_notes' => !empty($history->treatment_notes),
                    'has_observations' => !empty($history->observations),
                    
                    // Full details for expanded view
                    'treatment_notes' => $this->sanitizeRecommendations($history->treatment_notes),
                    'observations' => $this->sanitizeRecommendations($history->observations),
                    'recommendations' => $this->sanitizeRecommendations($history->recommendations),
                    'next_treatment_plan' => $this->sanitizeNextTreatmentPlan($history->next_treatment_plan),
                    'areas_treated' => $history->areas_treated,
                    
                    // Additional booking details
                    'address_line1' => $history->booking->address_line1,
                    'city' => $history->booking->city,
                    'postcode' => $history->booking->postcode,
                    'duration' => $history->service->duration,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment histories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getConditionDescription($condition)
    {
        return match($condition) {
            'improved' => 'Your condition has improved after treatment',
            'same' => 'Your condition remains stable',
            'worse' => 'Please consult with your therapist for further guidance',
            default => 'No assessment recorded'
        };
    }

    private function getPainImprovement($before, $after)
    {
        if (!$before || !$after) {
            return null;
        }

        $improvement = $before - $after;
        return [
            'before' => $before,
            'after' => $after,
            'improvement' => $improvement,
            'improvement_percentage' => $before > 0 ? round(($improvement / $before) * 100) : 0,
            'description' => $improvement > 0 
                ? "Pain reduced by {$improvement} point(s)" 
                : ($improvement < 0 
                    ? "Pain increased by " . abs($improvement) . " point(s)" 
                    : "Pain level remained the same")
        ];
    }

    private function sanitizeRecommendations($content)
    {
        if (empty($content)) {
            return null;
        }

        return strip_tags($content);
    }

    private function sanitizeNextTreatmentPlan($nextPlan)
    {
        if (empty($nextPlan)) {
            return null;
        }

        return strip_tags($nextPlan);
    }
}