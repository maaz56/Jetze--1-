<script setup>
import { ref, watch } from "vue";
const emit = defineEmits(["close"]);
const closeModal = () => {
    emit("close");
};
</script>
<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50"
    >
        <div
            class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white"
        >
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Deposit Details
                </h3>
                <div class="mt-2 px-7 py-3">
                    <div v-if="deposit" class="text-left">
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">ID</p>
                            <p class="text-lg font-semibold"></p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">Date</p>
                            <p class="text-lg font-semibold"></p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">Payment Type</p>
                            <p class="text-lg font-semibold">
                                <span> </span>
                            </p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">
                                Additional Details
                            </p>
                            <p class="text-lg font-semibold"></p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="text-lg font-semibold">
                                <span> </span>
                            </p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">Amount</p>
                            <p class="text-lg font-semibold"></p>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500">
                        No deposit details available.
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <button
                        class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
