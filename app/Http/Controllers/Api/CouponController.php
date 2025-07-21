<?php
// app/Http/Controllers/Api/CouponController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    /**
     * Validate a coupon code for a specific service
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'service_id' => 'required|exists:services,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code.',
            ], 422);
        }

        // Check if coupon is valid
        if (!$coupon->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => $coupon->getValidationMessage(),
            ], 422);
        }

        // Check if coupon is valid for the user (if authenticated)
        if (Auth::check() && !$coupon->isValidForUser(Auth::id())) {
            return response()->json([
                'valid' => false,
                'message' => 'You have already used this coupon the maximum number of times.',
            ], 422);
        }

        // Check if coupon is valid for the service
        if (!$coupon->isValidForService($request->service_id)) {
            return response()->json([
                'valid' => false,
                'message' => 'This coupon is not valid for the selected service.',
            ], 422);
        }

        // Check minimum amount requirement
        if ($coupon->minimum_amount && $request->amount < $coupon->minimum_amount) {
            return response()->json([
                'valid' => false,
                'message' => "This coupon requires a minimum purchase of £{$coupon->minimum_amount}.",
            ], 422);
        }

        // Calculate discount
        $discount = $coupon->calculateDiscount($request->amount);
        $finalAmount = max(0, $request->amount - $discount);

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'description' => $coupon->description,
            ],
            'discount_amount' => $discount,
            'original_amount' => $request->amount,
            'final_amount' => $finalAmount,
            'savings_percentage' => $request->amount > 0 ? round(($discount / $request->amount) * 100, 2) : 0,
        ]);
    }

    /**
     * Get active coupons for a service
     */
    public function getServiceCoupons($serviceId)
    {
        $service = Service::findOrFail($serviceId);

        // Get coupons that apply to this service or all services
        $coupons = Coupon::active()
            ->where(function ($query) use ($serviceId) {
                $query->whereDoesntHave('services') // Coupons that apply to all services
                    ->orWhereHas('services', function ($q) use ($serviceId) {
                        $q->where('service_id', $serviceId);
                    });
            })
            ->get()
            ->map(function ($coupon) {
                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'description' => $coupon->description,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'minimum_amount' => $coupon->minimum_amount,
                    'display_value' => $coupon->type === 'percentage' 
                        ? "{$coupon->value}% OFF" 
                        : "£{$coupon->value} OFF",
                ];
            });

        return response()->json([
            'service' => [
                'id' => $service->id,
                'title' => $service->title,
                'price' => $service->price,
            ],
            'available_coupons' => $coupons,
        ]);
    }

    /**
     * Get user's coupon usage history
     */
    public function getUserCouponHistory()
    {
        $user = Auth::user();

        $usages = $user->couponUsages()
            ->with(['coupon', 'booking.service'])
            ->latest()
            ->get()
            ->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'coupon_code' => $usage->coupon->code,
                    'service' => $usage->booking->service->title ?? 'N/A',
                    'discount_amount' => $usage->discount_amount,
                    'original_amount' => $usage->original_amount,
                    'final_amount' => $usage->final_amount,
                    'used_at' => $usage->created_at->format('M j, Y H:i'),
                    'booking_reference' => $usage->booking->reference ?? 'N/A',
                ];
            });

        return response()->json([
            'coupon_history' => $usages,
            'total_saved' => $usages->sum('discount_amount'),
        ]);
    }
}