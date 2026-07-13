<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $pageTitle = $seo?->meta_title ?: trim($__env->yieldContent('title')) . ' | Jetze';
        $pageDescription = $seo?->meta_description ?: trim($__env->yieldContent('description'));
        $ogImage = $seo?->og_image ? asset('storage/' . ltrim($seo->og_image, '/')) : null;
        $twitterImage = $seo?->twitter_image ? asset('storage/' . ltrim($seo->twitter_image, '/')) : $ogImage;
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    @if($seo?->meta_keywords)<meta name="keywords" content="{{ $seo->meta_keywords }}">@endif
    <meta name="robots" content="{{ $seo?->robots_meta ?: 'index, follow' }}">
    <link rel="canonical" href="{{ $seo?->canonical_url ?: url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo?->og_title ?: $pageTitle }}">
    <meta property="og:description" content="{{ $seo?->og_description ?: $pageDescription }}">
    <meta property="og:url" content="{{ $seo?->canonical_url ?: url()->current() }}">
    @if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    @if($seo?->alt_text)<meta property="og:image:alt" content="{{ $seo->alt_text }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo?->twitter_title ?: ($seo?->og_title ?: $pageTitle) }}">
    <meta name="twitter:description" content="{{ $seo?->twitter_description ?: ($seo?->og_description ?: $pageDescription) }}">
    @if($twitterImage)<meta name="twitter:image" content="{{ $twitterImage }}">@endif
    @if($seo?->schema_json)<script type="application/ld+json">{!! json_encode($seo->schema_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>@endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @include('partials.google-tag')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white font-roboto text-gray-900 antialiased">
    @include('partials.marketing-nav')
    <main>@yield('content')</main>
    @include('blogs.partials.footer')
    @stack('scripts')
</body>
</html>
