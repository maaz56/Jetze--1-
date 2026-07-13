<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\AirportMargin;
use Illuminate\Http\Request;
use Log;

class AirportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    
    public function index(Request $request)
    {
        $searchQuery = $request->input('search_query');
        $withPagination = filter_var($request->input('with_pagination', false), FILTER_VALIDATE_BOOLEAN);
        
        $query = Airport::query();

        if ($searchQuery) {
            $query->where(function($q) use ($searchQuery) {
                $q->where('city_name', 'like', '%' . $searchQuery . '%')
                  ->orWhere('name', 'like', '%' . $searchQuery . '%')
                  ->orWhere('iata_code', 'like', '%' . $searchQuery . '%')
                  ->orWhere('iata_city_code', 'like', '%' . $searchQuery . '%')
                  ->orWhere('iata_country_code', 'like', '%' . $searchQuery . '%');
            });
        }

        if ($withPagination) {
            $airports = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $airports->items(),
                'meta' => [
                    'current_page' => $airports->currentPage(),
                    'from' => $airports->firstItem(),
                    'last_page' => $airports->lastPage(),
                    'per_page' => $airports->perPage(),
                    'to' => $airports->lastItem(),
                    'total' => $airports->total(),
                ]
            ]);
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'iata_code' => 'required|string|max:10|unique:airports,iata_code',
            'name' => 'required|string|max:255',
            'iata_city_code' => 'nullable|string|max:10',
            'iata_country_code' => 'nullable|string|max:10',
            'city_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'time_zone' => 'nullable|string|max:100',
        ]);

        $airport = Airport::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Airport created successfully',
            'data' => $airport
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $airport = Airport::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $airport
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $airport = Airport::findOrFail($id);

        $validated = $request->validate([
            'iata_code' => 'required|string|max:10|unique:airports,iata_code,' . $id,
            'name' => 'required|string|max:255',
            'iata_city_code' => 'nullable|string|max:10',
            'iata_country_code' => 'nullable|string|max:10',
            'city_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'time_zone' => 'nullable|string|max:100',
        ]);

        $airport->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Airport updated successfully',
            'data' => $airport
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $airport = Airport::findOrFail($id);
        $airport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Airport deleted successfully'
        ]);
    }


    public function storeMargins(Request $request)
    {
        Log::info('Storing airport margins', $request->all());

        // Validate incoming data
        $request->validate([
            'domestic' => 'required',
            'international' => 'required',
        ]);

        // Fetch existing record (should be only one)
        $airport = AirportMargin::first();

        if ($airport) {
            // Update existing record
            $airport->update([
                'domestic' => $request->domestic,
                'international' => $request->international,
            ]);
        } else {
            // Create a new record
            AirportMargin::create([
                'domestic' => $request->domestic,
                'international' => $request->international,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Margins saved successfully'
        ]);
    }

    public function getMargins()
    {
        $margins = AirportMargin::first();
        // Log::info($margins);
        return response()->json($margins);
    }

    public function nearest(Request $request)
    {
        Log::info('Finding nearest airports', $request->all());
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $limit = (int) ($validated['limit'] ?? 5);

        $nearestAirports = Airport::query()
            ->select('*')
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) as distance_km',
                [$latitude, $longitude, $latitude]
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Nearest airports fetched successfully.',
            'data' => $nearestAirports,
        ]);
    }

    public function defaultSuggestions(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = (int) ($validated['limit'] ?? 7);

        $defaultAirports = Airport::query()
            ->whereNotNull('iata_code')
            ->where('iata_code', '!=', '')
            ->orderBy('city_name')
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Default airport suggestions fetched successfully.',
            'data' => $defaultAirports,
        ]);
    }


}
