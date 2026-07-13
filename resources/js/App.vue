<script setup>
import { initFlowbite } from "flowbite";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import { useRouter } from "vue-router";

const router = useRouter();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const showEmailVerifyDialog = computed(
    () => user.value && !user.value.email_verified_at,
);
const resendCooldownSeconds = ref(0);
const isResending = ref(false);
const resendSuccessMessage = ref("");
const resendErrorMessage = ref("");
const RESEND_COOLDOWN_TOTAL = 120;
let cooldownInterval = null;

const cooldownStorageKey = computed(() => {
    const email = user.value?.email || "guest";
    return `verify_email_resend_cooldown:${email}`;
});

const formattedCooldown = computed(() => {
    const minutes = Math.floor(resendCooldownSeconds.value / 60);
    const seconds = resendCooldownSeconds.value % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});

const isCooldownActive = computed(() => resendCooldownSeconds.value > 0);

const resendButtonLabel = computed(() => {
    if (isResending.value) return "Sending...";
    if (isCooldownActive.value) return `Resend in ${formattedCooldown.value}`;
    return "Resend Email";
});

const clearCooldownTimer = () => {
    if (cooldownInterval) {
        clearInterval(cooldownInterval);
        cooldownInterval = null;
    }
};

const startResendCooldown = (seconds = RESEND_COOLDOWN_TOTAL) => {
    clearCooldownTimer();
    resendCooldownSeconds.value = Math.max(0, seconds);
    const expiresAt = Date.now() + resendCooldownSeconds.value * 1000;
    localStorage.setItem(cooldownStorageKey.value, String(expiresAt));

    cooldownInterval = setInterval(() => {
        resendCooldownSeconds.value = Math.max(0, resendCooldownSeconds.value - 1);

        if (resendCooldownSeconds.value <= 0) {
            clearCooldownTimer();
            localStorage.removeItem(cooldownStorageKey.value);
        }
    }, 1000);
};

const restoreCooldownFromStorage = () => {
    clearCooldownTimer();
    resendCooldownSeconds.value = 0;

    const expiresAtRaw = localStorage.getItem(cooldownStorageKey.value);
    const expiresAt = Number(expiresAtRaw);

    if (!Number.isFinite(expiresAt)) {
        localStorage.removeItem(cooldownStorageKey.value);
        return;
    }

    const remainingSeconds = Math.ceil((expiresAt - Date.now()) / 1000);
    if (remainingSeconds > 0) {
        startResendCooldown(remainingSeconds);
    } else {
        localStorage.removeItem(cooldownStorageKey.value);
    }
};

const resendVerificationEmail = async () => {
    if (isResending.value || isCooldownActive.value) return;

    try {
        isResending.value = true;
        resendSuccessMessage.value = "";
        resendErrorMessage.value = "";
        await authStore.verifyEmail();
        resendSuccessMessage.value =
            "Verification email sent successfully. You can resend after 2 minutes.";
        startResendCooldown(RESEND_COOLDOWN_TOTAL);
    } catch (error) {
        resendErrorMessage.value =
            error?.response?.data?.message ||
            "Unable to resend verification email right now. Please try again.";
    } finally {
        isResending.value = false;
    }
};

const handleCloseDialog = async () => {
    try {
        await authStore.logout();
    } catch (e) {
        console.error("Logout failed during dialog close", e);
    } finally {
        authStore.user = null;
        authStore.isLoggedIn = false;
        localStorage.removeItem("access_token");
        router.push({ name: "Home" });
    }
};

onMounted(() => {
    initFlowbite();
    restoreCooldownFromStorage();
});

watch(
    () => user.value?.email,
    () => {
        resendSuccessMessage.value = "";
        resendErrorMessage.value = "";
        restoreCooldownFromStorage();
    },
);

onUnmounted(() => {
    clearCooldownTimer();
});
</script>

<template>
    <!-- extending all layouts here -->
    <router-view></router-view>

    <div v-if="showEmailVerifyDialog" class="fixed inset-0 z-[9999]">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="relative z-[10000] flex h-full items-center justify-center px-4">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-2xl relative">
                <div class="bg-primary px-6 py-4 flex justify-between items-center">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.18em] text-primary-foreground/85">
                            ACCOUNT SECURITY
                        </p>
                        <h3 class="mt-1 text-lg font-semibold text-primary-foreground">
                            Email Verification Required
                        </h3>
                    </div>
                    <button @click="handleCloseDialog" class="text-primary-foreground/85 hover:text-primary-foreground hover:bg-white/10 rounded-full p-2 transition-all" title="Close & Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <div class="flex items-center gap-3">
                    <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
                    >
                        <span class="text-xl font-semibold">!</span>
                    </div>
                    <div>
                            <p class="text-sm text-muted-foreground">
                            Please verify your email to continue.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-border bg-muted p-4 text-sm text-foreground">
                        We’ve sent a verification link to
                        <span class="font-semibold">{{ user?.email }}</span>.
                        Check your inbox (and spam folder) and click the link to
                        activate your account.
                    </div>

                    <div
                        v-if="resendSuccessMessage"
                        class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                    >
                        {{ resendSuccessMessage }}
                    </div>

                    <div
                        v-if="resendErrorMessage"
                        class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                    >
                        {{ resendErrorMessage }}
                    </div>

                    <div
                        v-if="isCooldownActive"
                        class="mt-4 text-xs font-medium tracking-wide text-muted-foreground"
                    >
                        You can request another email in {{ formattedCooldown }}.
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-full px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-all"
                            @click="handleCloseDialog"
                        >
                            Cancel & Logout
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-5 py-2.5 text-sm font-semibold transition-all"
                            :class="[
                                isCooldownActive || isResending
                                    ? 'cursor-not-allowed bg-muted text-muted-foreground'
                                    : 'bg-primary text-primary-foreground hover:opacity-90',
                            ]"
                            :disabled="isCooldownActive || isResending"
                            @click="resendVerificationEmail"
                        >
                            {{ resendButtonLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
   
</template>
