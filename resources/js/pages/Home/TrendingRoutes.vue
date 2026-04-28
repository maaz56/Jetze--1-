<script setup>
import { MapPin, Plane, Star } from 'lucide-vue-next';

const destinations = [
    {
        id: 1,
        city: 'Dubai',
        country: 'UAE',
        rating: 4.9,
        price: '₹24,500',
        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600&h=400&fit=crop',
        tag: 'Best Seller'
    },
    {
        id: 2,
        city: 'Paris',
        country: 'France',
        rating: 4.8,
        price: '₹52,000',
        image: 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=600&h=400&fit=crop',
        tag: 'Romantic'
    },
    {
        id: 3,
        city: 'Goa',
        country: 'India',
        rating: 4.7,
        price: '₹5,200',
        image: 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?w=600&h=400&fit=crop',
        tag: 'Beach'
    },
    {
        id: 4,
        city: 'Singapore',
        country: 'Singapore',
        rating: 4.9,
        price: '₹18,900',
        image: 'https://images.unsplash.com/photo-1525625232717-121045198897?w=600&h=400&fit=crop',
        tag: 'Family'
    }
];
</script>

<template>
    <section class="w-full bg-[#f2f2f2] py-16 px-4">
        <div class="max-w-7xl mx-auto">
            
            <div class="mb-10">
                <h2 class="text-4xl lg:text-5xl font-black text-[#1a1a1a] mb-4">
                    Trending Destinations
                </h2>
                <p class="text-lg text-gray-600 font-medium max-w-2xl">
                    Most loved places by fellow travelers this month. Explore the world's top-rated cities and get the best deals.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <div 
                    v-for="place in destinations" 
                    :key="place.id"
                    class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col"
                >
                    <div class="relative h-56 overflow-hidden">
                        <img 
                            :src="place.image" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                            :alt="place.city"
                        >
                        <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-sm text-[#008cff] text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-sm">
                            {{ place.tag }}
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-1">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-2xl font-black text-gray-900 leading-tight">
                                {{ place.city }}
                            </h3>
                            <div class="flex items-center gap-1 text-yellow-500 font-bold text-sm">
                                <Star class="w-4 h-4 fill-current" />
                                {{ place.rating }}
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-1 text-gray-500 text-sm font-bold uppercase tracking-wide mb-6">
                            <MapPin class="w-3.5 h-3.5" />
                            {{ place.country }}
                        </div>

                        <div class="mt-auto pt-4 border-t border-gray-50 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-gray-400 font-bold block uppercase tracking-tighter">Starting from</span>
                                <span class="text-xl font-black text-gray-900">{{ place.price }}</span>
                            </div>
                            <button class="bg-[#008cff] text-white p-3 rounded-xl hover:bg-[#0070cc] transition-colors shadow-lg shadow-blue-100">
                                <Plane class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-center gap-4 bg-blue-50 p-6 rounded-2xl border border-blue-100">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-[#008cff] shadow-sm">
                        <Star class="w-6 h-6 fill-current" />
                    </div>
                    <div>
                        <h4 class="font-black text-[#1a1a1a] uppercase text-sm tracking-wide">Premium Quality</h4>
                        <p class="text-xs text-gray-500 font-medium">Handpicked stays and routes</p>
                    </div>
                </div>
                </div>
        </div>
    </section>
</template>

<style scoped>
/* Ensuring consistent layout behavior */
.grid {
    display: grid;
}
</style>