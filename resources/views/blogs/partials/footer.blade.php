<footer class="border-t border-gray-100 bg-white">
    <div class="container mx-auto grid gap-10 px-4 py-12 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <img src="{{ asset('assets/logo.png') }}" class="h-10 w-auto" alt="Jetze Logo">
            <p class="mt-5 max-w-md text-sm leading-7 text-gray-600">
                We provide domestic and international air tickets with helpful travel content for every journey.
            </p>

            <div class="mt-6 flex space-x-4" aria-label="Social media links">
                <a href="https://www.facebook.com/Jetzet/" target="_blank" rel="noopener noreferrer" class="bg-gray-100 p-3 text-gray-600 transition-all duration-200 hover:bg-primary hover:text-white" aria-label="Facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                    </svg>
                </a>
                <a href="https://www.instagram.com/Jetze.pk/" target="_blank" rel="noopener noreferrer" class="bg-gray-100 p-3 text-gray-600 transition-all duration-200 hover:bg-primary hover:text-white" aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                        <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
                    </svg>
                </a>
                <a href="https://www.youtube.com/@Jetze" target="_blank" rel="noopener noreferrer" class="bg-gray-100 p-3 text-gray-600 transition-all duration-200 hover:bg-primary hover:text-white" aria-label="YouTube">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17" />
                        <path d="m10 15 5-3-5-3z" />
                    </svg>
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
