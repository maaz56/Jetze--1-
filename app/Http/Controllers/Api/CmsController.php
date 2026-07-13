<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PopularRoutesCampaignMail;
use App\Models\NewsletterSubscriber;
use App\Models\PopularRoute;
use App\Models\SeoMeta;
use App\Models\TopAirline;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CmsController extends Controller
{
    private function mapPopularRoute(PopularRoute $route): array
    {
        $route->queueDynamicPriceRefreshIfDue();

        $data = $route->toArray();
        $departurePlusDays = $route->departure_plus_days ?? 1;
        $departureDate = Carbon::today()->addDays((int) $departurePlusDays);
        $data['departure_date'] = $departureDate->toDateString();
        $data['return_date'] = $route->journey_type === 'round' && $route->stay_duration_days !== null
            ? $departureDate->copy()->addDays((int) $route->stay_duration_days)->toDateString()
            : null;
        $data['image_url'] = $route->image ? asset('storage/' . $route->image) : null;
        if (!empty($data['seo']['og_image'])) {
            $data['seo']['og_image_url'] = asset('storage/' . $data['seo']['og_image']);
        }
        return $data;
    }

    public function getPopularRoutes(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search_query');

            $query = PopularRoute::query()
                ->with(['airline', 'seo'])
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
            $mappedRoutes = collect($routes->items())->map(fn($item) => $this->mapPopularRoute($item));


            return response()->json([
                'success' => true,
                'data' => $mappedRoutes,
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

    public function getPopularRoute($id)
    {
        $route = PopularRoute::with(['airline', 'seo'])->find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Popular route not found',
                'errors' => ['id' => ['The selected route does not exist']]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapPopularRoute($route)
        ]);
    }

    private function popularRouteValidationRules(bool $isUpdate = false): array
    {
        return [
            'fromAirport' => 'required|exists:airports,iata_code',
            'toAirport' => 'required|exists:airports,iata_code|different:fromAirport',
            'type' => 'required|in:domestic,international',
            'image' => ($isUpdate ? 'nullable' : 'required') . '|image|max:2048',
            'airline' => 'nullable|exists:airlines,id',
            'journeyType' => 'required|in:round,one_way',
            'travelClass' => 'required|in:economy,business',
            'departurePlusDays' => 'required|integer|min:0',
            'stayDurationDays' => 'nullable|integer|min:0|required_if:journeyType,round',
            'priceType' => 'required|in:dynamic,static',
            'dynamicRefreshHours' => 'nullable|integer|min:1|required_if:priceType,dynamic',
            'staticPrice' => 'nullable|numeric|min:0|required_if:priceType,static',
            'destinationNameEn' => 'required|string|max:255',
            'destinationNameAr' => 'required|string|max:255',
            'blogs' => 'nullable|string', // Will be validated as JSON string
            'faqs' => 'nullable|string', // Will be validated as JSON string
            'focus_keyword' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'boolean',
            'no_follow' => 'boolean',
        ];
    }

    private function syncPopularRouteSeo(PopularRoute $route, Request $request): void
    {
        $seo = $route->seo ?? new SeoMeta();
        if (!$seo->exists) {
            $seo->seoable_id = $route->id;
            $seo->seoable_type = PopularRoute::class;
        }

        $seo->fill([
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'meta_keywords' => $request->input('focus_keyword'),
            'og_title' => $request->input('og_title'),
            'og_description' => $request->input('og_description'),
            'og_image' => $route->image,
            'canonical_url' => $request->input('canonical_url'),
            'no_index' => $request->boolean('no_index', false),
            'no_follow' => $request->boolean('no_follow', false),
        ]);

        $seo->save();
    }

    public function storePopularRoutes(Request $request)
    {
        Log::info($request->all());
        // Validation
        $validator = Validator::make($request->all(), $this->popularRouteValidationRules(false));

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
            'type' => $request->type,
            'image' => $imagePath,
            'airline_id' => $request->airline,
            'journey_type' => $request->journeyType,
            'travel_class' => $request->travelClass,
            'departure_plus_days' => $request->departurePlusDays,
            'stay_duration_days' => $request->journeyType === 'round' ? $request->stayDurationDays : null,
            'price_type' => $request->priceType,
            'dynamic_refresh_hours' => $request->priceType === 'dynamic' ? $request->dynamicRefreshHours : null,
            'static_price' => $request->priceType === 'static' ? $request->staticPrice : null,
            'destination_name_en' => $request->destinationNameEn,
            'destination_name_ar' => $request->destinationNameAr,
            'blogs' => $request->has('blogs') && !empty($request->blogs) ? json_decode($request->blogs, true) : null,
            'faqs' => $request->has('faqs') && !empty($request->faqs) ? json_decode($request->faqs, true) : null,
        ]);
        $this->syncPopularRouteSeo($route, $request);

        return response()->json([
            'success' => true,
            'message' => 'Popular route created successfully',
            'route' => $this->mapPopularRoute($route->fresh(['airline', 'seo']))
        ], 201);
    }

    public function updatePopularRoute(Request $request, $id)
    {
        $route = PopularRoute::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Popular route not found',
                'errors' => ['id' => ['The selected route does not exist']]
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->popularRouteValidationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
            if ($route->image) {
                Storage::disk('public')->delete($route->image);
            }
            $route->image = $request->file('image')->store('popular_routes', 'public');
        }

        $route->update([
            'from_airport' => $request->fromAirport,
            'to_airport' => $request->toAirport,
            'type' => $request->type,
            'airline_id' => $request->airline,
            'journey_type' => $request->journeyType,
            'travel_class' => $request->travelClass,
            'departure_plus_days' => $request->departurePlusDays,
            'stay_duration_days' => $request->journeyType === 'round' ? $request->stayDurationDays : null,
            'price_type' => $request->priceType,
            'dynamic_refresh_hours' => $request->priceType === 'dynamic' ? $request->dynamicRefreshHours : null,
            'static_price' => $request->priceType === 'static' ? $request->staticPrice : null,
            'destination_name_en' => $request->destinationNameEn,
            'destination_name_ar' => $request->destinationNameAr,
            'image' => $route->image,
            'blogs' => $request->has('blogs') && !empty($request->blogs) ? json_decode($request->blogs, true) : null,
            'faqs' => $request->has('faqs') && !empty($request->faqs) ? json_decode($request->faqs, true) : null,
        ]);
        $this->syncPopularRouteSeo($route->fresh('seo'), $request);

        return response()->json([
            'success' => true,
            'message' => 'Popular route updated successfully',
            'route' => $this->mapPopularRoute($route->fresh(['airline', 'seo']))
        ]);
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

            // 2. Delete related SEO and the database record
            $route->seo()->delete();
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

    public function sendSelectedPopularRoutesMail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_ids' => 'required|array|min:1',
            'route_ids.*' => 'integer|exists:popular_routes,id',
            'audience' => ['required', Rule::in(['customers', 'subscribers', 'both'])],
        ]);

        $routeIds = collect($validated['route_ids'])->unique()->values();
        $routes = PopularRoute::query()
            ->with(['airline'])
            ->whereIn('id', $routeIds)
            ->get()
            ->sortBy(fn ($route) => $routeIds->search($route->id))
            ->values();

        if ($routes->isEmpty()) {
            return response()->json(['message' => 'Please select at least one popular route.'], 422);
        }

        $frontend = rtrim(config('app.frontend_url'), '/');
        $routeItems = $routes->map(function (PopularRoute $route) use ($frontend) {
            $mapped = $this->mapPopularRoute($route);
            $query = [
                'origin' => $route->from_airport,
                'destination' => $route->to_airport,
                'departure_date' => $mapped['departure_date'] ?? null,
                'flightType' => $route->journey_type === 'round' ? 'return' : 'one-way',
                'cabin_class' => $route->travel_class === 'business' ? 'C' : 'Y',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ];

            if (!empty($mapped['return_date'])) {
                $query['return_date'] = $mapped['return_date'];
            }

            return [
                'title' => $route->destination_name_en ?: ($route->from_airport . ' to ' . $route->to_airport),
                'from_airport' => $route->from_airport,
                'to_airport' => $route->to_airport,
                'from_city' => $mapped['from_city'] ?? $route->from_airport,
                'to_city' => $mapped['to_city'] ?? $route->to_airport,
                'airline_type' => ucfirst((string) $route->type),
                'journey_type' => $route->journey_type === 'round' ? 'Round-trip' : 'One-way',
                'travel_class' => ucfirst((string) $route->travel_class),
                'price' => $route->static_price ? number_format((float) $route->static_price) : null,
                'image_url' => $mapped['image_url'] ?? null,
                'url' => $frontend . '/popular-routes/' . $route->id . '?' . http_build_query($query),
            ];
        })->all();

        $recipients = [];
        $addRecipient = function (?string $email, ?string $name = null) use (&$recipients) {
            $email = trim((string) $email);
            $key = strtolower($email);

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($recipients[$key])) {
                return;
            }

            $recipients[$key] = [
                'email' => $email,
                'name' => $name,
            ];
        };

        if (in_array($validated['audience'], ['customers', 'both'], true)) {
            User::query()
                ->whereIn('role', ['customer', 'user'])
                ->whereNotNull('email')
                ->select(['id', 'name', 'email'])
                ->chunkById(500, function ($users) use ($addRecipient) {
                    foreach ($users as $user) {
                        $addRecipient($user->email, $user->name);
                    }
                });
        }

        if (in_array($validated['audience'], ['subscribers', 'both'], true)) {
            NewsletterSubscriber::query()
                ->where('is_active', true)
                ->whereNotNull('email')
                ->select(['id', 'name', 'email'])
                ->chunkById(500, function ($subscribers) use ($addRecipient) {
                    foreach ($subscribers as $subscriber) {
                        $addRecipient($subscriber->email, $subscriber->name);
                    }
                });
        }

        if (empty($recipients)) {
            return response()->json(['message' => 'No recipients found for the selected audience.'], 422);
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient['email'])->queue(
                (new PopularRoutesCampaignMail($recipient['name'], $routeItems))->afterCommit()
            );
        }

        return response()->json([
            'message' => 'Popular route emails queued successfully.',
            'queued_count' => count($recipients),
            'route_count' => count($routeItems),
        ]);
    }

    // Top Airlines CRUD

    public function getTopAirlines(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search_query');

            $query = TopAirline::query()
                ->when($search, function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'desc');

            $airlines = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $airlines->items(),
                'meta' => [
                    'current_page' => $airlines->currentPage(),
                    'last_page' => $airlines->lastPage(),
                    'per_page' => $airlines->perPage(),
                    'total' => $airlines->total(),
                    'from' => $airlines->firstItem(),
                    'to' => $airlines->lastItem(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch top airlines list', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve top airlines'], 500);
        }
    }

    public function getTopAirline($id)
    {
        $airline = TopAirline::find($id);
        if (!$airline) {
            return response()->json(['success' => false, 'message' => 'Top airline not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $airline]);
    }

    public function storeTopAirline(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('top_airlines', 'public');
        }

        $airline = TopAirline::create([
            'name' => $request->name,
            'type' => $request->type,
            'image' => $imagePath,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Top airline created successfully', 'data' => $airline], 201);
    }

    public function updateTopAirline(Request $request, $id)
    {
        $airline = TopAirline::find($id);
        if (!$airline) {
            return response()->json(['success' => false, 'message' => 'Top airline not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            if ($airline->image) {
                Storage::disk('public')->delete($airline->image);
            }
            $airline->image = $request->file('image')->store('top_airlines', 'public');
        }

        $airline->update([
            'name' => $request->name,
            'type' => $request->type,
            'is_active' => $request->is_active ?? true,
            'image' => $airline->image,
        ]);

        return response()->json(['success' => true, 'message' => 'Top airline updated successfully', 'data' => $airline]);
    }

    public function deleteTopAirline($id)
    {
        $airline = TopAirline::find($id);
        if (!$airline) {
            return response()->json(['success' => false, 'message' => 'Top airline not found'], 404);
        }

        if ($airline->image) {
            Storage::disk('public')->delete($airline->image);
        }

        $airline->delete();

        return response()->json(['success' => true, 'message' => 'Top airline deleted successfully']);
    }
}
