<template>
    <div class="flex items-center justify-center p-4">
      <div class="max-w-md w-full bg-white shadow-2xl rounded-lg overflow-hidden transform hover:scale-105 transition-all duration-300">
        <div class="p-6 sm:p-8">
          <div class="flex items-center space-x-3 mb-6">
            <div class="relative">
              <AlertCircleIcon class="h-8 w-8 text-yellow-500" />
              <span class="absolute top-0 right-0 h-3 w-3 bg-red-500 rounded-full animate-ping"></span>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Account Approval Required</h2>
          </div>
          <p class="text-gray-600 leading-relaxed mb-6">
            Please contact the admin to approve your account. We're here to assist you through the process.
          </p>
          <div class="space-y-4">
            <div class="flex items-center space-x-3 text-gray-700">
              <MailIcon class="h-5 w-5 text-gray-500" />
              <a href="mailto:admin@example.com" class="hover:text-gray-500 transition-colors duration-300">admin@example.com</a>
            </div>
            <div class="flex items-center space-x-3 text-gray-700">
              <PhoneIcon class="h-5 w-5 text-gray-500" />
              <a href="tel:+1234567890" class="hover:text-gray-500 transition-colors duration-300">+1 (234) 567-890</a>
            </div>
            <div class="flex items-center space-x-3 text-gray-700">
              <ClockIcon class="h-5 w-5 text-purple-500" />
              <span>Mon-Fri, 9:00 AM - 5:00 PM EST</span>
            </div>
          </div>
          <div class="mt-8">
            <button class="w-full bg-gradient-to-r from-primary to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
              Contact Admin
            </button>
          </div>
        </div>
        <div class="bg-gray-50 px-6 py-4 sm:px-8 sm:py-6 border-t border-gray-100">
          <p class="text-sm text-gray-500 text-center">
            Thank you for your patience. We'll process your approval as quickly as possible.
          </p>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { AlertCircleIcon, MailIcon, PhoneIcon, ClockIcon } from 'lucide-vue-next'
  </script>
  
  <style scoped>
  @keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }
  
  .bg-gradient-to-br {
    background-size: 200% 200%;
    animation: gradient 15s ease infinite;
  }
  
  .animate-ping {
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
  }
  
  @keyframes ping {
    75%, 100% {
      transform: scale(2);
      opacity: 0;
    }
  }
  </style>