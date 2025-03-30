<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function getServicesForTreatment($treatmentId)
    {
        // Fetch services related to the treatment
        $services = Service::where('treatment_id', $treatmentId)->get();

        // Check if services exist and return them, else return a 404 message
        if ($services->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No services found for this treatment.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $services
        ], 200);
    }
}
