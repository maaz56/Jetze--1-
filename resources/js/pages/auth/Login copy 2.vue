<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-emerald-100 flex items-center justify-center p-4 sm:p-6 lg:p-8 overflow-hidden">
      <!-- Decorative elements -->
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <!-- Floating circles -->
        <div class="absolute top-[15%] left-[10%] w-64 h-64 rounded-full bg-gray-300/20 blur-3xl"></div>
        <div class="absolute bottom-[20%] right-[15%] w-80 h-80 rounded-full bg-emerald-400/20 blur-3xl"></div>
        
        <!-- Geometric shapes -->
        <div class="absolute top-[10%] right-[20%] w-20 h-20 rotate-45 bg-gray-400/10 rounded-xl"></div>
        <div class="absolute bottom-[15%] left-[25%] w-32 h-32 rotate-12 bg-emerald-500/10 rounded-xl"></div>
        
        <!-- Leaf patterns -->
        <svg class="absolute top-10 left-10 text-gray-600/10 w-40 h-40" viewBox="0 0 24 24" fill="currentColor">
          <path d="M21,4c0,0-6.667-3-10,3c-3.333,6,1,12,1,12s6.667-7,9-9S21,4,21,4z"/>
          <path d="M13,22c0,0-6.667-5-7-13c-0.333-8,8-7,8-7s-5.667,4-5,9S13,22,13,22z"/>
        </svg>
        <svg class="absolute bottom-10 right-10 text-gray-600/10 w-40 h-40 rotate-180" viewBox="0 0 24 24" fill="currentColor">
          <path d="M21,4c0,0-6.667-3-10,3c-3.333,6,1,12,1,12s6.667-7,9-9S21,4,21,4z"/>
          <path d="M13,22c0,0-6.667-5-7-13c-0.333-8,8-7,8-7s-5.667,4-5,9S13,22,13,22z"/>
        </svg>
      </div>
      
      <!-- Login card -->
      <div class="relative w-full max-w-md">
        <!-- Card with cutout shape -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
          <div class="relative">
            <!-- gray wave shape at top -->
            <div class="absolute -top-5 left-0 right-0 h-40 bg-gray-500 rounded-b-[40%] transform -rotate-2 scale-110"></div>
            <div class="absolute -top-5 left-0 right-0 h-40 bg-gray-600 rounded-b-[30%]"></div>
            
            <!-- Logo and welcome text -->
            <div class="relative pt-12 px-8 text-center">
              <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-white shadow-md mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <h2 class="text-2xl font-bold text-white">Welcome Back</h2>
              <p class="text-gray-100 text-sm mt-1">Sign in to continue your journey</p>
            </div>
          </div>
          
          <!-- Login form -->
          <div class="px-8 pt-16 pb-8">
            <form @submit.prevent="handleLogin()" class="space-y-5">
              <!-- Email field with floating label -->
              <div class="relative">
                <Input
                  v-model="email"
                  id="email"
                  type="email"
                  placeholder=" "
                  class="peer block w-full rounded-lg border-gray-300 bg-gray-50 px-4 pt-5 pb-2 text-gray-900 focus:border-gray-500 focus:ring-gray-500"
                  required
                />
                <Label 
                  for="email" 
                  class="absolute left-4 top-2 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-xs"
                >
                  Email address
                </Label>
                <InputMessage v-if="validationMessages?.email" class="mt-1 text-red-500 text-xs">
                  {{ validationMessages.email }}
                </InputMessage>
              </div>
              
              <!-- Password field with floating label -->
              <div class="relative">
                <Input
                  v-model="password"
                  id="password"
                  type="password"
                  placeholder=" "
                  class="peer block w-full rounded-lg border-gray-300 bg-gray-50 px-4 pt-5 pb-2 text-gray-900 focus:border-gray-500 focus:ring-gray-500"
                  required
                />
                <Label 
                  for="password" 
                  class="absolute left-4 top-2 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-base peer-focus:top-2 peer-focus:text-xs"
                >
                  Password
                </Label>
                <InputMessage v-if="validationMessages?.password" class="mt-1 text-red-500 text-xs">
                  {{ validationMessages.password }}
                </InputMessage>
              </div>
              
              <!-- Remember me and forgot password -->
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <input
                    id="remember-me"
                    name="remember-me"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-gray-600 focus:ring-gray-500"
                  />
                  <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                </div>
                <router-link
                  :to="{ name: 'ForgotPassword' }"
                  class="text-sm font-medium text-gray-600 hover:text-gray-500"
                >
                  Forgot password?
                </router-link>
              </div>
              
              <!-- Login button -->
              <Button
                type="submit"
                :isLoading="isLoading"
                class="relative w-full overflow-hidden group"
              >
                <span class="absolute inset-0 w-full h-full transition duration-300 ease-out opacity-0 bg-gradient-to-r from-gray-600 via-gray-500 to-gray-600 group-hover:opacity-100"></span>
                <span class="relative flex w-full justify-center rounded-lg bg-gray-600 px-4 py-3 text-sm font-semibold text-white">
                  Sign in
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                  </svg>
                </span>
              </Button>
            </form>
            
            <!-- Social login -->
            <div class="mt-6">
              <div class="relative">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="bg-white px-2 text-gray-500">Or continue with</span>
                </div>
              </div>
              
              <div class="mt-4 grid grid-cols-3 gap-3">
                <button class="flex justify-center items-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 hover:bg-gray-50">
                  <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                  </svg>
                </button>
                <button class="flex justify-center items-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 hover:bg-gray-50">
                  <svg class="h-5 w-5 text-[#1DA1F2]" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M22.46,6c-0.77,0.35-1.6,0.58-2.46,0.69c0.88-0.53,1.56-1.37,1.88-2.38c-0.83,0.5-1.75,0.85-2.72,1.05C18.37,4.5,17.26,4,16,4c-2.35,0-4.27,1.92-4.27,4.29c0,0.34,0.04,0.67,0.11,0.98C8.28,9.09,5.11,7.38,3,4.79c-0.37,0.63-0.58,1.37-0.58,2.15c0,1.49,0.75,2.81,1.91,3.56c-0.71,0-1.37-0.2-1.95-0.5c0,0.02,0,0.03,0,0.05c0,2.08,1.48,3.82,3.44,4.21c-0.36,0.1-0.74,0.15-1.13,0.15c-0.27,0-0.54-0.03-0.8-0.08c0.54,1.69,2.11,2.95,3.98,2.98c-1.46,1.16-3.31,1.84-5.33,1.84c-0.34,0-0.68-0.02-1.02-0.06C3.44,20.29,5.7,21,8.12,21C16,21,20.33,14.46,20.33,8.79c0-0.19,0-0.37-0.01-0.56C21.17,7.65,21.88,6.87,22.46,6z"/>
                  </svg>
                </button>
                <button class="flex justify-center items-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 hover:bg-gray-50">
                  <svg class="h-5 w-5 text-[#4267B2]" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20.9,2H3.1C2.5,2,2,2.5,2,3.1v17.8C2,21.5,2.5,22,3.1,22h9.6v-7.7h-2.6v-3h2.6V9.4c0-2.6,1.6-4,3.9-4c1.1,0,2.1,0.1,2.3,0.1v2.7h-1.6c-1.3,0-1.5,0.6-1.5,1.5v1.9h3l-0.4,3h-2.6V22h5.1c0.6,0,1.1-0.5,1.1-1.1V3.1C22,2.5,21.5,2,20.9,2z"/>
                  </svg>
                </button>
              </div>
            </div>
            
            <!-- Sign up link -->
            <p class="mt-8 text-center text-sm text-gray-600">
              Don't have an account?
              <router-link :to="{ name: 'Register' }" class="font-medium text-gray-600 hover:text-gray-500">
                Create an account
              </router-link>
            </p>
          </div>
        </div>
        
        <!-- Decorative leaf at bottom -->
        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-gray-500 rounded-full flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { Button } from "@/components/ui/button";
  import { Input } from "@/components/ui/input";
  import InputMessage from "@/components/ui/inputMessage.vue";
  import { Label } from "@/components/ui/label";
  import { useAuthStore } from "@/services/stores/auth";
  import { computed, ref } from "vue";
  
  const authStore = useAuthStore();
  
  const isLoading = computed(() => authStore.isLoading);
  const validationMessages = computed(() => authStore.validationMessages);
  
  const email = ref();
  const password = ref();
  
  function handleLogin() {    
      authStore.login({
          email: email.value,
          password: password.value,
      });
  }
  </script>