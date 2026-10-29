<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.google-site-verification')
    @include('partials.google-tag-manager')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jetze</title>

    <link rel="icon" type="image/png" href="/favicon.png?v=5" sizes="354x354">
    <link rel="shortcut icon" type="image/png" href="/favicon.png?v=5">
    <link rel="apple-touch-icon" href="/favicon.png?v=5">

    @include('partials.google-tag')

    @if (request()->is('/'))
        {{-- Start the hero photo with the HTML instead of after the Home chunk renders. --}}
        <link rel="preload" as="image" type="image/webp" fetchpriority="high" media="(max-width: 639px)"
            href="/images/hero/banner-mobile.webp">
        <link rel="preload" as="image" type="image/webp" fetchpriority="high" media="(min-width: 640px)"
            imagesrcset="/images/hero/banner-1280.webp 1280w, /images/hero/banner-1920.webp 1920w, /images/hero/banner-2560.webp 2560w"
            imagesizes="100vw">
    @endif

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font Awesome kit -->
    <script src="https://kit.fontawesome.com/8a3b522169.js" crossorigin="anonymous"></script>
    {{-- <script src="https://assets.duffel.com/components/3.3.1/duffel-payments.js"></script> --}}

</head>

<body>
    @include('partials.google-tag-manager-noscript')
    <div id="app"></div>
</body>

</html>
