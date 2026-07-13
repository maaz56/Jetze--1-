@extends('layouts.marketing')

@section('title', 'About Us')
@section('description', 'Learn how Jetze makes flight booking fast, transparent, and affordable.')

@section('content')
<section class="relative overflow-hidden bg-cover bg-center text-white" style="background-image:url('https://wallpapers.com/images/hd/plane-desktop-yms31u8wyuke7ari.jpg')">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="container relative mx-auto px-6 py-28 text-center">
        <h1 class="mb-6 text-5xl font-extrabold leading-tight md:text-6xl">{{ $seo?->h1 ?: 'About Jetze' }}</h1>
        <p class="mx-auto mb-8 max-w-4xl text-xl font-medium opacity-95 md:text-2xl">Book Flights Smarter. Fly Cheaper.</p>
        <p class="mx-auto max-w-3xl text-lg leading-relaxed opacity-90 md:text-xl">Jetze is a leading flight booking platform that helps millions of travelers find the best deals instantly. We search hundreds of airlines and travel sites to bring you the lowest prices with zero hidden fees.</p>
    </div>
</section>

<section class="bg-white py-20">
    <div class="container mx-auto px-6">
        <div class="mb-16 text-center">
            <h2 class="mb-6 text-4xl font-bold text-gray-900 md:text-5xl">Your Trusted Flight Partner</h2>
            <p class="mx-auto max-w-3xl text-xl leading-relaxed text-gray-600">To make flight booking fast, transparent, and affordable for everyone — whether you’re traveling for business, family, or adventure.</p>
        </div>
        <div class="mt-16 grid gap-10 md:grid-cols-3">
            @foreach([
                ['title' => 'No Hidden Fees', 'copy' => 'The price you see is the price you pay. Always.', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['title' => 'Instant Booking', 'copy' => 'Get confirmed tickets in seconds, not hours.', 'path' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['title' => 'Best Price Finder', 'copy' => 'We compare 700+ airlines to get you the lowest fares.', 'path' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'],
            ] as $promise)
                <article class="rounded-2xl border border-gray-200 bg-gray-50 p-10 text-center">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary">
                        <svg class="h-12 w-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $promise['path'] }}" /></svg>
                    </div>
                    <h3 class="mb-3 text-2xl font-bold text-gray-900">{{ $promise['title'] }}</h3>
                    <p class="text-gray-600">{{ $promise['copy'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-gray-100 py-20">
    <div class="container mx-auto grid grid-cols-2 gap-8 px-6 md:grid-cols-4">
        @foreach([['5M+', 'Tickets Booked'], ['700+', 'Airlines'], ['190+', 'Countries'], ['24/7', 'Support']] as $stat)
            <div class="text-center"><div class="mb-2 text-5xl font-extrabold text-primary">{{ $stat[0] }}</div><p class="font-semibold text-gray-700">{{ $stat[1] }}</p></div>
        @endforeach
    </div>
</section>

<section class="bg-white py-20">
    <div class="container mx-auto px-6 text-center">
        <h2 class="mb-12 text-4xl font-bold text-gray-900 md:text-5xl">Why Millions Choose APNTicket</h2>
        <div class="mx-auto grid max-w-5xl gap-10 md:grid-cols-3">
            @foreach([
                ['Real-Time Deals', 'Live price tracking across all major airlines and OTAs.'],
                ['Secure & Fast', 'Bank-level encryption. Instant e-tickets delivered to your inbox.'],
                ['Flexible Options', 'Free date changes on most bookings. Easy cancellations.'],
            ] as $reason)
                <article class="rounded-2xl border border-primary/10 bg-primary/5 p-8"><h3 class="mb-4 text-2xl font-bold text-gray-900">{{ $reason[0] }}</h3><p class="text-gray-600">{{ $reason[1] }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-primary py-20 text-white">
    <div class="container mx-auto px-6 text-center">
        <h2 class="mb-6 text-4xl font-bold md:text-5xl">Ready to Book Your Next Flight?</h2>
        <p class="mx-auto mb-10 max-w-2xl text-xl opacity-90">Join millions of smart travelers who save time and money with Jetze.pk</p>
        <a href="{{ url('/') }}" class="inline-block rounded-full bg-white px-12 py-5 text-xl font-bold text-primary shadow-xl transition hover:bg-gray-100">Search Flights Now</a>
    </div>
</section>
@endsection
