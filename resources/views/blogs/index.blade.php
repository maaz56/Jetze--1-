<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @include('partials.google-tag')
    @vite(['resources/css/app.css'])
</head>
@php
    $blogItems = $blogs->getCollection();
    $featuredBlog = $blogItems->first();
    $sideBlogs = $blogItems->slice(1, 2);
    $smallBlog = $blogItems->slice(3, 1)->first();
    $popularBlogs = $blogItems->take(4);

    $imageUrl = function ($blog) {
        if (!$blog || !$blog->featured_image) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $blog->featured_image)) {
            return $blog->featured_image;
        }

        return asset('storage/' . ltrim($blog->featured_image, '/'));
    };

    $dateLabel = fn ($blog) => optional($blog->published_at ?: $blog->created_at)->format('M d, Y');
    $authorLabel = fn ($blog) => optional($blog->author)->name ?: optional($blog->author)->email ?: 'Jetze';
    $summary = fn ($blog, $limit = 120) => \Illuminate\Support\Str::limit(strip_tags($blog->excerpt ?: $blog->content), $limit);
@endphp
<body class="bg-white font-roboto text-gray-900 antialiased">
    @include('partials.marketing-nav')

    <main>
        <section class="border-b border-gray-100 bg-white">
            <div class="container mx-auto px-4 py-10 lg:py-14">
                <div class="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Travel Journal</p>
                        <h1 class="mt-3 text-4xl font-bold tracking-tight text-gray-950 md:text-5xl">Latest Travel Blogs</h1>
                    </div>
                    <p class="max-w-xl text-base leading-7 text-gray-600">
                        Fresh routes, airline updates, booking tips, and travel guides from Jetze.
                    </p>
                </div>

                @if($blogs->count())
                    <div class="grid gap-5 lg:grid-cols-3">
                        @if($featuredBlog)
                            <article class="group relative min-h-[420px] overflow-hidden rounded-lg border border-gray-200 bg-gray-950 shadow-sm lg:col-span-2">
                                <a href="{{ route('blog.show', $featuredBlog->slug) }}" class="absolute inset-0" aria-label="{{ $featuredBlog->title }}"></a>
                                @if($imageUrl($featuredBlog))
                                    <img src="{{ $imageUrl($featuredBlog) }}" alt="{{ $featuredBlog->title }}" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                @else
                                    <div class="absolute inset-0 bg-gradient-to-br from-primary via-gray-900 to-gray-700"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/10"></div>
                                <div class="relative flex min-h-[420px] flex-col justify-end p-6 sm:p-8 lg:p-10">
                                    <div class="mb-4 flex flex-wrap items-center gap-3 text-xs font-bold uppercase tracking-[0.18em] text-white/85">
                                        <span class="rounded bg-primary px-2 py-1 text-primary-foreground">Featured</span>
                                        <span>{{ $dateLabel($featuredBlog) }}</span>
                                        <span>{{ $authorLabel($featuredBlog) }}</span>
                                    </div>
                                    <h2 class="max-w-3xl text-3xl font-bold leading-tight text-white sm:text-4xl lg:text-5xl">
                                        {{ $featuredBlog->title }}
                                    </h2>
                                    @if($summary($featuredBlog, 150))
                                        <p class="mt-4 max-w-2xl text-base leading-7 text-white/80">{{ $summary($featuredBlog, 150) }}</p>
                                    @endif
                                </div>
                            </article>
                        @endif

                        <div class="grid gap-5">
                            @foreach($sideBlogs as $blog)
                                <article class="group relative min-h-[200px] overflow-hidden rounded-lg border border-gray-200 bg-gray-950 shadow-sm">
                                    <a href="{{ route('blog.show', $blog->slug) }}" class="absolute inset-0" aria-label="{{ $blog->title }}"></a>
                                    @if($imageUrl($blog))
                                        <img src="{{ $imageUrl($blog) }}" alt="{{ $blog->title }}" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                    @else
                                        <div class="absolute inset-0 bg-gradient-to-br from-primary/90 to-gray-800"></div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>
                                    <div class="relative flex min-h-[200px] flex-col justify-end p-5">
                                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-white/75">{{ $dateLabel($blog) }}</p>
                                        <h3 class="text-xl font-bold leading-tight text-white">{{ $blog->title }}</h3>
                                        <p class="mt-2 text-xs text-white/75">By {{ $authorLabel($blog) }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    @if($smallBlog)
                        <article class="mt-6 max-w-2xl">
                            <a href="{{ route('blog.show', $smallBlog->slug) }}" class="group flex gap-4">
                                <div class="h-20 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                    @if($imageUrl($smallBlog))
                                        <img src="{{ $imageUrl($smallBlog) }}" alt="{{ $smallBlog->title }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Latest</p>
                                    <h3 class="mt-1 text-base font-bold leading-snug text-gray-950 transition group-hover:text-primary">{{ $smallBlog->title }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $dateLabel($smallBlog) }}</p>
                                </div>
                            </a>
                        </article>
                    @endif
                @else
                    <div class="rounded-lg border border-gray-200 bg-white p-10 text-center shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Travel Journal</p>
                        <h1 class="mt-3 text-3xl font-bold text-gray-950">No blogs are published yet.</h1>
                        <p class="mt-3 text-gray-600">Published blogs will appear here automatically.</p>
                    </div>
                @endif
            </div>
        </section>

        @if($blogs->count())
            <section class="bg-gray-50">
                <div class="container mx-auto grid gap-10 px-4 py-12 lg:grid-cols-[minmax(0,1fr)_320px] lg:py-16">
                    <div>
                        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-primary">Latest Posts</p>
                                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-950">Fresh Reads For You</h2>
                            </div>
                            <a href="{{ route('blog.index') }}" class="inline-flex text-sm font-semibold text-primary transition hover:opacity-80">
                                View all &rarr;
                            </a>
                        </div>

                        <div class="mb-7 flex flex-wrap gap-2">
                            <span class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground">All Topics</span>
                            <span class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700">Travel</span>
                            <span class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700">Flights</span>
                            <span class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700">Guides</span>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($blogs as $blog)
                                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                                    <a href="{{ route('blog.show', $blog->slug) }}" class="block">
                                        <div class="aspect-[16/10] bg-gray-100">
                                            @if($imageUrl($blog))
                                                <img src="{{ $imageUrl($blog) }}" alt="{{ $blog->title }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-semibold text-primary">
                                                    Jetze
                                                </div>
                                            @endif
                                        </div>
                                    </a>

                                    <div class="p-5">
                                        <div class="mb-3 flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gray-500">
                                            <span>{{ $dateLabel($blog) }}</span>
                                            <span class="h-1 w-1 rounded-full bg-primary"></span>
                                            <span>{{ $authorLabel($blog) }}</span>
                                        </div>
                                        <h3 class="text-xl font-bold leading-tight text-gray-950">
                                            <a href="{{ route('blog.show', $blog->slug) }}" class="transition hover:text-primary">
                                                {{ \Illuminate\Support\Str::limit($blog->title, 72) }}
                                            </a>
                                        </h3>
                                        @if($summary($blog, 95))
                                            <p class="mt-3 text-sm leading-6 text-gray-600">{{ $summary($blog, 95) }}</p>
                                        @endif
                                        <a href="{{ route('blog.show', $blog->slug) }}" class="mt-5 inline-flex text-sm font-semibold text-primary transition hover:opacity-80">
                                            Read more
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="mt-10">
                            {{ $blogs->links() }}
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-sm font-bold uppercase tracking-[0.22em] text-gray-950">Topics</h2>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <span class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700">Travel Guides</span>
                                <span class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700">Flight Deals</span>
                                <span class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700">Booking Tips</span>
                                <span class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700">Airlines</span>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <h2 class="text-sm font-bold uppercase tracking-[0.22em] text-gray-950">Most Discussed</h2>
                            <div class="mt-5 divide-y divide-gray-100">
                                @foreach($popularBlogs as $blog)
                                    <article class="py-4 first:pt-0 last:pb-0">
                                        <a href="{{ route('blog.show', $blog->slug) }}" class="group flex gap-3">
                                            <div class="h-14 w-16 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                                @if($imageUrl($blog))
                                                    <img src="{{ $imageUrl($blog) }}" alt="{{ $blog->title }}" class="h-full w-full object-cover">
                                                @endif
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-bold leading-snug text-gray-950 transition group-hover:text-primary">{{ \Illuminate\Support\Str::limit($blog->title, 72) }}</h3>
                                                <p class="mt-1 text-xs text-gray-500">{{ $authorLabel($blog) }}</p>
                                            </div>
                                        </a>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        @endif
    </main>

    @include('blogs.partials.footer')
</body>
</html>
