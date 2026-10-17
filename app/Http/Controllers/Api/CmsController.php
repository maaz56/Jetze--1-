<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PopularRoutesCampaignMail;
use App\Models\NewsletterSubscriber;
use App\Models\PopularRoute;
use App\Models\SeoMeta;
use App\Models\TopAirline;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\PopularRoutesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsController extends Controller
{
    private function mapPopularRoute(PopularRoute $route): array
    {
        $data = $route->toArray();
        $departurePlusDays = max(1, (int) ($route->departure_plus_days ?? 1));
        $departureDate = Carbon::today()->addDays($departurePlusDays);
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

    public function getPublicPopularRoutes(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'all');
            $limit = min(50, max(1, (int) $request->input('limit', 12)));
            $currency = strtoupper($request->input('currency') ?: config('popular_routes.base_currency', 'AED'));
            $version = Cache::get('popular_routes:version', 1);
            $locale = app()->getLocale();
            $cacheKey = "popular_routes:list:v{$version}:{$type}:{$limit}:{$locale}";

            $fetchCallback = function () use ($type, $limit) {
                $query = PopularRoute::query()
                    ->with(['airline', 'fromAirportModel', 'toAirportModel'])
                    ->active()
                    ->ordered();

                if ($type && $type !== 'all') {
                    $query->where('type', $type);
                }

                return $query->limit($limit)->get()->map(fn ($r) => $this->formatPublicRouteBase($r))->all();
            };

            $baseRoutes = $request->boolean('force')
                ? $fetchCallback()
                : Cache::remember($cacheKey, 600, $fetchCallback);

            if (empty($baseRoutes)) {
                Cache::forget($cacheKey);
            }

            $data = array_map(fn ($r) => $this->applyCurrencyToPublicRoute($r, $currency), $baseRoutes);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200, [
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch public popular routes', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Unable to load popular routes',
            ], 500);
        }
    }

    public function getPublicPopularRoute(Request $request, $slug): JsonResponse
    {
        try {
            $currency = strtoupper($request->input('currency') ?: config('popular_routes.base_currency', 'AED'));

            $route = PopularRoute::query()
                ->with(['airline', 'seo', 'fromAirportModel', 'toAirportModel'])
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug);
                    if (is_numeric($slug)) {
                        $q->orWhere('id', (int) $slug);
                    }
                })
                ->first();

            if (!$route) {
                return response()->json([
                    'success' => false,
                    'message' => 'Popular route not found',
                ], 404);
            }

            $base = $this->formatPublicRouteBase($route);
            $data = $this->applyCurrencyToPublicRoute($base, $currency);

            $data['blogs'] = $route->blogs ?: [];
            $data['faqs'] = $route->faqs ?: [];
            $data['seo'] = $route->seo ? [
                'meta_title' => $route->seo->meta_title,
                'meta_description' => $route->seo->meta_description,
                'meta_keywords' => $route->seo->meta_keywords,
                'og_title' => $route->seo->og_title,
                'og_description' => $route->seo->og_description,
                'og_image_url' => $route->seo->og_image ? asset('storage/' . $route->seo->og_image) : ($data['image']['url'] ?? null),
                'canonical_url' => $route->seo->canonical_url,
                'no_index' => (bool) $route->seo->no_index,
                'no_follow' => (bool) $route->seo->no_follow,
            ] : null;

            // Related routes: same origin or destination, up to 6
            $related = PopularRoute::query()
                ->with(['airline', 'fromAirportModel', 'toAirportModel'])
                ->active()
                ->where('id', '!=', $route->id)
                ->where(function ($q) use ($route) {
                    $q->where('from_airport', $route->from_airport)
                      ->orWhere('to_airport', $route->to_airport);
                })
                ->ordered()
                ->limit(6)
                ->get()
                ->map(fn ($r) => $this->applyCurrencyToPublicRoute($this->formatPublicRouteBase($r), $currency))
                ->all();

            $data['related_routes'] = $related;

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200, [
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch popular route details by slug', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve route details',
            ], 500);
        }
    }

    public function refreshPopularRoutePrice(Request $request, $id, PopularRoutesService $service): JsonResponse
    {
        $route = PopularRoute::find($id);
        if (!$route) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }

        try {
            $result = $service->refreshDynamicPrice($route);
            return response()->json([
                'success' => true,
                'message' => 'Price refresh completed successfully',
                'data' => $result,
                'route' => $this->mapPopularRoute($route->fresh(['airline', 'seo'])),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh price: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function formatPublicRouteBase(PopularRoute $route): array
    {
        $effectivePrice = $route->effective_price;
        $departurePlusDays = max(1, (int) ($route->departure_plus_days ?? 1));
        $meta = $route->dynamic_price_meta ?? [];

        $departureDate = !empty($meta['departure_date']) && Carbon::parse($meta['departure_date'])->isFuture()
            ? Carbon::parse($meta['departure_date'])->toDateString()
            : Carbon::today()->addDays($departurePlusDays)->toDateString();

        $returnDate = null;
        if ($route->journey_type === 'round') {
            $stayDays = (int) ($route->stay_duration_days ?: 7);
            $returnDate = !empty($meta['return_date']) && Carbon::parse($meta['return_date'])->isFuture()
                ? Carbon::parse($meta['return_date'])->toDateString()
                : Carbon::parse($departureDate)->addDays($stayDays)->toDateString();
        }

        $imageUrl = $route->image 
            ? (filter_var($route->image, FILTER_VALIDATE_URL) ? $route->image : asset('storage/' . $route->image)) 
            : null;
        $airline = $route->airline;
        $baseCurrency = config('popular_routes.base_currency', 'AED');

        return [
            'id' => $route->id,
            'slug' => $route->computed_slug,
            'from' => [
                'iata' => $route->from_airport,
                'city' => $route->from_city,
                'country' => $route->fromAirportModel?->iata_country_code ?? '',
                'airport' => $route->fromAirportModel?->name ?? '',
            ],
            'to' => [
                'iata' => $route->to_airport,
                'city' => $route->to_city,
                'country' => $route->toAirportModel?->iata_country_code ?? '',
                'airport' => $route->toAirportModel?->name ?? '',
            ],
            'destination_name' => app()->getLocale() === 'ar' ? ($route->destination_name_ar ?: $route->destination_name_en) : ($route->destination_name_en ?: $route->to_city),
            'destination_name_en' => $route->destination_name_en,
            'destination_name_ar' => $route->destination_name_ar,
            'type' => $route->type ?: 'international',
            'journey_type' => $route->journey_type ?: 'round',
            'travel_class' => $route->travel_class ?: 'economy',
            'image' => [
                'url' => $imageUrl,
                'thumb_url' => $imageUrl,
                'alt' => "Flights from {$route->from_city} to {$route->to_city}",
            ],
            'airline' => $airline ? [
                'name' => $airline->name,
                'iata' => $airline->iata_code,
                'logo_url' => $airline->logo ? asset('storage/' . $airline->logo) : null,
            ] : null,
            'price_base' => $effectivePrice ? [
                'amount' => (float) $effectivePrice['amount'],
                'currency' => $effectivePrice['currency'] ?? $baseCurrency,
                'source' => $effectivePrice['source'],
                'updated_at' => $effectivePrice['updated_at'],
                'is_stale' => $effectivePrice['is_stale'],
            ] : null,
            'search' => [
                'origin' => $route->from_airport,
                'destination' => $route->to_airport,
                'departure_date' => $departureDate,
                'return_date' => $returnDate,
                'flight_type' => $route->journey_type === 'round' ? 'return' : 'one-way',
                'cabin_class' => match (strtolower((string) $route->travel_class)) {
                    'business' => 'C',
                    'first' => 'F',
                    'premium_economy', 'premium-economy' => 'S',
                    default => 'Y',
                },
                'airline' => 'AT',
            ],
            'meta' => [
                'stops' => $meta['stops'] ?? 0,
                'duration_minutes' => $meta['duration_minutes'] ?? null,
            ],
        ];
    }

    protected function applyCurrencyToPublicRoute(array $base, string $targetCurrency): array
    {
        $basePrice = $base['price_base'] ?? null;
        if (!$basePrice) {
            $base['price'] = null;
            unset($base['price_base']);
            return $base;
        }

        $sourceCurrency = $basePrice['currency'] ?? config('popular_routes.base_currency', 'AED');
        $sourceAmount = $basePrice['amount'];

        if (strtoupper($sourceCurrency) === strtoupper($targetCurrency)) {
            $base['price'] = [
                'amount' => $sourceAmount,
                'currency' => $sourceCurrency,
                'base_amount' => $sourceAmount,
                'base_currency' => $sourceCurrency,
                'source' => $basePrice['source'],
                'updated_at' => $basePrice['updated_at'],
                'is_stale' => $basePrice['is_stale'],
            ];
        } else {
            try {
                $conversionService = app(CurrencyConversionService::class);
                $converted = $conversionService->convertMoney($sourceAmount, $sourceCurrency, $targetCurrency);
                $base['price'] = [
                    'amount' => round((float) $converted['amount'], 2),
                    'currency' => $targetCurrency,
                    'base_amount' => $sourceAmount,
                    'base_currency' => $sourceCurrency,
                    'source' => $basePrice['source'],
                    'updated_at' => $basePrice['updated_at'],
                    'is_stale' => $basePrice['is_stale'],
                ];
            } catch (\Throwable $e) {
                $base['price'] = [
                    'amount' => $sourceAmount,
                    'currency' => $sourceCurrency,
                    'base_amount' => $sourceAmount,
                    'base_currency' => $sourceCurrency,
                    'source' => $basePrice['source'],
                    'updated_at' => $basePrice['updated_at'],
                    'is_stale' => $basePrice['is_stale'],
                ];
            }
        }

        unset($base['price_base']);
        return $base;
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
                            ->orWhere('slug', 'like', $search)
                            ->orWhereHas('airline', function ($sub) use ($search) {
                                $sub->where('name', 'like', $search)
                                    ->orWhere('iata_code', 'like', $search);
                            });
                    });
                })
                ->ordered();

            $routes = $query->paginate($perPage);

            $mappedRoutes = $routes->getCollection()->map(function ($route) {
                return $this->mapPopularRoute($route);
            });

            $routes->setCollection($mappedRoutes);

            return response()->json([
                'success' => true,
                'data' => $routes,
            ]);
        } catch (\Exception $e) {
            Log::error('Popular Routes Fetch Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPopularRoute($id)
    {
        $route = PopularRoute::with(['airline', 'seo'])->find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Popular Route not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapPopularRoute($route),
        ]);
    }

    private function popularRouteValidationRules(bool $isUpdate = false): array
    {
        return [
            'fromAirport' => 'required|string|max:10',
            'toAirport' => 'required|string|max:10',
            'type' => 'required|in:domestic,international',
            'image' => $isUpdate ? 'nullable|image|mimes:jpeg,png,jpg,webp|max:4048' : 'required|image|mimes:jpeg,png,jpg,webp|max:4048',
            'airline' => 'nullable|exists:airlines,id',
            'journeyType' => 'required|string',
            'travelClass' => 'required|string',
            'departurePlusDays' => 'required|integer|min:1',
            'stayDurationDays' => 'nullable|integer',
            'priceType' => 'required|in:dynamic,static',
            'dynamicRefreshHours' => 'required_if:priceType,dynamic|nullable|integer',
            'staticPrice' => 'required_if:priceType,static|nullable|numeric',
            'destinationNameEn' => 'required|string|max:255',
            'destinationNameAr' => 'required|string|max:255',
            'blogs' => 'nullable',
            'faqs' => 'nullable',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:255',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:255',
            'no_index' => 'nullable|boolean',
            'no_follow' => 'nullable|boolean',
            'slug' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }

    private function syncPopularRouteSeo(PopularRoute $route, Request $request): void
    {
        $seo = $route->seo ?: new SeoMeta([
            'seoable_type' => PopularRoute::class,
            'seoable_id' => $route->id,
        ]);

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
        $validator = Validator::make($request->all(), $this->popularRouteValidationRules(false));

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('popular_routes', 'public');
        }

        $route = PopularRoute::create([
            'from_airport' => $request->fromAirport,
            'to_airport' => $request->toAirport,
            'type' => $request->type,
            'image' => $imagePath,
            'airline_id' => $request->airline,
            'journey_type' => $request->journeyType,
            'travel_class' => $request->travelClass,
            'departure_plus_days' => max(1, (int) ($request->departurePlusDays ?? 1)),
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

        $updateData = [
            'from_airport' => $request->fromAirport,
            'to_airport' => $request->toAirport,
            'type' => $request->type,
            'airline_id' => $request->airline,
            'journey_type' => $request->journeyType,
            'travel_class' => $request->travelClass,
            'departure_plus_days' => max(1, (int) ($request->departurePlusDays ?? 1)),
            'stay_duration_days' => $request->journeyType === 'round' ? $request->stayDurationDays : null,
            'price_type' => $request->priceType,
            'destination_name_en' => $request->destinationNameEn,
            'destination_name_ar' => $request->destinationNameAr,
            'image' => $route->image,
            'blogs' => $request->has('blogs') && !empty($request->blogs) ? (is_array($request->blogs) ? $request->blogs : json_decode($request->blogs, true)) : null,
            'faqs' => $request->has('faqs') && !empty($request->faqs) ? (is_array($request->faqs) ? $request->faqs : json_decode($request->faqs, true)) : null,
        ];

        if ($request->filled('slug')) {
            $updateData['slug'] = Str::slug($request->slug);
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('is_featured')) {
            $updateData['is_featured'] = filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('sort_order')) {
            $updateData['sort_order'] = (int) $request->sort_order;
        }

        if ($request->priceType === 'static') {
            $updateData['static_price'] = $request->staticPrice;
            $updateData['dynamic_refresh_hours'] = null;
        } else {
            $updateData['dynamic_refresh_hours'] = $request->dynamicRefreshHours;
            if ($route->price_type === 'static') {
                $updateData['static_price'] = null;
            }
        }

        $route->update($updateData);
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
                    'message' => 'Popular Route not found',
                ], 404);
            }

            if ($route->image) {
                Storage::disk('public')->delete($route->image);
            }

            if ($route->seo) {
                $route->seo->delete();
            }

            $route->delete();

            return response()->json([
                'success' => true,
                'message' => 'Popular Route deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Popular Route Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendSelectedPopularRoutesMail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_ids' => 'required|array|min:1',
            'route_ids.*' => 'integer|exists:popular_routes,id',
            'subject' => 'nullable|string|max:255',
            'heading' => 'nullable|string|max:255',
            'intro_text' => 'nullable|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'email',
            'target_audience' => 'nullable|in:custom,users,subscribers,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $routes = PopularRoute::with(['airline', 'fromAirportModel', 'toAirportModel'])
            ->whereIn('id', $request->input('route_ids'))
            ->get();

        if ($routes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid popular routes found for the selected IDs.',
            ], 422);
        }

        $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');
        $routeItems = $routes->map(function (PopularRoute $route) use ($frontend) {
            $departurePlusDays = max(1, (int) ($route->departure_plus_days ?? 1));
            $departureDate = Carbon::today()->addDays($departurePlusDays);
            $returnDate = $route->journey_type === 'round' && $route->stay_duration_days !== null
                ? $departureDate->copy()->addDays((int) $route->stay_duration_days)->toDateString()
                : null;

            $slug = $route->computed_slug;
            $url = $slug ? "{$frontend}/flights/{$slug}" : "{$frontend}/popular-routes/{$route->id}";

            $effectivePrice = $route->effective_price;
            $priceText = $effectivePrice
                ? ($effectivePrice['currency'] . ' ' . number_format((float) $effectivePrice['amount'], 2))
                : 'Check latest fares';

            return [
                'id' => $route->id,
                'title' => "{$route->from_city} to {$route->to_city}",
                'from_city' => $route->from_city,
                'to_city' => $route->to_city,
                'from_airport' => $route->from_airport,
                'to_airport' => $route->to_airport,
                'airline' => $route->airline?->name,
                'journey_type' => $route->journey_type,
                'travel_class' => $route->travel_class,
                'departure_date' => $departureDate->toDateString(),
                'return_date' => $returnDate,
                'price' => $priceText,
                'url' => $url,
                'image_url' => $route->image ? (filter_var($route->image, FILTER_VALIDATE_URL) ? $route->image : asset('storage/' . $route->image)) : null,
            ];
        })->all();

        $subject = $request->input('subject') ?: 'Exclusive Flight Deals Hand-Picked for You';
        $heading = $request->input('heading') ?: 'Explore Our Most Popular Flight Routes';
        $introText = $request->input('intro_text') ?: 'We have gathered our best flight deals of the week. Book now and enjoy incredible savings on your next trip.';

        $audience = $request->input('target_audience', 'custom');
        $recipients = collect();

        $addRecipient = function (?string $email, ?string $name = null) use (&$recipients) {
            $email = trim(strtolower((string) $email));
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            if (!$recipients->has($email)) {
                $recipients->put($email, [
                    'email' => $email,
                    'name' => $name ?: explode('@', $email)[0],
                ]);
            }
        };

        if (in_array($audience, ['custom', 'all'], true)) {
            foreach ((array) $request->input('recipients', []) as $customEmail) {
                $addRecipient($customEmail);
            }
        }

        if (in_array($audience, ['users', 'all'], true)) {
            User::query()
                ->whereNotNull('email')
                ->chunkById(500, function ($users) use ($addRecipient) {
                    foreach ($users as $user) {
                        $addRecipient($user->email, $user->name);
                    }
                });
        }

        if (in_array($audience, ['subscribers', 'all'], true)) {
            NewsletterSubscriber::query()
                ->whereNotNull('email')
                ->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', true);
                })
                ->chunkById(500, function ($subscribers) use ($addRecipient) {
                    foreach ($subscribers as $sub) {
                        $addRecipient($sub->email, $sub->name ?? null);
                    }
                });
        }

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid recipient email addresses found.',
            ], 422);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'], $recipient['name'])
                    ->queue(new PopularRoutesCampaignMail($routeItems, $subject, $heading, $introText, $recipient['name']));
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('Failed to queue popular routes campaign mail', [
                    'email' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Campaign queued for {$sentCount} recipient(s)." . ($failedCount ? " Failed: {$failedCount}." : ''),
            'queued_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);
    }

    public function getTopAirlines(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search_query');

            $query = TopAirline::query()
                ->with(['airline', 'seo'])
                ->when($search, function ($q) use ($search) {
                    $search = '%' . trim($search) . '%';
                    $q->where(function ($q) use ($search) {
                        $q->where('description_en', 'like', $search)
                            ->orWhere('description_ar', 'like', $search)
                            ->orWhereHas('airline', function ($sub) use ($search) {
                                $sub->where('name', 'like', $search)
                                    ->orWhere('iata_code', 'like', $search);
                            });
                    });
                })
                ->latest();

            $airlines = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $airlines,
            ]);
        } catch (\Exception $e) {
            Log::error('Top Airlines Fetch Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTopAirline($id)
    {
        $airline = TopAirline::with(['airline', 'seo'])->find($id);

        if (!$airline) {
            return response()->json([
                'success' => false,
                'message' => 'Top Airline not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $airline,
        ]);
    }

    public function storeTopAirline(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'airline_id' => 'required|exists:airlines,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4048',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'faqs' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = $request->file('image')->store('top_airlines', 'public');

        $topAirline = TopAirline::create([
            'airline_id' => $request->airline_id,
            'image' => $imagePath,
            'description_en' => $request->description_en,
            'description_ar' => $request->description_ar,
            'faqs' => $request->has('faqs') && !empty($request->faqs) ? json_decode($request->faqs, true) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Top Airline created successfully',
            'data' => $topAirline->fresh(['airline', 'seo'])
        ], 201);
    }

    public function updateTopAirline(Request $request, $id)
    {
        $airline = TopAirline::find($id);

        if (!$airline) {
            return response()->json([
                'success' => false,
                'message' => 'Top Airline not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'airline_id' => 'required|exists:airlines,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4048',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'faqs' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
            if ($airline->image) {
                Storage::disk('public')->delete($airline->image);
            }
            $airline->image = $request->file('image')->store('top_airlines', 'public');
        }

        $airline->update([
            'airline_id' => $request->airline_id,
            'description_en' => $request->description_en,
            'description_ar' => $request->description_ar,
            'faqs' => $request->has('faqs') && !empty($request->faqs) ? json_decode($request->faqs, true) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Top Airline updated successfully',
            'data' => $airline->fresh(['airline', 'seo'])
        ]);
    }

    public function deleteTopAirline($id)
    {
        try {
            $airline = TopAirline::find($id);

            if (!$airline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Top Airline not found',
                ], 404);
            }

            if ($airline->image) {
                Storage::disk('public')->delete($airline->image);
            }

            $airline->delete();

            return response()->json([
                'success' => true,
                'message' => 'Top Airline deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Top Airline Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
