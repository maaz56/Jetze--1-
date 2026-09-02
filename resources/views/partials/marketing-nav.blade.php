@php
    $navItems = [
        ['url' => url('/'), 'label' => 'Flights', 'subtitle' => 'Book Cheapest', 'icon' => asset('plane.png'), 'active' => request()->is('/')],
        ['url' => route('about-us'), 'label' => 'About Us', 'subtitle' => 'Who we are', 'icon' => asset('info.png'), 'active' => request()->routeIs('about-us')],
        ['url' => route('blog.index'), 'label' => 'Blogs', 'subtitle' => 'Travel Stories', 'icon' => asset('blog.png'), 'active' => request()->routeIs('blog.*')],
        ['url' => route('contact-us'), 'label' => 'Contact Us', 'subtitle' => 'Get in touch', 'icon' => asset('info.png'), 'active' => request()->routeIs('contact-us')],
    ];
@endphp

<header class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 text-slate-900 shadow-2xl backdrop-blur-md">
    <nav class="container mx-auto flex h-20 items-center justify-between px-4 lg:px-10" aria-label="Main navigation">
        <a href="{{ url('/') }}" class="flex shrink-0 items-center" aria-label="Jetze home">
            <img src="{{ asset('assets/logo.png') }}" alt="Jetze" class="h-10 w-auto lg:h-12">
        </a>

        <div class="hidden items-center gap-1 rounded-2xl bg-slate-50/80 p-1 shadow-sm xl:flex">
            @foreach($navItems as $item)
                <a href="{{ $item['url'] }}"
                   @class([
                       'group relative flex items-center rounded-lg px-4 py-2.5 transition-all duration-200',
                       'text-primary' => $item['active'],
                       'text-slate-700 hover:bg-sky-50 hover:text-primary' => !$item['active'],
                   ])
                   @if($item['active']) aria-current="page" @endif>
                    <span @class(['mr-3 rounded-xl p-2 transition-all duration-200', 'bg-primary/10' => $item['active'], 'bg-sky-100 group-hover:bg-primary/15' => !$item['active']])>
                        <img src="{{ $item['icon'] }}" alt="" @class(['h-5 w-5 object-contain transition-all duration-300', 'scale-110' => $item['active']])>
                    </span>
                    <span class="flex flex-col">
                        <span class="text-[13px] font-bold leading-tight">{{ $item['label'] }}</span>
                        <span @class(['text-[10px]', 'text-primary/75' => $item['active'], 'text-slate-500 group-hover:text-primary/75' => !$item['active']])>{{ $item['subtitle'] }}</span>
                    </span>
                    @if($item['active'])
                        <span aria-hidden="true" class="absolute inset-x-3 bottom-0 h-[3px] rounded-sm bg-primary"></span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ url('/') }}" class="hidden items-center gap-3 rounded-md border border-slate-300 bg-white px-4 py-2 text-slate-900 shadow-sm transition hover:bg-slate-100 sm:flex">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4m-4-4 5-5-5-5m5 5H3" /></svg>
                </span>
                <span class="flex flex-col items-start leading-none">
                    <span class="text-[11px] font-medium text-slate-500">Login or</span>
                    <span class="text-sm font-bold">Create Account</span>
                </span>
                <span class="text-slate-400" aria-hidden="true">⌄</span>
            </a>

            <details class="relative xl:hidden">
                <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-md hover:bg-slate-100 [&::-webkit-details-marker]:hidden" aria-label="Open navigation menu">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </summary>
                <div class="absolute right-0 mt-3 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-2xl">
                    @foreach($navItems as $item)
                        <a href="{{ $item['url'] }}" @class(['relative flex items-center rounded-xl p-3 transition hover:bg-sky-50', 'text-primary' => $item['active'], 'text-slate-700' => !$item['active']]) @if($item['active']) aria-current="page" @endif>
                            <span @class(['mr-3 rounded-lg p-2', 'bg-primary/10' => $item['active'], 'bg-sky-100' => !$item['active']])><img src="{{ $item['icon'] }}" alt="" class="h-5 w-5 object-contain"></span>
                            <span><span class="block text-sm font-bold">{{ $item['label'] }}</span><span @class(['block text-[10px]', 'text-primary/75' => $item['active'], 'text-slate-500' => !$item['active']])>{{ $item['subtitle'] }}</span></span>
                            @if($item['active'])
                                <span aria-hidden="true" class="absolute inset-x-3 bottom-0 h-[3px] rounded-sm bg-primary"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </details>
        </div>
    </nav>
</header>
