<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Jetze</title>

    <link rel="icon" type="image/png" href="/favicon.png?v=5" sizes="354x354">
    <link rel="shortcut icon" type="image/png" href="/favicon.png?v=5">
    <link rel="apple-touch-icon" href="/favicon.png?v=5">

    @include('partials.google-tag')

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font Awesome kit -->
    <script src="https://kit.fontawesome.com/8a3b522169.js" crossorigin="anonymous"></script>
    {{-- <script src="https://assets.duffel.com/components/3.3.1/duffel-payments.js"></script> --}}

</head>

<body>
    <div id="app"></div>
</body>

</html>