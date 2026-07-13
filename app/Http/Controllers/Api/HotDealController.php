<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\HotDealsCampaignMail;
use App\Http\Requests\HotDeal\HotDealRequest;
use App\Models\HotDeal;
use App\Models\NewsletterSubscriber;
use App\Models\SeoMeta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HotDealController extends Controller
{
    private array $seoFields = [
        'focus_keyword',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'canonical_url',
        'no_index',
        'no_follow',
    ];

    private function resolveSeoImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $image)) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }

    private function mapHotDeal(HotDeal $hotDeal): array
    {
        $data = $hotDeal->toArray();
        if (!empty($data['seo']['og_image'])) {
            $data['seo']['og_image_url'] = $this->resolveSeoImageUrl($data['seo']['og_image']);
        }

        return $data;
    }

    private function syncHotDealSeo(HotDeal $hotDeal, HotDealRequest $request): void
    {
        $seo = $hotDeal->seo ?? new SeoMeta();
        if (!$seo->exists) {
            $seo->seoable_id = $hotDeal->id;
            $seo->seoable_type = HotDeal::class;
        }

        $seo->fill([
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'meta_keywords' => $request->input('focus_keyword'),
            'og_title' => $request->input('og_title'),
            'og_description' => $request->input('og_description'),
            'og_image' => $hotDeal->image_url,
            'canonical_url' => $request->input('canonical_url'),
            'no_index' => $request->boolean('no_index', false),
            'no_follow' => $request->boolean('no_follow', false),
        ]);

        $seo->save();
    }

    /**
     * Display a listing of hot deals (Public)
     */
    public function index(Request $request)
    {
        $query = HotDeal::with('seo')->active()->ordered();
        
        // Optional: filter by search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('from_airport', 'like', "%{$search}%")
                  ->orWhere('to_airport', 'like', "%{$search}%");
            });
        }
        
        $hotDeals = $query->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $hotDeals,
            'message' => 'Hot deals retrieved successfully'
        ]);
    }
    
    /**
     * Display a listing of all hot deals for admin panel
     */
    public function adminIndex(Request $request)
    {
        $query = HotDeal::with('seo')->ordered();
        
        if ($request->has('search_query')) {
            $search = $request->search_query;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('from_airport', 'like', "%{$search}%")
                  ->orWhere('to_airport', 'like', "%{$search}%");
            });
        }
        
        $hotDeals = $query->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $hotDeals,
            'message' => 'Hot deals retrieved successfully'
        ]);
    }

    /**
     * Store a newly created hot deal
     */
    public function store(HotDealRequest $request)
    {
        $data = collect($request->validated())->except($this->seoFields)->all();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('hot-deals', 'public');
            $data['image_url'] = Storage::url($imagePath);
            unset($data['image']);
        }
        
        $hotDeal = HotDeal::create($data);
        $this->syncHotDealSeo($hotDeal, $request);
        
        return response()->json([
            'success' => true,
            'data' => $this->mapHotDeal($hotDeal->fresh('seo')),
            'message' => 'Hot deal created successfully'
        ], 201);
    }

    /**
     * Display a specific hot deal
     */
    public function show(HotDeal $hotDeal)
    {
        $hotDeal->load('seo');

        return response()->json([
            'success' => true,
            'data' => $this->mapHotDeal($hotDeal),
            'message' => 'Hot deal retrieved successfully'
        ]);
    }

    /**
     * Update a hot deal
     */
    public function update(HotDealRequest $request, HotDeal $hotDeal)
    {
        $data = collect($request->validated())->except($this->seoFields)->all();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($hotDeal->image_url) {
                $oldPath = str_replace('/storage/', '', $hotDeal->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            
            $imagePath = $request->file('image')->store('hot-deals', 'public');
            $data['image_url'] = Storage::url($imagePath);
            unset($data['image']);
        }
        
        $hotDeal->update($data);
        $this->syncHotDealSeo($hotDeal->fresh('seo'), $request);
        
        return response()->json([
            'success' => true,
            'data' => $this->mapHotDeal($hotDeal->fresh('seo')),
            'message' => 'Hot deal updated successfully'
        ]);
    }

    /**
     * Delete a hot deal
     */
    public function destroy(HotDeal $hotDeal)
    {
        // Delete associated image
        if ($hotDeal->image_url) {
            $imagePath = str_replace('/storage/', '', $hotDeal->image_url);
            Storage::disk('public')->delete($imagePath);
        }
        
        $hotDeal->seo()->delete();
        $hotDeal->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Hot deal deleted successfully'
        ]);
    }
    
    /**
     * Update display order for multiple hot deals
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:hot_deals,id',
            'orders.*.display_order' => 'required|integer|min:0',
        ]);
        
        foreach ($request->orders as $order) {
            HotDeal::where('id', $order['id'])->update(['display_order' => $order['display_order']]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Display order updated successfully'
        ]);
    }

    public function sendSelectedHotDealsMail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deal_ids' => 'required|array|min:1',
            'deal_ids.*' => 'integer|exists:hot_deals,id',
            'audience' => ['required', Rule::in(['customers', 'subscribers', 'both'])],
        ]);

        $dealIds = collect($validated['deal_ids'])->unique()->values();
        $deals = HotDeal::query()
            ->whereIn('id', $dealIds)
            ->get()
            ->sortBy(fn ($deal) => $dealIds->search($deal->id))
            ->values();

        if ($deals->isEmpty()) {
            return response()->json(['message' => 'Please select at least one hot deal.'], 422);
        }

        $frontend = rtrim(config('app.frontend_url'), '/');
        $dealItems = $deals->map(function (HotDeal $deal) use ($frontend) {
            $imageUrl = $deal->image_url;
            if ($imageUrl && !preg_match('/^https?:\/\//i', $imageUrl)) {
                $imageUrl = asset(ltrim($imageUrl, '/'));
            }

            $departureDate = now()->addDay()->toDateString();

            return [
                'title' => $deal->title,
                'tag' => $deal->tag,
                'route' => $deal->route,
                'original_price' => $deal->original_price ? number_format((float) $deal->original_price) : null,
                'discounted_price' => $deal->discounted_price ? number_format((float) $deal->discounted_price) : null,
                'discount_percentage' => $deal->discount_percentage,
                'image_url' => $imageUrl,
                'url' => $frontend . '/flight/search?origin=' . urlencode($deal->from_airport)
                    . '&destination=' . urlencode($deal->to_airport)
                    . '&departure_date=' . $departureDate,
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
                (new HotDealsCampaignMail($recipient['name'], $dealItems))->afterCommit()
            );
        }

        return response()->json([
            'message' => 'Hot deal emails queued successfully.',
            'queued_count' => count($recipients),
            'deal_count' => count($dealItems),
        ]);
    }
}
