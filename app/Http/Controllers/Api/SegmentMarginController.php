<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SegmentMargin;
use Illuminate\Http\Request;

class SegmentMarginController extends Controller
{
    /** List segment-margin rules for the admin panel. */
    public function index()
    {
        return SegmentMargin::with('airline')->latest()->get();
    }

    /** Create a per-segment, per-passenger AED adjustment rule. */
    public function store(Request $request)
    {
        $margin = SegmentMargin::create($this->validatedMargin($request));

        return response()->json([
            'message' => 'Segment margin created successfully',
            'margin' => $margin->load('airline'),
        ], 201);
    }

    public function show($id)
    {
        return SegmentMargin::with('airline')->findOrFail($id);
    }

    /** Update a per-segment, per-passenger AED adjustment rule. */
    public function update(Request $request, $id)
    {
        $margin = SegmentMargin::findOrFail($id);
        $validated = $this->validatedMargin($request);

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

    /** Validate scope/exclusions and normalize the legacy multi-airline column. */
    private function validatedMargin(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sale_channel' => 'required|string|max:100',
            'airline_id' => 'nullable|integer|exists:airlines,id',
            'excluded_airline_ids' => 'nullable|array',
            'excluded_airline_ids.*' => 'integer|distinct|exists:airlines,id',
            'margin_type' => 'required|in:markup,discount',
            'margin_value' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $airlineId = !empty($validated['airline_id']) ? (int) $validated['airline_id'] : null;
        $excludedAirlineIds = array_values(array_unique(array_map(
            'intval',
            $validated['excluded_airline_ids'] ?? [],
        )));
        // A targeted airline cannot also be excluded from its own override.
        $excludedAirlineIds = array_values(array_diff($excludedAirlineIds, $airlineId ? [$airlineId] : []));

        unset($validated['excluded_airline_ids']);

        return array_merge($validated, [
            'airline_id' => $airlineId,
            'airline_ids' => [],
            'disabled_airline_ids' => $excludedAirlineIds,
            'is_active' => $validated['is_active'] ?? true,
            'value_currency' => 'AED',
        ]);
    }
}
