<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BlogCampaignMail;
use App\Models\Blog;
use App\Models\NewsletterSubscriber;
use App\Models\SeoMeta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        $query = Blog::with(['seo', 'author'])->latest();

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status')) {
            $query->where('is_published', $request->status === 'published' ? 1 : 0);
        } elseif (!auth()->check()) {
            $query->where('is_published', true);
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

    private function validationRules(?int $blogId = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:blogs,slug' . ($blogId ? ',' . $blogId : ''),
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
            'is_published' => 'nullable|boolean',
        ];
    }

    private function validationMessages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 255 characters.',
            'slug.required' => 'Slug is required.',
            'slug.max' => 'Slug must not exceed 255 characters.',
            'slug.unique' => 'This slug is already in use. Please choose another one.',
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and single hyphens.',
            'content.required' => 'Content is required.',
            'excerpt.max' => 'Excerpt must not exceed 500 characters.',
            'featured_image.image' => 'Featured image must be a valid image file.',
            'featured_image.mimes' => 'Featured image must be a JPEG, PNG, JPG, or WEBP file.',
            'featured_image.max' => 'Featured image must not exceed 5MB.',
            'focus_keyword.max' => 'Focus keyword must not exceed 255 characters.',
            'meta_title.max' => 'Meta title must not exceed 255 characters.',
            'meta_description.max' => 'Meta description must not exceed 500 characters.',
            'og_title.max' => 'OG title must not exceed 255 characters.',
            'og_image.image' => 'Open Graph image must be a valid image file.',
            'og_image.mimes' => 'Open Graph image must be a JPEG, PNG, JPG, or WEBP file.',
            'og_image.max' => 'Open Graph image must not exceed 5MB.',
            'canonical_url.url' => 'Canonical URL must be a valid URL.',
            'canonical_url.max' => 'Canonical URL must not exceed 500 characters.',
        ];
    }

    public function store(Request $request)
    {
        Log::info($request->all());
        $request->validate($this->validationRules(), $this->validationMessages());

        $blog = new Blog();
        $blog->title = $request->title;
        $blog->slug = $request->slug;
        $blog->content = $request->content;
        $blog->excerpt = $request->excerpt;
        $blog->is_published = $request->boolean('is_published', true);
        $blog->published_at = $blog->is_published ? Carbon::now() : null;
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
        $query = Blog::with('seo');
        $blog = is_numeric($id)
            ? $query->where('id', $id)->first()
            : $query->where('slug', $id)->where('is_published', true)->first();

        if (!$blog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }

        return response()->json(['data' => $blog]);
    }

    public function update(Request $request)
    {
        Log::info($request->all());
        $blog = Blog::find($request->id);
        if (!$blog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }

        $request->validate($this->validationRules($blog->id), $this->validationMessages());

        $blog->update($request->only(['title', 'slug', 'content', 'excerpt']));
        if ($request->has('is_published')) {
            $blog->is_published = $request->boolean('is_published');
            $blog->published_at = $blog->is_published ? ($blog->published_at ?: Carbon::now()) : null;
            $blog->save();
        }

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
        if (!$blog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }

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

    public function sendSelectedBlogsMail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blog_ids' => 'required|array|min:1',
            'blog_ids.*' => 'integer|exists:blogs,id',
            'audience' => ['required', Rule::in(['customers', 'subscribers', 'both'])],
        ]);

        $blogIds = collect($validated['blog_ids'])->unique()->values();
        $blogs = Blog::query()
            ->whereIn('id', $blogIds)
            ->get()
            ->sortBy(fn ($blog) => $blogIds->search($blog->id))
            ->values();

        if ($blogs->isEmpty()) {
            return response()->json(['message' => 'Please select at least one blog.'], 422);
        }

        $frontend = rtrim(config('app.frontend_url'), '/');
        $blogItems = $blogs->map(function (Blog $blog) use ($frontend) {
            return [
                'title' => $blog->title,
                'slug' => $blog->slug,
                'excerpt' => $blog->excerpt,
                'published_at' => optional($blog->published_at)->format('d M Y'),
                'featured_image_url' => $blog->featured_image ? asset('storage/' . $blog->featured_image) : null,
                'url' => $frontend . '/blog/' . $blog->id . '/' . $blog->slug,
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
                (new BlogCampaignMail($recipient['name'], $blogItems))->afterCommit()
            );
        }

        return response()->json([
            'message' => 'Blog emails queued successfully.',
            'queued_count' => count($recipients),
            'blog_count' => count($blogItems),
        ]);
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
