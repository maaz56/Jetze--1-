<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SegmentMargin;
use Illuminate\Http\Request;
use Log;

class SegmentMarginController extends Controller
{
    public function index()
    {
        return SegmentMargin::with('airline')->latest()->get();
    }

   public function store(Request $request)
{   
    Log::info('Creating segment margin with data: ', $request->all());
    
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'sale_channel' => 'required|string',
        'airline_id' => 'nullable|exists:airlines,id',
        'airline_ids' => 'nullable|array',
        'airline_ids.*' => 'integer|exists:airlines,id',
        'disabled_airline_ids' => 'nullable|array',
        'disabled_airline_ids.*' => 'integer|exists:airlines,id',
        'margin_type' => 'required|string|in:markup,discount', // Added string validation
        'margin_value' => 'required|numeric|min:0',
    ]);
    
    // Create margin by assigning fields one by one
    $margin = new SegmentMargin();
    $margin->title = $validated['title'];
    $margin->sale_channel = $validated['sale_channel'];
    $margin->airline_id = $validated['airline_id'] ?? null;
    $margin->airline_ids = $validated['airline_ids'] ?? [];
    $margin->disabled_airline_ids = $validated['disabled_airline_ids'] ?? [];
    $margin->margin_type = (string) $validated['margin_type']; // Explicitly cast to string
    $margin->margin_value = $validated['margin_value'];
    $margin->save();

    return response()->json([
        'message' => 'Segment margin created successfully',
        'margin' => $margin->load('airline')
    ], 201);
}
    public function show($id)
    {
        return SegmentMargin::with('airline')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $margin = SegmentMargin::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sale_channel' => 'required|string',
            'airline_id' => 'nullable|exists:airlines,id',
            'airline_ids' => 'nullable|array',
            'airline_ids.*' => 'integer|exists:airlines,id',
            'disabled_airline_ids' => 'nullable|array',
            'disabled_airline_ids.*' => 'integer|exists:airlines,id',
            'margin_type' => 'required|in:markup,discount',
            'margin_value' => 'required|numeric|min:0',
        ]);

        $margin->update($validated);

        return response()->json([
            'message' => 'Segment margin updated successfully',
            'margin' => $margin->load('airline')
        ]);
    }

    public function destroy($id)
    {
        $margin = SegmentMargin::findOrFail($id);
        $margin->delete();

        return response()->json(['message' => 'Segment margin deleted successfully']);
    }

    public function getProviders()
    {
        return response()->json([
            ['identifier' => 'OneApi', 'name' => 'OneApi'],
            ['identifier' => 'TravelPort-GDS', 'name' => 'TravelPort-GDS'],
            ['identifier' => 'TravelPort-NDC', 'name' => 'TravelPort-NDC'],
        ]);
    }
}
