<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Blog;
use App\Models\PopularRoute;
use App\Models\SeoSetting;
use App\Models\TopAirline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BlogPageController extends Controller
{
    public function about(): View
    {
        return view('pages.about-us', array_merge($this->footerData(), [
            'seo' => SeoSetting::where('page', 'about-us')->where('is_active', true)->first(),
        ]));
    }

    public function contact(): View
    {
        return view('pages.contact-us', array_merge($this->footerData(), [
            'seo' => SeoSetting::where('page', 'contact-us')->where('is_active', true)->first(),
        ]));
    }

    public function index(): View
    {
        $blogs = Blog::query()
            ->with(['seo', 'author'])
            ->where('is_published', true)
            ->latest('published_at')
            ->paginate(12);

        return view('blogs.index', array_merge([
            'blogs' => $blogs,
            'metaTitle' => 'Blogs | Jetze',
            'metaDescription' => 'Read the latest travel guides, flight booking tips, and updates from Jetze.',
            'canonicalUrl' => route('blog.index'),
        ], $this->footerData()));
    }

    public function show(Blog $blog): View
    {
        abort_unless($blog->is_published, 404);

        $blog->load(['seo', 'author']);
        $seo = $blog->seo;
        $relatedBlogs = Blog::query()
            ->with('author')
            ->where('is_published', true)
            ->whereKeyNot($blog->getKey())
            ->latest('published_at')
            ->limit(8)
            ->get();

        return view('blogs.show', array_merge([
            'blog' => $blog,
            'relatedBlogs' => $relatedBlogs,
            'seo' => $seo,
            'metaTitle' => $seo->meta_title ?? $blog->title,
            'metaDescription' => $seo->meta_description ?? $blog->excerpt,
            'metaKeywords' => $seo->meta_keywords ?? null,
            'ogTitle' => $seo->og_title ?? ($seo->meta_title ?? $blog->title),
            'ogDescription' => $seo->og_description ?? ($seo->meta_description ?? $blog->excerpt),
            'ogImage' => $this->imageUrl($seo->og_image ?? $blog->featured_image),
            'canonicalUrl' => $seo->canonical_url ?: route('blog.show', $blog->slug),
            'robots' => $this->robots($seo),
        ], $this->footerData()));
    }

    public function showLegacy(int $id, string $slug): RedirectResponse
    {
        $blog = Blog::query()
            ->whereKey($id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return redirect()->route('blog.show', $blog->slug, 301);
    }

    private function imageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function footerData(): array
    {
        $popularRouteModels = PopularRoute::query()
            ->latest()
            ->limit(50)
            ->get();

        $airportCities = Airport::query()
            ->whereIn(
                'iata_code',
                $popularRouteModels
                    ->flatMap(fn (PopularRoute $popularRoute) => [
                        $popularRoute->from_airport,
                        $popularRoute->to_airport,
                    ])
                    ->filter()
                    ->unique(),
            )
            ->pluck('city_name', 'iata_code');

        $popularRoutes = $popularRouteModels
            ->map(function (PopularRoute $popularRoute) use ($airportCities) {
                $departureDate = Carbon::today()->addDays((int) ($popularRoute->departure_plus_days ?? 1));
                $returnDate = $popularRoute->journey_type === 'round' && $popularRoute->stay_duration_days !== null
                    ? $departureDate->copy()->addDays((int) $popularRoute->stay_duration_days)
                    : null;

                $query = array_filter([
                    'origin' => $popularRoute->from_airport,
                    'destination' => $popularRoute->to_airport,
                    'departure_date' => $departureDate->toDateString(),
                    'return_date' => $returnDate?->toDateString(),
                    'flightType' => $popularRoute->journey_type === 'round' ? 'return' : 'one-way',
                    'cabin_class' => $popularRoute->travel_class === 'business' ? 'C' : 'Y',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ], static fn ($value) => $value !== null);

                return [
                    'id' => $popularRoute->id,
                    'type' => $popularRoute->type,
                    'label' => ($airportCities->get($popularRoute->from_airport) ?: $popularRoute->from_airport)
                        . ' to '
                        . ($airportCities->get($popularRoute->to_airport) ?: $popularRoute->to_airport),
                    'url' => url('/popular-routes/' . $popularRoute->id) . '?' . http_build_query($query),
                ];
            });

        $topAirlines = TopAirline::query()
            ->where('is_active', true)
            ->latest()
            ->limit(100)
            ->get(['id', 'name', 'type']);

        return [
            'domesticPopularRoutes' => $popularRoutes->where('type', 'domestic')->values(),
            'internationalPopularRoutes' => $popularRoutes->where('type', 'international')->values(),
            'domesticAirlines' => $topAirlines->where('type', 'domestic')->values(),
            'internationalAirlines' => $topAirlines->where('type', 'international')->values(),
        ];
    }

    private function robots($seo): string
    {
        return implode(', ', [
            optional($seo)->no_index ? 'noindex' : 'index',
            optional($seo)->no_follow ? 'nofollow' : 'follow',
        ]);
    }
}
