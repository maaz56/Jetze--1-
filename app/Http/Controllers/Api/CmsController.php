<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PopularRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CmsController extends Controller
{

    public function getPopularRoutes(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search_query');

            $query = PopularRoute::query()
                ->with(['airline'])
                ->when($search, function ($q) use ($search) {
                    $search = '%' . trim($search) . '%';
                    $q->where(function ($q) use ($search) {
                        $q->where('from_airport', 'like', $search)
                            ->orWhere('to_airport', 'like', $search)
                            ->orWhere('destination_name_en', 'like', $search)
                            ->orWhere('destination_name_ar', 'like', $search)
                            ->orWhereHas('airline', function ($sub) use ($search) {
                                $sub->where('name', 'like', $search)
                                    ->orWhere('iata_code', 'like', $search);
                            });
                    });
                })
                ->orderBy('created_at', 'desc'); // or orderBy('id', 'desc') if preferred

            

            $routes = $query->paginate($perPage);


            return response()->json([
                'success' => true,
                'data' => $routes->items(),
                'meta' => [
                    'current_page' => $routes->currentPage(),
                    'last_page' => $routes->lastPage(),
                    'per_page' => $routes->perPage(),
                    'total' => $routes->total(),
                    'from' => $routes->firstItem(),
                    'to' => $routes->lastItem(),
                ],
                'filters' => [
                    'search_query' => $search ?: null,
                    'per_page' => $perPage,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch popular routes list', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular routes'
            ], 500);
        }
    }
    public function storePopularRoutes(Request $request)
    {
        Log::info($request->all());
        // Validation
        $validator = Validator::make($request->all(), [
            'fromAirport' => 'required|exists:airports,iata_code',
            'toAirport' => 'required|exists:airports,iata_code',
            'image' => 'nullable|image|max:2048', // optional, max 2MB
            'airline' => 'nullable|exists:airlines,id',
            'journeyType' => 'required|string',
            'travelClass' => 'required|string',
            'departurePlusDays' => 'required|integer',
            'stayDurationDays' => 'nullable|integer',
            'priceType' => 'required|in:dynamic,static',
            'dynamicRefreshHours' => 'nullable|integer|required_if:priceType,dynamic',
            'staticPrice' => 'nullable|numeric|required_if:priceType,static',
            'destinationNameEn' => 'required|string|max:255',
            'destinationNameAr' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('popular_routes', 'public');
        }

        // Create Popular Route
        $route = PopularRoute::create([
            'from_airport' => $request->fromAirport,
            'to_airport' => $request->toAirport,
            'image' => $imagePath,
            'airline_id' => $request->airline,
            'journey_type' => $request->journeyType,
            'travel_class' => $request->travelClass,
            'departure_plus_days' => $request->departurePlusDays,
            'stay_duration_days' => $request->stayDurationDays,
            'price_type' => $request->priceType,
            'dynamic_refresh_hours' => $request->dynamicRefreshHours,
            'static_price' => $request->staticPrice,
            'destination_name_en' => $request->destinationNameEn,
            'destination_name_ar' => $request->destinationNameAr,
        ]);

        return response()->json([
            'message' => 'Popular route created successfully',
            'route' => $route
        ], 201);
    }

    public function deletePopularRoutes($id)
    {
        try {
            $route = PopularRoute::find($id);

            if (!$route) {
                return response()->json([
                    'success' => false,
                    'message' => 'Popular route not found',
                    'errors' => ['id' => ['The selected route does not exist']]
                ], 404);
            }

            // 1. Delete the associated image from storage (if exists)
            if ($route->image) {
                Storage::disk('public')->delete($route->image);
            }

            // 2. Delete the database record
            $route->delete();

            return response()->json([
                'success' => true,
                'message' => 'Popular route deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Popular route deletion failed', [
                'route_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete popular route. Please try again later.',
                // In production you usually don't expose the real error message
                // 'error'   => $e->getMessage()   // ← only for development/debug
            ], 500);
        }
    }
}
