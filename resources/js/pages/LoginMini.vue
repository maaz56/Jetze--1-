<template>
  <!-- TELEPORT ROOT -->
  <Teleport to="body">
    <!-- BACKDROP -->
    <div v-bind="$attrs" class="fixed inset-0 z-50 flex items-center justify-center p-4
             bg-black/50  transition-all duration-300" :class="isOpen ? 'animate-in fade-in' : 'animate-out fade-out'"
      @click="handleCloseDialog">
      <!-- Click outside -->
      <div class="absolute inset-0"></div>

      <!-- MODAL -->
      <div class="relative w-full max-w-sm sm:max-w-md mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden
               max-h-[85vh] flex flex-col animate-in zoom-in-95 duration-300 ease-out" @click.stop>
        <!-- Close Button -->
        <button @click="handleCloseDialog" class="absolute top-4 right-4 z-10 rounded-full bg-white/90 p-2.5 border border-primary
                 hover:bg-white hover:scale-110 transition-all duration-200">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- CONTENT -->
        <div class="flex-1 overflow-y-auto px-8 pt-12 pb-6 scrollbar-thin scrollbar-thumb-gray-300">
          <!-- Logo -->
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-48 mx-auto
                        transition-all duration-700 hover:scale-110 hover:rotate-6">
              <img src="/public/assets/logo.png" alt="Jetze"
                class="w-full h-full object-contain drop-shadow-2xl" />
            </div>
          </div>

          <!-- Back Button (OTP) -->
          <div v-if="showOtp" class="flex items-center mb-6 -ml-1 cursor-pointer select-none" @click="backToLogin">
            <ArrowLeft :size="18" class="mr-1 text-gray-600" />
            <span class="text-sm font-medium text-gray-600 hover:text-primary">
              Back to login
            </span>
          </div>

          <!-- Tabs -->
          <div v-if="!showOtp" class="flex my-7 bg-gray-100 rounded-md shadow-inner">
            <button type="button" @click="formType = 'login'"
              class="flex-1 py-3 px-6  font-bold rounded-md text-sm transition-all duration-200" :class="formType === 'login'
                ? 'bg-primary text-white shadow-md'
                : 'text-gray-600'">
              Login
            </button>

            <button type="button" @click="formType = 'register'"
              class="flex-1 py-3 px-6 rounded-md font-bold text-sm transition-all duration-200" :class="formType === 'register'
                ? 'bg-primary text-white shadow-md'
                : 'text-gray-600'">
              Register
            </button>
          </div>

          <!-- FORMS -->
          <Login v-if="formType === 'login' && !showOtp" @open-otp-card="openOtpScreen" />

          <OTPValidation v-else-if="showOtp" :show-otp-input="true" :user-details="userDetail"
            :key="'otp-' + userDetail.phone" />

          <Register v-else />
        </div>

        <!-- FOOTER -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-8 py-5 border-t border-gray-200">
          <p class="text-xs text-center text-gray-600 leading-relaxed">
            By continuing, you agree to our
            <a href="#" class="text-primary font-semibold hover:underline">Terms of Service</a>
            &
            <a href="#" class="text-primary font-semibold hover:underline">Privacy Policy</a>
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import { useAuthStore } from '@/services/stores/auth'

defineOptions({ inheritAttrs: false })

defineProps({
  isOpen: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close'])

const Login = defineAsyncComponent(() => import('@/components/agent/auth/Login.vue'))
const Register = defineAsyncComponent(() => import('@/components/agent/auth/Register.vue'))
const OTPValidation = defineAsyncComponent(() => import('@/components/agent/auth/OTPValidation.vue'))

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const user = computed(() => authStore.user);
const formType = ref('login')
const showOtp = ref(false)
const userDetail = ref({})

const isAuthenticated = computed(() => authStore.isAuthenticated)

watch(isAuthenticated, (val) => {
  if (val) handleUserDashboard()
}, { immediate: true })

function handleCloseDialog() {
  emit('close')
  authStore.isDialogOpen = false
}

function openOtpScreen(userData) {
  userDetail.value = userData
  showOtp.value = true
}

function backToLogin() {
  showOtp.value = false
  userDetail.value = {}
}

function handleUserDashboard() {
  if (!user.value) return;

  // const role = user.value.role;

  // if (role === 'admin') {
  //   router.push({ name: 'Dashboard' });
  // } else if (role === 'agent') {
  //   if (user.value.is_formFilled === 0) {
  //     router.push({ name: 'AddAgentDetails' });
  //   } else {
  //     router.push({ name: 'DashboardFlights' });
  //   }
  // } else if (role === 'customer' || role === 'user') {
  //   router.push({ name: 'CustomerProfile' });
  // } else {
  //   // This covers reservation, accounts, salesman and any custom roles created in the database
  //   router.push({ name: 'AdminCustomerBookings' });
  // }
}

onMounted(() => {
  if (route.query.verified) {
    toast.success('Your email has been verified successfully!')
    router.replace({ query: { ...route.query, verified: undefined } })
  }
});

watch(user, (newUser) => {
  if (newUser) {
    showOtp.value = false;
    emit('close');
    authStore.isDialogOpen = false;
    handleUserDashboard();
  }
});
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

@keyframes fadeIn {
  from {
    opacity: 0
  }

  to {
    opacity: 1
  }
}

@keyframes fadeOut {
  from {
    opacity: 1
  }

  to {
    opacity: 0
  }
}

.animate-in {
  animation: fadeIn 0.3s ease-out;
}

.animate-out {
  animation: fadeOut 0.2s ease-in;
}
</style>
