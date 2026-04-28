<script setup>
import { computed, defineProps, onUnmounted, ref, watch } from 'vue';

import { useAuthStore } from '@/services/stores/auth';
import { toast } from 'vue3-toastify';

const authStore = useAuthStore();
const props = defineProps({ showOtpInput: Boolean, userDetails: Object });
const otp = ref('');
const isLoading = computed(() => authStore.isLoading);
const validationMessages = computed(() => authStore.validationMessages);
const resendCooldownSeconds = ref(0);
let resendTimer = null;

const clearResendTimer = () => {
    if (resendTimer) {
        clearInterval(resendTimer);
        resendTimer = null;
    }
};

const startResendCooldown = (seconds = 60) => {
    clearResendTimer();
    resendCooldownSeconds.value = Math.max(0, seconds);

    resendTimer = setInterval(() => {
        resendCooldownSeconds.value = Math.max(0, resendCooldownSeconds.value - 1);
        if (resendCooldownSeconds.value <= 0) {
            clearResendTimer();
        }
    }, 1000);
};

const isResendDisabled = computed(() => isLoading.value || resendCooldownSeconds.value > 0);

const resendCountdownLabel = computed(() => {
    const minutes = Math.floor(resendCooldownSeconds.value / 60);
    const seconds = resendCooldownSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

async function verifyOtp() {
    try {
        const response = await authStore.verifyLoginOtp({
            email: props.userDetails.email,
            otp: otp.value,
        });
        if (response.status == 200) {
            toast.success(response.message?.description || 'Login successful');
        }
    } catch (error) {
        console.error('Error verifying OTP:', error);
        const errorMessage = error.response?.data?.message?.description || 'Invalid OTP. Please try again.';
        toast.error(errorMessage);
    }
}

async function resendOtp() {
    if (isResendDisabled.value) return;

    try {
        const response = await authStore.requestLoginOtp(props.userDetails);
        if (response.success) {
            toast.success(response.message?.description || 'OTP has been resent to your email');
            startResendCooldown(60);
        }
    } catch (error) {
        console.error('Error resending OTP:', error);
        const errorMessage = error.response?.data?.message?.description || 'Failed to resend OTP. Please try again.';
        toast.error(errorMessage);
    }
}

watch(
    () => props.showOtpInput,
    (isOpen) => {
        if (isOpen) {
            startResendCooldown(60);
        } else {
            clearResendTimer();
            resendCooldownSeconds.value = 0;
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    clearResendTimer();
});
</script>

<template>
    <div v-if="props.showOtpInput">
        <form @submit.prevent="verifyOtp" class="">
            <div class="text-center mb-4">
                <p class="text-gray-600">Please enter the OTP sent to</p>
                <p class="font-medium text-gray-800">{{ props.userDetails.email }}</p>
            </div>

            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                    </svg>
                </div>
                <input type="text" v-model="otp" placeholder="Enter OTP sent to your email"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    required maxlength="6" />
            </div>
            <p v-if="validationMessages?.otp" class="text-red-500 text-xs mt-1">
                {{ validationMessages.otp }}
            </p>
            <div class="mt-4">
                <button type="submit" :disabled="isLoading"
                    class="w-full bg-primary hover:primary/50 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center disabled:opacity-50 shadow-lg">
                    <span v-if="!isLoading">
                        VERIFY OTP
                    </span>
                    <span v-else>Processing...</span>
                    <svg v-if="!isLoading" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-600 mt-2">
                Didn't receive the code?
                <button type="button" @click="resendOtp" :disabled="isResendDisabled" class="font-medium"
                    :class="isResendDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-primary'">
                    {{ resendCooldownSeconds > 0 ? `Resend OTP in ${resendCountdownLabel}` : 'Resend OTP' }}
                </button>
            </p>
        </form>
    </div>
</template>
