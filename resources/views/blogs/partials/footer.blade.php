<footer class="border-t border-gray-100 bg-white">
    <div class="container mx-auto grid gap-10 px-4 py-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <img src="{{ asset('assets/logo.png') }}" class="h-10 w-auto" alt="Jetze Logo">
            <p class="mt-5 max-w-md text-sm leading-7 text-gray-600">
                We provide domestic and international air tickets with helpful travel content for every journey.
            </p>

            <div class="mt-6 flex space-x-4" aria-label="Social media links">
                <a href="https://www.tiktok.com/@Jetze.pk" target="_blank" rel="noopener noreferrer" aria-label="TikTok"
                   class="bg-white border border-gray-200 hover:border-primary p-3 transition-all duration-200">
                    <img src="{{ asset('assets/tiktok.png') }}" alt="TikTok" class="w-8 h-8 object-contain">
                </a>
                <a href="https://www.facebook.com/Jetzet/" target="_blank"
                   rel="noopener noreferrer" aria-label="Facebook"
                   class="bg-white border border-gray-200 hover:border-primary p-1 transition-all duration-200">
                    <img src="{{ asset('assets/fb.png') }}" alt="Facebook" class="w-12 h-12 object-contain">
                </a>
                <a href="https://www.instagram.com/Jetze.pk/" target="_blank"
                   rel="noopener noreferrer" aria-label="Instagram"
                   class="bg-white border border-gray-200 hover:border-primary p-3 transition-all duration-200">
                    <img src="{{ asset('assets/instagram.png') }}" alt="Instagram" class="w-8 h-8 object-contain">
                </a>
                <a href="https://www.youtube.com/@Jetze" target="_blank"
                   rel="noopener noreferrer" aria-label="YouTube"
                   class="bg-white border border-gray-200 hover:border-primary p-1 transition-all duration-200">
                    <img src="{{ asset('assets/yt.png') }}" alt="YouTube" class="w-12 h-12 object-contain">
                </a>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="grid gap-10 md:grid-cols-2">
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-950">Top Airlines</h2>
                    <div class="mt-4 grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-bold text-primary">Domestic</h3>
                            <ul class="mt-3 space-y-2 text-sm text-gray-600">
                                @forelse($domesticAirlines as $airline)
                                    <li>{{ $airline->name }}</li>
                                @empty
                                    <li class="text-gray-400">No airlines available</li>
                                @endforelse
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-primary">International</h3>
                            <ul class="mt-3 grid gap-x-4 gap-y-2 text-sm text-gray-600 sm:grid-cols-2">
                                @forelse($internationalAirlines as $airline)
                                    <li>{{ $airline->name }}</li>
                                @empty
                                    <li class="text-gray-400 sm:col-span-2">No airlines available</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-950">Popular Flight Routes</h2>
                    <div class="mt-4 grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-bold text-primary">Domestic</h3>
                            <ul class="mt-3 space-y-2 text-sm text-gray-600">
                                @forelse($domesticPopularRoutes as $popularRoute)
                                    <li>
                                        <a href="{{ $popularRoute['url'] }}" class="transition-colors hover:text-primary">
                                            {{ $popularRoute['label'] }}
                                        </a>
                                    </li>
                                @empty
                                    <li class="text-gray-400">No routes available</li>
                                @endforelse
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-primary">International</h3>
                            <ul class="mt-3 space-y-2 text-sm text-gray-600">
                                @forelse($internationalPopularRoutes as $popularRoute)
                                    <li>
                                        <a href="{{ $popularRoute['url'] }}" class="transition-colors hover:text-primary">
                                            {{ $popularRoute['label'] }}
                                        </a>
                                    </li>
                                @empty
                                    <li class="text-gray-400">No routes available</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-10 grid gap-8 border-t border-gray-100 pt-8 sm:grid-cols-3">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-950">Company</h2>
                    <ul class="mt-4 space-y-3 text-sm text-gray-600">
                        <li><a href="{{ url('/about/us') }}" class="transition hover:text-primary">About Us</a></li>
                        <li><a href="{{ url('/contact/us') }}" class="transition hover:text-primary">Contact Us</a></li>
                        <li><a href="{{ route('blog.index') }}" class="transition hover:text-primary">Blogs</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-950">Help</h2>
                    <ul class="mt-4 space-y-3 text-sm text-gray-600">
                        <li><a href="{{ url('/how-to-use-abhi-pay-bank-transfer') }}" class="transition hover:text-primary">How To Use AbhiPay</a></li>
                        <li><a href="{{ url('/privacy-policy') }}" class="transition hover:text-primary">Privacy Policy</a></li>
                        <li><a href="{{ url('/terms-condition') }}" class="transition hover:text-primary">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-[0.18em] text-primary">Newsletter</h2>
                    <form class="mt-4 flex gap-2">
                        <input type="email" name="email" placeholder="Email address" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-primary">
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90">Join</button>
                    </form>
                    <p class="mt-3 text-xs text-gray-500">Fresh reads, routes, and travel updates.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-gray-950 py-6 text-center text-sm text-gray-400">
        &copy; {{ now()->year }} <span class="font-semibold text-white">Jetze</span>. All Rights Reserved.
    </div>
</footer>
