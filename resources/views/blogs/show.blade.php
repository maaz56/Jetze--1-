<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    @if($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
    @if($metaKeywords)<meta name="keywords" content="{{ $metaKeywords }}">@endif
    <meta name="robots" content="{{ $robots }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $ogTitle }}">
    @if($ogDescription)<meta property="og:description" content="{{ $ogDescription }}">@endif
    @if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @include('partials.google-tag')
    @vite(['resources/css/app.css'])
    <style>
        .article-content { color: #4b5563; font-size: 16px; line-height: 1.85; }
        .article-content > *:first-child { margin-top: 0; }
        .article-content h1, .article-content h2, .article-content h3, .article-content h4 {
            color: #111827 !important; font-weight: 750; line-height: 1.25; letter-spacing: -.018em;
            margin: 2.25rem 0 .85rem;
        }
        .article-content h2 { font-size: 1.75rem; }
        .article-content h3 { font-size: 1.35rem; }
        .article-content h4 { font-size: 1.1rem; }
        .article-content p { margin: 0 0 1.15rem; color: #4b5563 !important; }
        .article-content strong, .article-content b { color: #111827 !important; font-weight: 700; }
        .article-content a { color: hsl(var(--primary)) !important; font-weight: 650; text-decoration: none; }
        .article-content a:hover { text-decoration: underline; }
        .article-content ul, .article-content ol { margin: 1rem 0 1.35rem 1.4rem; padding: 0; }
        .article-content ul { list-style: disc; }
        .article-content ol { list-style: decimal; }
        .article-content li { margin: .45rem 0; padding-left: .25rem; color: #4b5563 !important; }
        .article-content li::marker { color: hsl(var(--primary)); }
        .article-content blockquote {
            border-left: 3px solid hsl(var(--primary)); background: #f8fafc; color: #374151 !important;
            margin: 1.6rem 0; padding: 1rem 1.2rem; border-radius: 0 8px 8px 0;
        }
        .article-content img { width: 100%; height: auto; border-radius: 8px; margin: 1.6rem 0; }
        .article-content hr { border: 0; border-top: 1px solid #e5e7eb; margin: 2rem 0; }
        .article-content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; display: block; overflow-x: auto; }
        .article-content th, .article-content td { border: 1px solid #e5e7eb; padding: .75rem; text-align: left; }
        .article-content th { background: #f9fafb; color: #111827 !important; }
        .article-content code { color: #be123c; background: #f3f4f6; padding: .15rem .35rem; border-radius: 4px; }
        .article-content pre { overflow-x: auto; background: #111827; color: #e5e7eb; padding: 1rem; border-radius: 8px; margin: 1.5rem 0; }
        .article-content pre code { color: inherit; background: transparent; padding: 0; }
    </style>
</head>
@php
    $imageUrl = function ($item) {
        if (!$item || !$item->featured_image) return null;
        return preg_match('/^https?:\/\//i', $item->featured_image)
            ? $item->featured_image
            : asset('storage/' . ltrim($item->featured_image, '/'));
    };
    $publishedDate = optional($blog->published_at ?: $blog->created_at);
    $authorName = optional($blog->author)->name ?: optional($blog->author)->email ?: 'Jetze Editorial';
    $readingMinutes = max(1, (int) ceil(str_word_count(strip_tags($blog->content)) / 220));
    $otherBlogs = $relatedBlogs->take(5);
    $popularBlogs = $relatedBlogs->slice(5, 3)->values();
@endphp
<body class="bg-white font-roboto text-gray-900 antialiased">
    @include('partials.marketing-nav')

    <main class="bg-white">
        <div class="container mx-auto px-4 py-8 lg:py-12">
            <nav class="mb-7 flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('blog.index') }}" class="transition hover:text-primary">All Posts</a>
                <span>/</span>
                <span class="text-gray-700">{{ \Illuminate\Support\Str::limit($blog->title, 85) }}</span>
            </nav>

            <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_320px] xl:gap-14">
                <article class="min-w-0">
                    <div class="mb-4 flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em]">
                        <span class="rounded bg-primary px-2.5 py-1 text-primary-foreground">Travel Guide</span>
                        <span class="rounded bg-gray-100 px-2.5 py-1 text-gray-500">{{ $readingMinutes }} min read</span>
                    </div>

                    <h1 class="max-w-4xl text-4xl font-extrabold leading-[1.1] tracking-[-0.035em] text-gray-950 sm:text-5xl lg:text-[56px]">
                        {{ $blog->title }}
                    </h1>

                    @if($blog->excerpt)
                        <p class="mt-5 max-w-3xl text-lg leading-8 text-gray-600">{{ $blog->excerpt }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500">
                        <span class="font-semibold text-primary">{{ $authorName }}</span>
                        <span class="h-1 w-1 rounded-full bg-gray-300"></span>
                        <time datetime="{{ $publishedDate?->toDateString() }}">{{ $publishedDate?->format('M d, Y') }}</time>
                        <span class="h-1 w-1 rounded-full bg-gray-300"></span>
                        <span>{{ $readingMinutes }} minute read</span>
                    </div>

                    @if($imageUrl($blog))
                        <figure class="mt-8 overflow-hidden rounded-lg border border-gray-200 bg-gray-100 shadow-sm">
                            <img src="{{ $imageUrl($blog) }}" alt="{{ $blog->title }}" class="aspect-[16/8.7] w-full object-cover">
                        </figure>
                    @endif

                    <div class="article-content mt-9">
                        {!! $blog->content !!}
                    </div>

                    <div class="mt-12 border-t border-gray-200 pt-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach(['Travel', 'Flights', 'Booking Tips'] as $topic)
                                    <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600">{{ $topic }}</span>
                                @endforeach
                            </div>
                            <a href="{{ route('blog.index') }}" class="text-sm font-semibold text-primary transition hover:opacity-80">All Blogs →</a>
                        </div>
                    </div>
                </article>

                <aside class="space-y-6 lg:sticky lg:top-28">
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-950">Other Blogs</h2>
                            <a href="{{ route('blog.index') }}" class="text-xs font-semibold text-primary">All</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($otherBlogs as $item)
                                <a href="{{ route('blog.show', $item->slug) }}" class="group flex gap-3 py-4 first:pt-0 last:pb-0">
                                    <div class="h-16 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                        @if($imageUrl($item))
                                            <img src="{{ $imageUrl($item) }}" alt="{{ $item->title }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Travel Guide</p>
                                        <h3 class="mt-1 text-sm font-bold leading-snug text-gray-950 transition group-hover:text-primary">{{ \Illuminate\Support\Str::limit($item->title, 62) }}</h3>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ optional($item->published_at ?: $item->created_at)->format('M d, Y') }}</p>
                                    </div>
                                </a>
                            @empty
                                <p class="text-sm text-gray-500">More travel stories are coming soon.</p>
                            @endforelse
                        </div>
                    </section>

                    @if($popularBlogs->isNotEmpty())
                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-950">Popular Now</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($popularBlogs as $item)
                                    <a href="{{ route('blog.show', $item->slug) }}" class="group block">
                                        <h3 class="text-sm font-bold leading-snug text-gray-800 transition group-hover:text-primary">{{ \Illuminate\Support\Str::limit($item->title, 76) }}</h3>
                                        <p class="mt-1 text-xs text-gray-400">{{ optional($item->published_at ?: $item->created_at)->format('M d, Y') }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-950">Topics</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach(['Travel', 'Flights', 'How To', 'Guides'] as $topic)
                                <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Explore</h2>
                        <div class="mt-4 divide-y divide-gray-100 border-y border-gray-100">
                            <a href="{{ route('blog.index') }}" class="flex items-center justify-between py-3 text-sm font-semibold text-gray-700 hover:text-primary">All posts <span>›</span></a>
                            <a href="{{ url('/') }}" class="flex items-center justify-between py-3 text-sm font-semibold text-gray-700 hover:text-primary">Search flights <span>›</span></a>
                            <a href="{{ url('/contact/us') }}" class="flex items-center justify-between py-3 text-sm font-semibold text-gray-700 hover:text-primary">Contact us <span>›</span></a>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </main>

    @include('blogs.partials.footer')
</body>
</html>
