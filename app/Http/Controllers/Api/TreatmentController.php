<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    public function index($locationId = null)
    {
        $query = Treatment::where('status', 1);

        if ($locationId) {
            // Only return treatments that have active services for this location
            $query->whereHas('services', function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->where('status', 1);
            });
        }

        $treatments = $query->get();

        return response()->json([
            'success' => true,
            'data' => $treatments,
        ]);
    }

    public function show($id)
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json([
                'success' => false,
                'message' => 'Treatment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $treatment,
        ]);
    }
}
