<script setup>
import { ref } from 'vue';
import { Mail, ArrowRight, ShieldCheck, Sparkles, CheckCircle2 } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';

const email = ref('');
const isSubmitting = ref(false);
const isSubscribed = ref(false);

const handleSubscribe = () => {
    if (!email.value || !email.value.includes('@')) {
        toast.error('Please enter a valid email address.');
        return;
    }

    isSubmitting.value = true;
    setTimeout(() => {
        isSubmitting.value = false;
        isSubscribed.value = true;
        toast.success('Welcome to Jetze Travel Club! Check your inbox for your welcome voucher.');
    }, 800);
};
</script>

<template>
    <section class="w-full py-14 sm:py-20 px-4 sm:px-6 lg:px-8 bg-slate-50/80">
        <div class="max-w-7xl mx-auto">
            <div class="relative overflow-hidden rounded-lg bg-gradient-to-br from-slate-900 via-[#0e213d] to-slate-900 text-white p-7 sm:p-12 lg:p-16 shadow-md border border-slate-700">
                
                <div class="relative z-10 max-w-3xl mx-auto text-center">
                    
                    <!-- Pill Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-900/60 border border-blue-600 text-blue-300 text-xs font-bold uppercase tracking-wider mb-4">
                        <Sparkles class="w-3.5 h-3.5 text-orange-400" />
                        Jetze Travel Club
                    </div>

                    <!-- Headings -->
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                        Unlock Secret Member Fares & <span class="text-orange-400">Bespoke Deals</span>
                    </h2>
                    
                    <p class="mt-3 text-sm sm:text-base text-gray-300 font-normal leading-relaxed">
                        Join over 250,000 discerning travelers who receive our confidential flash sales, complimentary flight upgrades, and seasonal package alerts every week.
                    </p>

                    <!-- Subscription Form -->
                    <div class="mt-7 sm:mt-8 max-w-xl mx-auto">
                        <div v-if="!isSubscribed" class="flex flex-col sm:flex-row items-center gap-2 p-1.5 bg-slate-800/90 border border-slate-600 rounded">
                            <div class="relative flex-1 w-full flex items-center pl-3">
                                <Mail class="w-4 h-4 text-gray-400 shrink-0" />
                                <input 
                                    v-model="email"
                                    type="email" 
                                    placeholder="Enter your personal email address..." 
                                    class="w-full py-2.5 px-3 bg-transparent text-white placeholder-gray-400 text-sm focus:outline-none border-none focus:ring-0"
                                    @keyup.enter="handleSubscribe"
                                />
                            </div>

                            <button 
                                @click="handleSubscribe"
                                :disabled="isSubmitting"
                                class="w-full sm:w-auto px-6 py-2.5 rounded bg-primary hover:bg-primary-dark text-white font-bold text-xs sm:text-sm tracking-wide flex items-center justify-center gap-2 transition-colors cursor-pointer shrink-0 disabled:opacity-70"
                            >
                                <span v-if="!isSubmitting">Subscribe</span>
                                <span v-else>Joining...</span>
                                <ArrowRight v-if="!isSubmitting" class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Success Message -->
                        <div v-else class="flex items-center justify-center gap-2 p-3.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-semibold text-sm">
                            <CheckCircle2 class="w-4 h-4" />
                            <span>You're subscribed! We've sent an exclusive voucher to your inbox.</span>
                        </div>
                    </div>

                    <!-- Trust Reassurance -->
                    <div class="mt-6 sm:mt-7 flex flex-wrap items-center justify-center gap-5 text-xs text-gray-400 font-medium">
                        <div class="flex items-center gap-1.5">
                            <ShieldCheck class="w-4 h-4 text-orange-400" />
                            <span>No Spam, Guaranteed</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                            <span>Unsubscribe Anytime</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <ShieldCheck class="w-4 h-4 text-blue-400" />
                            <span>256-Bit Data Privacy</span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>
</template>
