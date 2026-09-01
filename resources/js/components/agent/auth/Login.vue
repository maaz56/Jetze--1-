<script setup>
import { computed, defineEmits, ref, watch } from 'vue';
import { toast } from 'vue3-toastify';

import { custom, email, minLength, required, useValidation } from '@/components/composables/useFormValidation';
import apiService, { ensureCsrfCookie } from '@/config/axios';
import { useAuthStore } from '@/services/stores/auth';
const authStore = useAuthStore()
const validationMessages = computed(() => authStore.validationMessages)
const emit = defineEmits(['openOtpCard', 'createPassword', 'backToOtp'])
const props = defineProps({
  initialEmail: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'email',
  },
})

const loginDetail = ref({
  email: '',
  password: '',
});
const validator = {
    email: useValidation([required('Please enter email address'), email('Please enter valid email address')]),
    password: useValidation([
      required('Password is required'),
      minLength(8, 'Password must be at least 8 characters.'),
      custom((value) => /[A-Za-z]/.test(value), 'Password must contain a letter.'),
      custom((value) => /\d/.test(value), 'Password must contain a number.'),
      custom((value) => /[^A-Za-z0-9]/.test(value), 'Password must contain a symbol.'),
    ]),
};
const isSubmitForm = ref(false)
const getValidation = computed(() => {
    const shouldValidate = isSubmitForm.value;
    const { email, password } = loginDetail.value;

    return {
        
        email: shouldValidate  && !!validator.email.validate(email),
        password: shouldValidate && props.mode !== 'email' && !!validator.password.validate(password),
    };
});
const isLoading = ref(false);
const showPassword = ref(false);
watch(
  () => props.initialEmail,
  (email) => {
    if (email) loginDetail.value.email = email
  },
  { immediate: true },
)

async function handleLogin() {
  isSubmitForm.value=true;
  if(getValidation.value.email || getValidation.value.password) {
    return;
  }
  isLoading.value = true;
  try {
    if (props.mode === 'password-login') {
      const response = await authStore.requestLoginOtp({
        email: loginDetail.value.email,
        password: loginDetail.value.password,
        mode: 'password',
      });
      if (response?.data?.skip_otp) {
        toast.success(response?.data?.message?.description || 'Logged in successfully.');
      }
      return;
    }

    if (props.mode === 'email') {
      const checkResponse = await authStore.requestLoginOtp({
        email: loginDetail.value.email,
        mode: 'check',
      });

      if (checkResponse?.data?.requires_password) {
        emit('createPassword', { email: loginDetail.value.email });
        return;
      }

      openOtpAndSendInBackground({ email: loginDetail.value.email }, true);
      return;
    }

    openOtpAndSendInBackground({
      email: loginDetail.value.email,
      password: loginDetail.value.password,
      mode: 'register',
    }, false);
  } catch (error) {
    console.error('Error requesting OTP:', error.response);
    const errorMessage = error.response?.data?.message?.description || 'Failed to send OTP. Please try again.';
    toast.error(errorMessage);
  }
  finally {

    isLoading.value = false;
  }
}

function openOtpAndSendInBackground(payload, accountExists) {
  // Render the OTP screen before any SMTP-related request is started.
  emit('openOtpCard', {
    email: loginDetail.value.email,
    accountExists,
    registrationPending: props.mode === 'create-password',
  });

  // Do not use the shared auth store here: its global loading flag can keep
  // other auth screens in a processing state while SMTP is connecting.
  void ensureCsrfCookie()
    .then(() => apiService.post('/login/request-otp', payload))
    .then((response) => {
      toast.success(response?.data?.message?.description || 'OTP has been sent to your email');
    })
    .catch((error) => {
      const message = error.response?.data?.message?.description || 'Unable to send the OTP. Please try again.';
      toast.error(message);
    });
}

async function handleGoogleLogin() {
  try {
    isLoading.value = true;
    const response = await authStore.googleLogin();
    // if (response && response.url) {
    //   window.location.href = response.url; // Redirect to Google OAuth URL
    // } else {
    //   toast.error('Failed to initiate Google login. Please try again.');
    // }
  } catch (error) {
    console.error('Error during Google login:', error);
  } finally {
    isLoading.value = false;
  }
}

</script>
<template>
  <div class="">
    <form @submit.prevent="handleLogin()" class="space-y-6">
      <div v-if="mode !== 'email'">
        <h2 class="text-xl font-bold text-gray-900">
          {{ mode === 'create-password' ? 'Create an Account' : 'Enter Your Password' }}
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          {{ mode === 'create-password' ? 'Set a password for your new account.' : 'Enter the password for your account.' }}
        </p>
      </div>

      <!-- Email-first sign in -->
      <div>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
          <input 
            type="email" 
            v-model="loginDetail.email"
            placeholder="Enter email address"
            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          />
        </div>
        
        <p v-if="getValidation.email" class="text-red-500 text-xs mt-1">
          {{ validator.email.validate(loginDetail.email) }}
        </p>
        <p v-else-if="validationMessages?.email" class="text-red-500 text-xs mt-1">
          {{ validationMessages.email }}
        </p>
      </div>

      <div v-if="mode !== 'email'">
        <div class="relative">
          <input
            :type="showPassword ? 'text' : 'password'"
            v-model="loginDetail.password"
            placeholder="Password"
            class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
          >
            <svg v-if="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878L3 3m6.878 6.878L21 21" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
        <p v-if="getValidation.password" class="text-red-500 text-xs mt-1">
          {{ validator.password.validate(loginDetail.password) }}
        </p>
        <p v-else-if="mode === 'create-password'" class="text-xs text-gray-600 mt-1">
          Password needs at least 8 characters, including letters, numbers, and symbols.
        </p>
      </div>

      <!-- Submit Button -->
      <button 
        type="submit" 
        :disabled="isLoading"
        class="w-full bg-primary text-white font-semibold py-3.5 px-4 rounded-md transition-all duration-200 flex items-center justify-center shadow-lg hover:shadow-xl disabled:opacity-50"
      >
        <span v-if="!isLoading">
          {{ mode === 'create-password' ? 'REGISTER AND CONTINUE' : mode === 'password-login' ? 'SIGN IN' : 'CONTINUE WITH EMAIL' }}
        </span>
        <span v-else>Processing...</span>
        <svg v-if="!isLoading" class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>

      <div v-if="mode === 'password-login'" class="text-center">
        <button
          type="button"
          @click="emit('backToOtp')"
          class="text-sm font-medium text-primary hover:text-primary/80"
        >
          Sign in with verification code
        </button>
      </div>

      <!-- Divider -->
      <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-gray-300"></div>
        </div>
        <div class="relative flex justify-center text-sm">
          <span class="px-4 bg-white text-gray-500">Or continue with</span>
        </div>
      </div>

      <!-- Google Sign-In Button -->
      <button
        type="button"
        @click="handleGoogleLogin"
        class="w-full flex items-center justify-center gap-3 border border-gray-300 bg-white text-gray-700 font-medium py-3 px-4 rounded-md hover:bg-gray-50 transition-all duration-200 shadow-sm hover:shadow"
      >
        <svg class="h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.501 12.233c0-.781-.069-1.531-.197-2.251H12v4.261h5.902c-.255 1.357-1.029 2.505-2.184 3.274v2.765h3.534c2.068-1.906 3.249-4.705 3.249-7.049z" fill="#4285F4"/>
          <path d="M12 23c3.24 0 5.956-1.074 7.942-2.906l-3.534-2.765c-1.076.722-2.454 1.146-4.408 1.146-3.39 0-6.258-2.225-7.277-5.22H.908v2.795C2.882 20.792 6.643 23 12 23z" fill="#34A853"/>
          <path d="M4.723 13.78C4.531 13.22 4.423 12.623 4.423 12s.108-.78.3-1.34V7.865H.908A11.946 11.946 0 000 12c0 1.945.467 3.786 1.292 5.415l3.431-2.635z" fill="#FBBC05"/>
          <path d="M12 4.5c1.758 0 3.336.64 4.564 1.692l3.425-3.425C17.956.917 15.24 0 12 0 6.643 0 2.882 2.208.908 6.135l3.815 2.935C5.742 6.005 8.61 4.5 12 4.5z" fill="#EA4335"/>
        </svg>
        <span>Continue with Google</span>
      </button>

    </form>
  </div>
</template>
