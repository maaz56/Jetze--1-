<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\SeoMeta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogController extends Controller
{

    public function slugs(): JsonResponse
    {
        Log::info('slug function is running');
        $slugs = Blog::query()
            ->where('is_published', true)   // important for SEO
            ->orderBy('published_at', 'desc')
            ->pluck('slug');                 // ONLY slugs

        return response()->json($slugs);
    }

    // public function show(string $slug): JsonResponse
    // {
    //     $blog = Blog::query()
    //         ->where('slug', $slug)
    //         ->where('is_published', true) // IMPORTANT for SEO
    //         ->firstOrFail();

    //     return response()->json([
    //         'title' => $blog->title,
    //         'slug' => $blog->slug,
    //         'excerpt' => $blog->excerpt,
    //         'content' => $blog->content, // HTML
    //         'published_at' => optional($blog->published_at),
    //     ]);
    // }
    public function index(Request $request)
    {
        Log::info('blog fetched with this request', $request->all());
        $query = Blog::with('seo')->latest();

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status')) {
            $query->where('is_published', $request->status === 'published' ? 1 : 0);
        }

        $blogs = $query->paginate(15);

        return response()->json([
            'data' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
                'from' => $blogs->firstItem(),
                'to' => $blogs->lastItem(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        Log::info($request->all());
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:blogs,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'focus_keyword' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'boolean',
            'no_follow' => 'boolean',
        ]);

        $blog = new Blog();
        $blog->title = $request->title;
        $blog->slug = $request->slug;
        $blog->content = $request->content;
        $blog->excerpt = $request->excerpt;
        $blog->is_published = true;
        $blog->published_at = Carbon::now();
        $blog->user_id = auth()->id(); // or null if admin-only

        // Featured Image
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('blogs/featured', 'public');
            $blog->featured_image = $path;
        }

        $blog->save();

        // SEO Meta (polymorphic)
        $seo = new SeoMeta();
        $seo->seoable_id = $blog->id;
        $seo->seoable_type = Blog::class;
        $seo->meta_title = $request->meta_title;
        $seo->meta_description = $request->meta_description;
        $seo->meta_keywords = $request->focus_keyword;
        $seo->og_title = $request->og_title;
        $seo->canonical_url = $request->canonical_url;
        $seo->no_index = $request->boolean('no_index', false);
        $seo->no_follow = $request->boolean('no_follow', false);
        $seo->save();

        // Custom OG image (if uploaded separately)
        if ($request->hasFile('og_image')) {
            $ogPath = $request->file('og_image')->store('blogs/og', 'public');
            $seo->og_image = $ogPath;
            $seo->save();
        } else {
            $seo->og_image = $blog->featured_image;
        }

        return response()->json([
            'message' => 'Blog created successfully',
            'data' => $blog->load('seo')
        ], 201);
    }

    public function show($id)
    {
        $blog = Blog::find($id);
        $blog->load('seo');
        return response()->json(['data' => $blog]);
    }

    public function update(Request $request)
    {
        Log::info($request->all());
        $blog = Blog::find($request->id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:blogs,slug,' . $blog->id,
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'focus_keyword' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'boolean',
            'no_follow' => 'boolean',
        ]);

        $blog->update($request->only(['title', 'slug', 'content', 'excerpt']));

        // Handle featured image update/replace
        if ($request->hasFile('featured_image')) {
            if ($blog->featured_image) {
                Storage::disk('public')->delete($blog->featured_image);
            }
            $path = $request->file('featured_image')->store('blogs/featured', 'public');
            $blog->featured_image = $path;
            $blog->save();
        }

        // Update SEO
        $seo = $blog->seo ?? new SeoMeta([
            'seoable_id' => $blog->id,
            'seoable_type' => Blog::class,
        ]);

        $seo->fill($request->only([
            'meta_title',
            'meta_description',
            'og_title',
            'canonical_url',
            'no_index',
            'no_follow'
        ]));
        if ($request->has('focus_keyword')) {
            $seo->meta_keywords = $request->focus_keyword;
        }

        // Handle custom OG image
        if ($request->hasFile('og_image')) {
            if ($seo->og_image) {
                Storage::disk('public')->delete($seo->og_image);
            }
            $ogPath = $request->file('og_image')->store('blogs/og', 'public');
            $seo->og_image = $ogPath;
        }

        $seo->save();

        return response()->json([
            'message' => 'Blog updated successfully',
            'data' => $blog->load('seo')
        ]);
    }

    public function delete($id)
    {
        $blog = Blog::find($id);
        if ($blog->featured_image) {
            Storage::disk('public')->delete($blog->featured_image);
        }
        if ($blog->seo && $blog->seo->og_image) {
            Storage::disk('public')->delete($blog->seo->og_image);
        }

        $blog->seo()->delete();
        $blog->delete();

        return response()->json(['message' => 'Blog deleted successfully']);
    }

    public function publish(Request $request, Blog $blog)
    {
        $validated = $request->validate([
            'is_published' => 'required|boolean'
        ]);

        $blog->is_published = $validated['is_published'];
        $blog->published_at = $validated['is_published'] ? now() : null;
        $blog->save();

        return response()->json(['message' => 'Publish status updated']);
    }
}