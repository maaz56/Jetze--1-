<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SeoSettingController extends Controller
{
    private const PAGES = ['about-us', 'contact-us'];

    public function show(string $page): JsonResponse
    {
        abort_unless(in_array($page, self::PAGES, true), 404);
        return response()->json(['data' => $this->setting($page)]);
    }

    public function update(Request $request, string $page): JsonResponse
    {
        abort_unless(in_array($page, self::PAGES, true), 404);
        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255', 'meta_description' => 'nullable|string|max:1000',
            'meta_keywords' => 'nullable|string|max:1000', 'h1' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:500',
            'robots_meta' => ['required', Rule::in(['index, follow', 'index, nofollow', 'noindex, follow', 'noindex, nofollow'])],
            'og_title' => 'nullable|string|max:255', 'og_description' => 'nullable|string|max:1000',
            'og_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', 'remove_og_image' => 'nullable|boolean',
            'twitter_title' => 'nullable|string|max:255', 'twitter_description' => 'nullable|string|max:1000',
            'twitter_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', 'remove_twitter_image' => 'nullable|boolean',
            'schema_json' => 'nullable|json', 'breadcrumb_title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255', 'is_active' => 'required|boolean',
        ]);
        $setting = $this->setting($page);

        foreach (['og_image', 'twitter_image'] as $field) {
            if ($request->boolean('remove_' . $field) && $setting->{$field}) {
                Storage::disk('public')->delete($setting->{$field});
                $setting->{$field} = null;
            }
            if ($request->hasFile($field)) {
                if ($setting->{$field}) Storage::disk('public')->delete($setting->{$field});
                $setting->{$field} = $request->file($field)->store("seo/{$page}", 'public');
            }
        }

        unset($validated['og_image'], $validated['twitter_image'], $validated['remove_og_image'], $validated['remove_twitter_image']);
        $validated['schema_json'] = filled($validated['schema_json'] ?? null) ? json_decode($validated['schema_json'], true) : null;
        $setting->fill($validated)->save();
        return response()->json(['message' => 'SEO settings saved successfully.', 'data' => $setting->fresh()]);
    }

    private function setting(string $page): SeoSetting
    {
        return SeoSetting::firstOrCreate(
            ['page' => $page, 'seoable_id' => null, 'seoable_type' => null],
            $page === 'about-us'
                ? ['meta_title' => 'About Us | Jetze', 'meta_description' => 'Learn how Jetze makes flight booking fast, transparent, and affordable.', 'h1' => 'About Jetze', 'robots_meta' => 'index, follow', 'is_active' => true]
                : ['meta_title' => 'Contact Us | Jetze', 'meta_description' => 'Contact Jetze for bookings, travel inquiries, and 24/7 support.', 'h1' => 'Contact Jetze', 'robots_meta' => 'index, follow', 'is_active' => true],
        );
    }
}
