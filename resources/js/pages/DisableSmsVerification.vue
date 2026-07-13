<script setup>
import apiService from "@/config/axios";
import { onMounted, ref } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import { toast } from "vue3-toastify";

const authStore = useAuthStore();
const isLoading = ref(false);
const isDisabledForThisBrowser = ref(false);

function getBrowserId() {
    const storageKey = "browser_id";
    let browserId = localStorage.getItem(storageKey);

    if (!browserId) {
        browserId = `br_${Date.now()}_${Math.random().toString(36).slice(2, 12)}`;
        localStorage.setItem(storageKey, browserId);
    }

    return browserId;
}

async function fetchStatus() {
    isLoading.value = true;
    try {
        const browserId = getBrowserId();
        const response = await apiService.get("/sms-otp-browser-status", {
            headers: { "X-Browser-Id": browserId },
            params: { browser_id: browserId },
        });
        isDisabledForThisBrowser.value = !!response?.data?.data?.sms_otp_disabled;
    } catch (error) {
        toast.error("Failed to fetch SMS verification status.");
    } finally {
        isLoading.value = false;
    }
}

async function updateStatus(disable) {
    isLoading.value = true;
    try {
        const browserId = getBrowserId();
        await apiService.post(
            "/sms-otp-browser-status",
            { disable, browser_id: browserId },
            { headers: { "X-Browser-Id": browserId } }
        );
        isDisabledForThisBrowser.value = disable;
        await authStore.fetchUser();
        toast.success(disable ? "SMS verification disabled for this browser." : "SMS verification enabled again.");
    } catch (error) {
        toast.error(error?.response?.data?.message || "Failed to update SMS verification.");
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchStatus();
});
</script>

<template>
    <div class="p-6 md:p-10 bg-gray-50 min-h-[70vh]">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-4xl font-bold text-gray-800 mb-6">Disabling SMS Verification</h1>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-3xl font-semibold text-gray-700">Disabling SMS verification includes</h2>
                </div>

                <div class="p-6">
                    <ol class="list-decimal pl-6 text-gray-700 space-y-2">
                        <li>SMS verification will be disabled when logging into the system</li>
                        <li>SMS verification will be disabled for this browser only</li>
                        <li>When using or upgrading the browser, you may need to re-disable SMS verification.</li>
                    </ol>

                    <div
                        class="mt-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-lg border px-4 py-3"
                        :class="isDisabledForThisBrowser ? 'bg-blue-50 border-blue-300' : 'bg-amber-50 border-amber-300'"
                    >
                        <p class="text-sm md:text-base font-semibold"
                            :class="isDisabledForThisBrowser ? 'text-blue-700' : 'text-amber-700'">
                            <span v-if="isDisabledForThisBrowser">
                                The SMS verification service has been disabled, and this browser has been added as a verified browser.
                            </span>
                            <span v-else>
                                SMS verification is currently enabled for this browser.
                            </span>
                        </p>

                        <button
                            :disabled="isLoading"
                            @click="updateStatus(!isDisabledForThisBrowser)"
                            class="text-sm md:text-base font-medium whitespace-nowrap disabled:opacity-60"
                            :class="isDisabledForThisBrowser ? 'text-emerald-700' : 'text-blue-700'"
                        >
                            {{ isDisabledForThisBrowser ? "Re-enable SMS Verification Service" : "Disable SMS Verification Service" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
