<script setup>
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-primary/5 via-white to-primary/10">
    <!-- Hero Section -->
    <section class="relative overflow-hidden px-6 py-20 md:py-28 bg-gradient-to-br from-primary/10 via-transparent to-transparent">
      <div class="absolute inset-0 bg-gradient-to-l from-primary/20 to-transparent opacity-40"></div>
      <div class="relative mx-auto max-w-7xl text-center">
        <h1 class="text-5xl md:text-7xl font-bold text-gray-900 mb-6 leading-tight">
          Our <span class="text-primary">Services</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-700 max-w-3xl mx-auto leading-relaxed">
          From booking to boarding — every step of your journey, elevated.
        </p>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-white to-transparent"></div>
    </section>

    <!-- Core Services Grid -->
    <section class="px-6 py-16 md:py-24">
      <div class="mx-auto max-w-7xl">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Flight Booking -->
          <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-gray-100 hover:border-primary/20">
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition"></div>
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-primary/20 transition">
              <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" 
                  d="M5 12h14M12 5l7 7-7 7"></path>
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Smart Flight Booking</h3>
            <p class="text-gray-600 mb-5 leading-relaxed">
              Compare 500+ airlines in real-time. Find the best price, fastest route, or most sustainable option — all in seconds.
            </p>
            <ul class="space-y-2 text-sm text-gray-500">
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Price prediction AI</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Flexible date search</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Multi-city itineraries</li>
            </ul>
          </div>

          <!-- Personalized Itineraries -->
          <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-gray-100 hover:border-primary/20">
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition"></div>
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-primary/20 transition">
              <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" 
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Tailored Itineraries</h3>
            <p class="text-gray-600 mb-5 leading-relaxed">
              Your trip, your way. Build custom multi-stop journeys with intelligent layover suggestions and hidden gems.
            </p>
            <ul class="space-y-2 text-sm text-gray-500">
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Stopover explorer</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Local experience add-ons</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> 24/7 trip support</li>
            </ul>
          </div>

          <!-- Premium Concierge -->
          <div class="group relative bg-gradient-to-br from-primary to-primary/80 rounded-3xl p-8 shadow-xl text-white lg:col-span-1 md:col-span-2 lg:row-span-2">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-sm rounded-3xl"></div>
            <div class="relative z-10">
              <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
              </div>
              <h3 class="text-2xl font-bold mb-3">Jetze Concierge</h3>
              <p class="mb-5 leading-relaxed opacity-90">
                VIP treatment from takeoff to touchdown. For those who expect more.
              </p>
              <ul class="space-y-3 text-sm opacity-80">
                <li class="flex items-center"><span class="w-2 h-2 bg-white rounded-full mr-3"></span> Dedicated travel advisor</li>
                <li class="flex items-center"><span class="w-2 h-2 bg-white rounded-full mr-3"></span> Lounge access & fast-track</li>
                <li class="flex items-center"><span class="w-2 h-2 bg-white rounded-full mr-3"></span> Private transfers</li>
                <li class="flex items-center"><span class="w-2 h-2 bg-white rounded-full mr-3"></span> Last-minute upgrades</li>
              </ul>
              <button class="mt-8 bg-white text-primary px-6 py-3 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition">
                Explore Concierge
              </button>
            </div>
          </div>

          <!-- Eco-Conscious Travel -->
          <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-gray-100 hover:border-primary/20">
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition"></div>
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-primary/20 transition">
              <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" 
                  d="M12 14l9-5-9-5-9 5 9 5z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" 
                  d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.825-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path>
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Green Skies Initiative</h3>
            <p class="text-gray-600 mb-5 leading-relaxed">
              Fly responsibly. Offset carbon, choose eco-friendly routes, and support sustainable aviation.
            </p>
            <ul class="space-y-2 text-sm text-gray-500">
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Verified carbon offsets</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Lower-emission flight tags</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Tree-planting partnership</li>
            </ul>
          </div>

          <!-- Business Travel Hub -->
          <div class="group relative bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all duration-500 border border-gray-100 hover:border-primary/20">
            <div class="absolute -top-4 -right-4 w-20 h-20 bg-primary/10 rounded-full blur-xl group-hover:bg-primary/20 transition"></div>
            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-primary/20 transition">
              <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" 
                  d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6v.01M12 6v.01M8 6v.01M3 15.5V19a1 1 0 001 1h16a1 1 0 001-1v-3.5"></path>
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Corporate Travel Suite</h3>
            <p class="text-gray-600 mb-5 leading-relaxed">
              Streamline business trips with policy compliance, reporting, and dedicated account managers.
            </p>
            <ul class="space-y-2 text-sm text-gray-500">
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Expense tracking</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> Approval workflows</li>
              <li class="flex items-center"><span class="w-1.5 h-1.5 bg-primary rounded-full mr-2"></span> 24/7 priority support</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- How It Works -->
    <section class="px-6 py-16 md:py-24 bg-gray-50">
      <div class="mx-auto max-w-7xl">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">How Jetze Works</h2>
          <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Three simple steps to your perfect flight
          </p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
          <div class="text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-primary/10 rounded-full flex items-center justify-center">
              <div class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold">1</div>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Search & Compare</h3>
            <p class="text-gray-600">Enter your trip details. Our AI scans millions of options instantly.</p>
          </div>
          <div class="text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-primary/10 rounded-full flex items-center justify-center">
              <div class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold">2</div>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Customize & Book</h3>
            <p class="text-gray-600">Add extras, choose seats, and secure with one-click checkout.</p>
          </div>
          <div class="text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-primary/10 rounded-full flex items-center justify-center">
              <div class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold">3</div>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">Fly with Confidence</h3>
            <p class="text-gray-600">Get real-time updates, digital boarding, and support anytime.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Trust & Security -->
    <section class="px-6 py-16 md:py-24">
      <div class="mx-auto max-w-7xl">
        <div class="grid md:grid-cols-2 gap-12 items-center">
          <div>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
              Trust Built on <span class="text-primary">Security</span>
            </h2>
            <p class="text-lg text-gray-600 mb-6 leading-relaxed">
              Your data and payments are protected with bank-grade encryption and PCI-DSS compliance.
            </p>
            <div class="space-y-4">
              <div class="flex items-start">
                <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                  </svg>
                </div>
                <div class="ml-4">
                  <h4 class="font-semibold text-gray-900">256-bit SSL Encryption</h4>
                  <p class="text-gray-600 text-sm">All transactions secured end-to-end</p>
                </div>
              </div>
              <div class="flex items-start">
                <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                  </svg>
                </div>
                <div class="ml-4">
                  <h4 class="font-semibold text-gray-900">Fraud Protection</h4>
                  <p class="text-gray-600 text-sm">AI-powered monitoring 24/7</p>
                </div>
              </div>
            </div>
          </div>
          <div class="relative">
            <div class="bg-gradient-to-tr from-primary to-primary/70 rounded-3xl p-1">
              <div class="bg-white rounded-3xl p-10 h-full flex flex-col items-center justify-center text-center">
                <div class="w-32 h-32 mx-auto mb-6 bg-primary/10 rounded-full flex items-center justify-center">
                  <svg class="w-16 h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <p class="text-2xl font-bold text-gray-900 mb-2">100% Secure</p>
                <p class="text-gray-600">Trusted by 2M+ travelers worldwide</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="px-6 py-16 md:py-24 bg-primary text-white">
      <div class="mx-auto max-w-4xl text-center">
        <h2 class="text-4xl md:text-5xl font-bold mb-6">
          Experience Travel, <span class="underline decoration-white/50">Reimagined</span>
        </h2>
        <p class="text-xl mb-8 opacity-90">
          Join the future of flight booking — simple, smart, and uniquely yours.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <button class="bg-white text-primary px-8 py-4 rounded-full text-lg font-semibold hover:shadow-xl transform hover:scale-105 transition duration-300">
            Start Booking Now
          </button>
          <button class="border-2 border-white text-white px-8 py-4 rounded-full text-lg font-semibold hover:bg-white/10 transition">
            View Demo
          </button>
        </div>
      </div>
    </section>
  </div>
</template>