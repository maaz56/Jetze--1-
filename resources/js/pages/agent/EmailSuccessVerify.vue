<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <!-- Success checkmark icon -->
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Email Verified!</h2>
          <p class="mt-2 text-sm text-gray-600">
            Your email has been successfully verified. You can now log in to your account.
          </p>
        </div>
        
        <div class="mt-8">
          <button 
            @click="goToLogin" 
            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200"
          >
            Go to Login
          </button>
        </div>
        
        <div class="text-center mt-4">
          <p class="text-xs text-gray-500">
            If you have any issues, please contact our support team.
          </p>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { useRouter } from 'vue-router';
  
  const router = useRouter();
  
  const goToLogin = () => {
    router.push('/auth/login');
  };
  </script>
  
  <style scoped>
  /* Any additional custom styles can go here */
  </style>