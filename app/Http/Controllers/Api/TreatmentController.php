<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    public function index()
    {
        $treatments = Treatment::where('status', 1)->get();

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
