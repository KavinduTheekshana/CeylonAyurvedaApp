<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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

    public function detail($id)
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service
        ]);
    }

    public function index()
    {
        $services = Service::where('status', true)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    public function store(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'treatment_id' => 'required|exists:treatments,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'benefits' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'active' => 'boolean',
            'image' => 'nullable|image|max:2048' // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create new service
        $service = new Service();
        $service->treatment_id = $request->treatment_id;
        $service->title = $request->title;
        $service->subtitle = $request->subtitle;
        $service->description = $request->description;
        $service->price = $request->price;
        $service->duration = $request->duration;
        $service->benefits = $request->benefits;
        $service->display_order = $request->display_order ?? 0;
        $service->active = $request->has('active') ? $request->active : true;

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $service->image = $path;
        }

        $service->save();

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => $service
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Find the service
        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'treatment_id' => 'sometimes|exists:treatments,id',
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'duration' => 'sometimes|required|integer|min:1',
            'benefits' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'active' => 'boolean',
            'image' => 'nullable|image|max:2048' // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update service fields
        if ($request->has('treatment_id')) $service->treatment_id = $request->treatment_id;
        if ($request->has('title')) $service->title = $request->title;
        if ($request->has('subtitle')) $service->subtitle = $request->subtitle;
        if ($request->has('description')) $service->description = $request->description;
        if ($request->has('price')) $service->price = $request->price;
        if ($request->has('duration')) $service->duration = $request->duration;
        if ($request->has('benefits')) $service->benefits = $request->benefits;
        if ($request->has('display_order')) $service->display_order = $request->display_order;
        if ($request->has('active')) $service->active = $request->active;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }

            // Upload new image
            $path = $request->file('image')->store('services', 'public');
            $service->image = $path;
        }

        $service->save();

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => $service
        ]);
    }

    public function destroy($id)
    {
        // Find the service
        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        // Delete the image
        if ($service->image) {
            Storage::disk('public')->delete($service->image);
        }

        // Delete the service
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }
}
