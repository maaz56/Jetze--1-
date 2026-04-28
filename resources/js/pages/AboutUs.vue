<script setup>
import { ref } from 'vue';

const companyInfo = ref({
  name: 'Jetze',
  founded: '2019',
  tagline: 'Book Flights Smarter. Fly Cheaper.',
  description:
    'APNTicket is a leading flight booking platform that helps millions of travelers find the best deals instantly. We search hundreds of airlines and travel sites to bring you the lowest prices with zero hidden fees.',
  mission:
    'To make flight booking fast, transparent, and affordable for everyone — whether you’re traveling for business, family, or adventure.',
});
</script>

<template>
  <div class="min-h-screen bg-gray-50 font-sans">
    <!-- Hero Section -->
    <section class="relative bg-cover bg-center bg-[url('https://wallpapers.com/images/hd/plane-desktop-yms31u8wyuke7ari.jpg')] text-white overflow-hidden">
      <div class="absolute inset-0 bg-black/30"></div>
      <div class="relative container mx-auto px-6 py-28 text-center">
        <h1 class="text-5xl md:text-6xl font-extrabold mb-6 leading-tight">
          About <span class="text-white">Jetze</span>
        </h1>
        <p class="text-xl md:text-2xl max-w-4xl mx-auto font-medium opacity-95 mb-8">
          {{ $t(companyInfo.tagline) }}
        </p>
        <p class="text-lg md:text-xl max-w-3xl mx-auto opacity-90 leading-relaxed">
          {{ $t(companyInfo.description) }}
        </p>
      </div>
    </section>

    <!-- Our Promise / Mission -->
    <section class="py-20 bg-white">
      <div class="container mx-auto px-6">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
            {{ $t('Your Trusted Flight Partner') }}
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
            {{ $t(companyInfo.mission) }}
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-10 mt-16">
          <div class="bg-gray-50 rounded-2xl p-10 text-center border border-gray-200">
            <div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $t('No Hidden Fees') }}</h3>
            <p class="text-gray-600">{{ $t('The price you see is the price you pay. Always.') }}</p>
          </div>

          <div class="bg-gray-50 rounded-2xl p-10 text-center border border-gray-200">
            <div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $t('Instant Booking') }}</h3>
            <p class="text-gray-600">{{ $t('Get confirmed tickets in seconds, not hours.') }}</p>
          </div>

          <div class="bg-gray-50 rounded-2xl p-10 text-center border border-gray-200">
            <div class="w-20 h-20 bg-primary rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">{{ $t('Best Price Finder') }}</h3>
            <p class="text-gray-600">{{ $t('We compare 700+ airlines to get you the lowest fares.') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats / Trust Section (Squared Cards) -->
    <section class="py-20 bg-gray-100">
      <div class="container mx-auto px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
          <div class="text-center">
            <div class="text-5xl font-extrabold text-primary mb-2">5M+</div>
            <p class="text-gray-700 font-semibold">{{ $t('Tickets Booked') }}</p>
          </div>
          <div class="text-center">
            <div class="text-5xl font-extrabold text-primary mb-2">700+</div>
            <p class="text-gray-700 font-semibold">{{ $t('Airlines') }}</p>
          </div>
          <div class="text-center">
            <div class="text-5xl font-extrabold text-primary mb-2">190+</div>
            <p class="text-gray-700 font-semibold">{{ $t('Countries') }}</p>
          </div>
          <div class="text-center">
            <div class="text-5xl font-extrabold text-primary mb-2">24/7</div>
            <p class="text-gray-700 font-semibold">{{ $t('Support') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Why Choose APNTicket -->
    <section class="py-20 bg-white">
      <div class="container mx-auto px-6 text-center">
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-12">
          {{ $t('Why Millions Choose APNTicket') }}
        </h2>
        <div class="grid md:grid-cols-3 gap-10 max-w-5xl mx-auto">
          <div class="bg-primary/5 rounded-2xl p-8 border border-primary/10">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Real-Time Deals') }}</h3>
            <p class="text-gray-600">{{ $t('Live price tracking across all major airlines and OTAs.') }}</p>
          </div>
          <div class="bg-primary/5 rounded-2xl p-8 border border-primary/10">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Secure & Fast') }}</h3>
            <p class="text-gray-600">{{ $t('Bank-level encryption. Instant e-tickets delivered to your inbox.') }}</p>
          </div>
          <div class="bg-primary/5 rounded-2xl p-8 border border-primary/10">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Flexible Options') }}</h3>
            <p class="text-gray-600">{{ $t('Free date changes on most bookings. Easy cancellations.') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="bg-primary text-white py-20">
      <div class="container mx-auto px-6 text-center">
        <h2 class="text-4xl md:text-5xl font-bold mb-6">
          {{ $t('Ready to Book Your Next Flight?') }}
        </h2>
        <p class="text-xl mb-10 max-w-2xl mx-auto opacity-90">
          {{ $t('Join millions of smart travelers who save time and money with APNTicket.') }}
        </p>
        <a
          href="/"
          class="inline-block bg-white text-primary font-bold text-xl py-5 px-12 rounded-full hover:bg-gray-100 transform hover:scale-105 transition-all duration-300 shadow-xl"
        >
          {{ $t('Search Flights Now') }}
        </a>
      </div>
    </section>
  </div>
</template>

<style scoped>

</style>