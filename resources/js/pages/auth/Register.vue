<template>
  <div class="w-full lg:grid lg:grid-cols-2 h-screen">
    <!-- Left Column - Registration Form -->
    <div class="flex items-center justify-center py-12 bg-white relative overflow-hidden">
      <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-0 left-0 w-64 h-64 bg-gray-100 rounded-full -translate-x-1/2 -translate-y-1/2 opacity-60"></div>
        <div class="absolute bottom-0 right-0 w-64 h-64 bg-gray-100 rounded-full translate-x-1/3 translate-y-1/3 opacity-60"></div>
      </div>

      <div class="mx-auto grid w-[400px] gap-6 z-10">
        <div class="text-center mb-2">
          <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
          </div>
          <h1 class="text-3xl font-bold text-gray-900">Create Account</h1>
          <p class="text-gray-600 mt-2">Join us and start your journey today</p>
        </div>

        <form @submit.prevent="handleRegister" class="grid gap-5">
          <!-- Role -->
          <div class="space-y-2">
            <RadioGroup v-model="role" class="grid grid-cols-1 gap-2">
              <label for="agent" class="flex items-center space-x-2 p-3 rounded-lg"
                :class="{ 'text-primary font-bold': role === 'agent' }">
                <RadioGroupItem id="agent" value="agent" />
                <Label for="agent">Register as an Agent</Label>
              </label>
            </RadioGroup> 
          </div>

          <!-- Email -->
          <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input v-model="email" id="email" type="email" placeholder="your@email.com"
              class="w-full rounded-lg border-gray-300 focus:border-gray-500 focus:ring-gray-500" />
            <InputMessage v-if="validationMessages.email" class="text-red-500 text-xs">
              {{ validationMessages.email }}
            </InputMessage>
          </div>

          <!-- Password -->
          <div class="grid gap-2">
            <Label for="password">Password</Label>
            <Input v-model="password" id="password" type="password" placeholder="••••••••"
              class="w-full rounded-lg border-gray-300 focus:border-gray-500 focus:ring-gray-500" />
            <InputMessage v-if="validationMessages.password" class="text-red-500 text-xs">
              {{ validationMessages.password }}
            </InputMessage>
          </div>

          <!-- Confirm Password -->
          <div class="grid gap-2">
            <Label for="confirm-password">Confirm Password</Label>
            <Input v-model="confirmPassword" id="confirm-password" type="password" placeholder="••••••••"
              class="w-full rounded-lg border-gray-300 focus:border-gray-500 focus:ring-gray-500" />
            <InputMessage v-if="validationMessages.confirmPassword" class="text-red-500 text-xs">
              {{ validationMessages.confirmPassword }}
            </InputMessage>
          </div>

          <!-- Terms -->
          <div class="flex items-start mt-2">
            <div class="flex items-center h-5">
              <input v-model="acceptedTerms" id="terms" type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-gray-600 focus:ring-gray-500" />
            </div>
            <div class="ml-3 text-sm">
              <label for="terms" class="text-gray-600">
                I agree to the
                <a href="#" class="text-gray-600 hover:text-gray-500">Terms of Service</a> and
                <a href="#" class="text-gray-600 hover:text-gray-500">Privacy Policy</a>
              </label>
              <InputMessage v-if="validationMessages.terms" class="text-red-500 text-xs">
                {{ validationMessages.terms }}
              </InputMessage>
            </div>
          </div>

          <!-- Submit -->
          <Button type="submit" :isLoading="isLoading"
            class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2.5 rounded-lg transition-colors mt-2">
            Create Account
          </Button>

          <div class="mt-4 text-center text-sm">
            Already have an account?
            <router-link :to="{ name: 'Login' }" class="text-gray-600 hover:text-gray-500 font-medium">
              Sign in
            </router-link>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Column -->
    <div class="hidden lg:block relative">
      <div class="absolute inset-0 bg-gradient-to-br from-gray-600 to-emerald-800">
        <img
          src="https://images.unsplash.com/photo-1569154941061-e231b4725ef1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1470&q=80"
          alt="background" class="h-full w-full object-cover mix-blend-overlay opacity-50" />
      </div>
      <div class="absolute inset-0 flex flex-col items-center justify-center px-12 text-white">
        <div class="max-w-md text-center animate-fade-in-up">
          <div class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-white/10 backdrop-blur-sm mb-6 animate-pulse">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <h2 class="text-3xl font-bold mb-4">Welcome to Jetze</h2>
          <p class="text-lg mb-8 opacity-90">
            Join our flight booking and travel services portal. Manage your travel business efficiently, offer competitive flight deals, and grow your agency with ease.
          </p>
          <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 mt-8">
            <p class="italic text-white/90 mb-4">
              "Jetze helped us streamline bookings and offer our clients better prices. The support is outstanding!"
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import InputMessage from "@/components/ui/inputMessage.vue";
import { Label } from "@/components/ui/label";
import { RadioGroup } from "@/components/ui/radio-group";
import RadioGroupItem from "@/components/ui/radio-group/RadioGroupItem.vue";
import { useAuthStore } from "@/services/stores/auth";

const authStore = useAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const role = ref('agent');
const acceptedTerms = ref(false);
const isLoading = computed(() => authStore.isLoading);

const validationMessages = ref({});

async function handleRegister() {
  validationMessages.value = {};

  if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    validationMessages.value.email = "Please enter a valid email.";
  }
  if (!password.value || password.value.length < 6) {
    validationMessages.value.password = "Password must be at least 6 characters.";
  }
  if (confirmPassword.value !== password.value) {
    validationMessages.value.confirmPassword = "Passwords do not match.";
  }
  if (!acceptedTerms.value) {
    validationMessages.value.terms = "You must accept the terms.";
  }

  if (Object.keys(validationMessages.value).length > 0) return;

  try {
    await authStore.register({
      role: role.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    });

    // Redirect to Add Agent Details form
    router.push({ name: "AgentDetails" });
  } catch (err) {
    console.error(err);
    validationMessages.value.email = "Registration failed. Try again.";
  }
}
</script>
