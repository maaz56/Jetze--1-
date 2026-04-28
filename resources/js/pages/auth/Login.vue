<template>
    <div class="flex min-h-screen bg-gray-50">
      <!-- Left side - Login Form -->
      <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
          <div class="mb-10">
            <!-- Logo placeholder - replace with your actual logo -->
            <div class="flex h-16 w-auto items-center justify-start rounded-xl text-white">
                <img src="/public/assets/logo.png" alt="Logo" class="h-16" />
            </div>
            
            <h2 class="mt-8 text-3xl font-bold tracking-tight text-gray-900">Welcome back</h2>
            <p class="mt-2 text-sm text-gray-600">
              Sign in to your account to continue
            </p>
          </div>
  
          <form @submit.prevent="handleLogin()" class="space-y-6">
            <div>
              <Label for="email" class="block text-sm font-medium text-gray-700">Email address</Label>
              <div class="mt-1">
                <Input
                  v-model="email"
                  id="email"
                  type="email"
                  placeholder="your@email.com"
                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                  required
                />
                <InputMessage v-if="validationMessages?.email" class="text-red-500">
                  {{ validationMessages.email }}
                </InputMessage>
              </div>
            </div>
  
            <div>
              <div class="flex items-center justify-between">
                <Label for="password" class="block text-sm font-medium text-gray-700">Password</Label>
                <router-link
                  :to="{ name: 'ForgotPassword' }"
                  class="text-sm font-medium text-gray-600 hover:text-gray-500"
                >
                  Forgot password?
                </router-link>
              </div>
              <div class="mt-1">
                <Input
                  v-model="password"
                  id="password"
                  type="password"
                  placeholder="••••••••"
                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                  required
                />
                <InputMessage v-if="validationMessages?.password" class="text-red-500">
                  {{ validationMessages.password }}
                </InputMessage>
              </div>
            </div>
  
            <div class="flex items-center">
              <input
                id="remember-me"
                name="remember-me"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-gray-600 focus:ring-gray-500"
              />
              <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
            </div>
  
            <div>
              <Button
                type="submit"
                :isLoading="isLoading"
                class="flex w-full justify-center rounded-lg bg-gray-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
              >
                Sign in
              </Button>
            </div>
          </form>
  
          <div class="mt-10">
            <div class="relative">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
              </div>
              <div class="relative flex justify-center text-sm">
                <span class="bg-gray-50 px-2 text-gray-500">Or continue with</span>
              </div>
            </div>
  
            <div class="mt-6">
              <button
                type="button"
                class="flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
              >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                </svg>
                Google
              </button>
            </div>
          </div>
  
          <p class="mt-8 text-center text-sm text-gray-600">
            Don't have an account?
            <router-link :to="{ name: 'Register' }" class="font-medium text-gray-600 hover:text-gray-500">
              Create an account
            </router-link>
          </p>
        </div>
      </div>
  
      <!-- Right side - Image -->
      <div class="relative hidden w-0 flex-1 lg:block">
        <img
          class="absolute inset-0 h-full w-full object-cover"
          src="https://images.unsplash.com/photo-1506012787146-f92b2d7d6d96?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1469&q=80"
          alt="gray leaves background"
        />
        <div class="absolute inset-0 bg-gray-700 mix-blend-multiply opacity-20"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40"></div>
        <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
          <div class="mx-auto max-w-md">
            <h2 class="text-2xl font-bold">Grow with us</h2>
            <p class="mt-2 text-sm opacity-90">
              Join our platform and experience the difference. We're committed to providing the best service for our users.
            </p>
          </div>
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