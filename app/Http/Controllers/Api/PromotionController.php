<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    /** List promotion rules for the admin panel. */
    public function index()
    {
        return Promotion::with('airline')->latest()->get();
    }

    /** Create one AED-based promotion rule. */
    public function store(Request $request)
    {
        $validated = $this->validatedPromotion($request);

        $promotion = Promotion::create($validated);

        return response()->json([
            'message' => 'Promotion created successfully',
            'promotion' => $promotion->load('airline')
        ], 201);
    }

    public function show($id)
    {
        return Promotion::with('airline')->findOrFail($id);
    }

    /** Update one AED-based promotion rule. */
    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $validated = $this->validatedPromotion($request);

        $promotion->update($validated);

        return response()->json([
            'message' => 'Promotion updated successfully',
            'promotion' => $promotion->load('airline')
        ]);
    }

    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully']);
    }

    public function getProviders()
    {
        // These are based on FlightController.php
        return response()->json([
            ['identifier' => 'OneApi', 'name' => 'OneApi'],
            ['identifier' => 'TravelPort-GDS', 'name' => 'TravelPort-GDS'],
            ['identifier' => 'TravelPort-NDC', 'name' => 'TravelPort-NDC'],
        ]);
    }

    /** Validate rule conditions and force all flat commercial values into AED. */
    private function validatedPromotion(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sale_channel' => 'required|string|max:100',
            'airline_id' => 'nullable|exists:airlines,id',
            'reservation_type' => 'required|in:ALL-SECTORS,INTERLINE,EX-PAKISTAN,SOTO,DOMESTIC,CODE-SHARE',
            'price_option' => 'required|in:markup,discount',
            'commission_type' => 'required|in:amount,percentage',
            'commission_value' => 'required|numeric|min:0',
            'travel_start_date' => 'nullable|date',
            'travel_end_date' => 'nullable|date|after_or_equal:travel_start_date',
            'ticketing_start_date' => 'nullable|date',
            'ticketing_end_date' => 'nullable|date|after_or_equal:ticketing_start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validated['price_option'] === 'discount'
            && $validated['commission_type'] === 'percentage'
            && (float) $validated['commission_value'] > 100) {
            throw ValidationException::withMessages([
                'commission_value' => 'A percentage discount cannot exceed 100%.',
            ]);
        }

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['value_currency'] = 'AED';

        return $validated;
    }
}
