<script setup>
import { 
    ShieldCheck, 
    Zap, 
    Headphones, 
    BadgePercent, 
    ArrowRight, 
    CheckCircle2, 
    Lock, 
    Globe2, 
    Clock, 
    Award 
} from 'lucide-vue-next';

const features = [
    {
        id: 1,
        title: 'Best Fare Guarantee',
        description: 'Multi-GDS live aggregator tracking hundreds of airlines to offer unbeatable wholesale and published rates.',
        icon: BadgePercent,
        badge: 'Lowest Prices',
        iconBg: 'bg-blue-50 text-blue-600',
        badgeBg: 'bg-blue-100 text-blue-700'
    },
    {
        id: 2,
        title: 'Instant E-Ticketing',
        description: 'Confirmed PNR and e-tickets dispatched immediately to your email and WhatsApp within seconds of checkout.',
        icon: Zap,
        badge: 'Real-Time',
        iconBg: 'bg-orange-50 text-orange-600',
        badgeBg: 'bg-orange-100 text-orange-700'
    },
    {
        id: 3,
        title: '24/7 VIP Concierge Support',
        description: 'Dedicated travel consultants on standby round-the-clock for flight changes, meal requests, and assistance.',
        icon: Headphones,
        badge: 'Always Here',
        iconBg: 'bg-purple-50 text-purple-600',
        badgeBg: 'bg-purple-100 text-purple-700'
    },
    {
        id: 4,
        title: 'Flexible & Secure Booking',
        description: 'Bank-grade 256-bit SSL checkout with transparent cancellation terms and swift refund handling.',
        icon: ShieldCheck,
        badge: '100% Protected',
        iconBg: 'bg-emerald-50 text-emerald-600',
        badgeBg: 'bg-emerald-100 text-emerald-700'
    }
];

const stats = [
    { value: '500K+', label: 'Happy Passengers', icon: Globe2 },
    { value: '450+', label: 'Airline Partners', icon: Award },
    { value: '99.9%', label: 'Ticketing Reliability', icon: CheckCircle2 },
    { value: '24/7', label: 'Concierge Assistance', icon: Clock }
];
</script>

<template>
    <section class="w-full py-14 sm:py-18 bg-white px-4 sm:px-6 lg:px-8 border-b border-border/70">
        <div class="max-w-7xl mx-auto">
            
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold uppercase tracking-wider mb-2">
                    <Lock class="w-3.5 h-3.5 text-orange-500" />
                    The Jetze Difference
                </div>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">
                    Why Millions of Travelers <span class="text-blue-600">Trust Jetze</span>
                </h2>
                <p class="mt-2 text-sm sm:text-base text-gray-600 font-normal leading-relaxed">
                    Experience state-of-the-art booking technology backed by dedicated travel specialists, transparent pricing, and instant confirmations worldwide.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
                <div 
                    v-for="feature in features" 
                    :key="feature.id"
                    class="group relative bg-slate-50/70 rounded-lg p-5 sm:p-6 border border-gray-200 hover:border-primary/60 hover:bg-white shadow-xs hover:shadow-md transition-all duration-200 flex flex-col justify-between"
                >
                    <div>
                        <!-- Icon & Badge -->
                        <div class="flex items-center justify-between mb-5">
                            <div 
                                class="w-11 h-11 rounded flex items-center justify-center transition-transform group-hover:scale-105"
                                :class="feature.iconBg"
                            >
                                <component :is="feature.icon" class="w-5 h-5" />
                            </div>
                            <span 
                                class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                                :class="feature.badgeBg"
                            >
                                {{ feature.badge }}
                            </span>
                        </div>

                        <!-- Text Content -->
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors">
                            {{ feature.title }}
                        </h3>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                            {{ feature.description }}
                        </p>
                    </div>

                    <!-- Subtle bottom indicator -->
                    <div class="mt-5 pt-3 border-t border-gray-200/80 flex items-center text-xs font-semibold text-primary">
                        <span>Learn more</span>
                        <ArrowRight class="w-3.5 h-3.5 ml-1 transition-transform group-hover:translate-x-1" />
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="mt-12 sm:mt-14 rounded-lg bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 p-6 sm:p-8 text-white shadow-sm border border-blue-800">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center divide-y sm:divide-y-0 sm:divide-x divide-white/10">
                    <div 
                        v-for="(stat, idx) in stats" 
                        :key="idx"
                        class="flex flex-col items-center justify-center p-2"
                    >
                        <component :is="stat.icon" class="w-5 h-5 text-orange-400 mb-1.5" />
                        <div class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight">
                            {{ stat.value }}
                        </div>
                        <div class="text-[11px] sm:text-xs font-medium text-gray-300 mt-1 uppercase tracking-wider">
                            {{ stat.label }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</template>
