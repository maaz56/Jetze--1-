<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Log;

class PromotionController extends Controller
{
    public function index()
    {
        return Promotion::with('airline')->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sale_channel' => 'required|string',
            'airline_id' => 'nullable|exists:airlines,id',
            'reservation_type' => 'required|string',
            'price_option' => 'required|in:markup,discount',
            'commission_type' => 'required|in:amount,percentage',
            'commission_value' => 'required|numeric|min:0',
            'travel_start_date' => 'nullable|date',
            'travel_end_date' => 'nullable|date',
            'ticketing_start_date' => 'nullable|date',
            'ticketing_end_date' => 'nullable|date',
        ]);

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

    public function update(Request $request, $id)
    {
        Log::info($request->all());
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sale_channel' => 'required|string',
            'airline_id' => 'nullable|exists:airlines,id',
            'reservation_type' => 'required|string',
            'price_option' => 'required|in:markup,discount',
            'commission_type' => 'required|in:amount,percentage',
            'commission_value' => 'required|numeric|min:0',
            'travel_start_date' => 'nullable|date',
            'travel_end_date' => 'nullable|date',
            'ticketing_start_date' => 'nullable|date',
            'ticketing_end_date' => 'nullable|date',
        ]);

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
}
