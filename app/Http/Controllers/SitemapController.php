<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\PopularRoute;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            $this->url(url('/'), now(), 'daily', '1.0'),
            $this->url(route('blog.index'), now(), 'weekly', '0.8'),
            $this->url(url('/about/us'), null, 'monthly', '0.6'),
            $this->url(url('/contact/us'), null, 'monthly', '0.6'),
            $this->url(url('/our/services'), null, 'monthly', '0.6'),
            $this->url(url('/visa'), null, 'weekly', '0.6'),
            $this->url(url('/holidays'), null, 'weekly', '0.6'),
            $this->url(url('/umra-packages'), null, 'weekly', '0.6'),
            $this->url(url('/travel-insurance'), null, 'monthly', '0.5'),
            $this->url(url('/group-tickets-main'), null, 'monthly', '0.5'),
            $this->url(url('/privacy-policy'), null, 'yearly', '0.3'),
            $this->url(url('/terms-condition'), null, 'yearly', '0.3'),
        ]);

        Blog::query()
            ->where('is_published', true)
            ->select(['id', 'slug', 'updated_at', 'published_at'])
            ->orderBy('id')
            ->chunkById(200, function ($blogs) use (&$urls) {
                foreach ($blogs as $blog) {
                    $urls->push($this->url(
                        route('blog.show', $blog->slug),
                        $blog->updated_at ?? $blog->published_at,
                        'monthly',
                        '0.8'
                    ));
                }
            });

        PopularRoute::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($routes) use (&$urls) {
                foreach ($routes as $route) {
                    $urls->push($this->url(
                        url('/popular-routes/' . $route->id),
                        $route->updated_at,
                        'weekly',
                        '0.7'
                    ));
                }
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    private function url(string $loc, $lastmod = null, ?string $changefreq = null, ?string $priority = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? Carbon::parse($lastmod)->toAtomString() : null,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}
