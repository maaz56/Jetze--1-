<template>
    <div
        class="min-h-screen bg-gradient-to-r from-gray-100 to-purple-100 flex items-center justify-center p-4"
    >
        <div
            class="max-w-md w-full bg-white shadow-2xl rounded-lg overflow-hidden"
        >
            <div class="p-8">
                <div class="text-center mb-8">
                    <div
                        class="inline-block p-3 rounded-full bg-gray-100 mb-4"
                    >
                        <MailIcon class="h-12 w-12 text-gray-500" />
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        Check Your Email
                    </h1>
                    <p class="text-gray-600">
                        We've sent a verification link to your email address.
                    </p>
                </div>

                <div class="space-y-6">
                    <div
                        class="bg-gray-50 border-l-4 border-gray-500 p-4 rounded"
                    >
                        <div class="flex items-center space-x-3">
                            <InfoIcon class="h-5 w-5 text-gray-500" />
                            <p class="text-gray-700">
                                Please check your inbox and click the
                                verification link to activate your account.
                            </p>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-gray-600 mb-2">
                            Didn't receive the email?
                        </p>
                        <button
                            @click="resendEmail"
                            class="text-gray-500 hover:text-gray-700 font-semibold transition duration-300"
                        >
                            Resend verification email
                        </button>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Or</span>
                        </div>
                    </div>

                    <div class="text-center">
                        <router-link :to="{ name: 'Login' }" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded transition duration-300">
                            Return to Login
                    </router-link>
                        
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100">
                <p class="text-sm text-gray-500 text-center">
                    Need help?
                    <a
                        href="#"
                        class="text-gray-500 hover:text-gray-700 font-semibold"
                        >Contact Support</a
                    >
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { MailIcon, InfoIcon } from "lucide-vue-next";
import { ref } from "vue";

const resendEmail = () => {
    // Implement the logic to resend the verification email
    //console.log("Resending verification email...");
    // You can add actual API call here
};
</script>

<style scoped>
/* Add any additional component-specific styles here */
</style>
