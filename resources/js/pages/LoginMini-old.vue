<template>
  <!-- BACKDROP + MODAL -->
  <Teleport to="body">
    <!-- Animated Backdrop -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 
             bg-black/50 backdrop-blur-sm transition-all duration-300"
      :class="isOpen ? 'animate-in fade-in' : 'animate-out fade-out'" @click="handleCloseDialog">
      <!-- Click-outside to close -->
      <div class="absolute inset-0" @click="handleCloseDialog"></div>

      <!-- Modal Card -->
      <div class="relative w-full max-w-sm sm:max-w-md mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden
                max-h-[85vh] flex flex-col
                animate-in zoom-in-95 duration-300 ease-out" @click.stop>
        <!-- Close Button -->
        <button @click="handleCloseDialog" class="absolute top-4 right-4 z-10 rounded-full bg-white/90 p-2.5 shadow-lg 
                 hover:bg-white hover:scale-110 transition-all duration-200 backdrop-blur">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto px-8 pt-12 pb-6 scrollbar-thin scrollbar-thumb-gray-300">
          <!-- Logo -->
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-48 mx-auto 
                      transform transition-all duration-700 hover:scale-110 hover:rotate-6">
              <img src="/public/assets/logo.png" alt="Jetze" class="w-full h-full object-contain drop-shadow-2xl" />
            </div>
          </div>

          <!-- Back Button (OTP) -->
          <div v-if="showOtp" class="flex items-center mb-6 -ml-1 cursor-pointer select-none" @click="backToLogin">
            <ArrowLeft :size="18" class="mr-1 text-gray-600" />
            <span class="text-sm font-medium text-gray-600 hover:text-primary">Back to login</span>
          </div>

          <!-- Tabs -->
          <div v-if="!showOtp" class="flex my-7 bg-gray-100 rounded-xl p-1 shadow-inner">
            <button type="button" @click="formType = 'login'"
              class="flex-1 py-3 px-6 rounded-lg font-bold text-sm transition-all duration-200"
              :class="formType === 'login' ? 'bg-primary text-white shadow-md' : 'text-gray-600'">
              Login
            </button>
            <button type="button" @click="formType = 'register'"
              class="flex-1 py-3 px-6 rounded-lg font-bold text-sm transition-all duration-200"
              :class="formType === 'register' ? 'bg-primary text-white shadow-md' : 'text-gray-600'">
              Register
            </button>
          </div>

          <!-- Forms -->
          <Login v-if="formType === 'login' && !showOtp" @open-otp-card="openOtpScreen" />
          <OTPValidation v-else-if="showOtp" :show-otp-input="true" :user-details="userDetail"
            :key="'otp-' + userDetail.phone" />
          <Register v-else :key="'register'" />
        </div>

        <!-- Footer -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-8 py-5 border-t border-gray-200">
          <p class="text-xs text-center text-gray-600 leading-relaxed">
            By continuing, you agree to our
            <a href="#" class="text-primary font-semibold hover:underline"> Terms of Service</a> &
            <a href="#" class="text-primary font-semibold hover:underline"> Privacy Policy</a>
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
export default {
  props: {
    isOpen: {
      type: Boolean,
      default: true
    }
  },
  emits: ['close'],
  data() {
    return {
      showOtp: false,
      formType: 'login',
      userDetail: null
    }
  },
  methods: {
    handleCloseDialog() {
      this.$emit('close')
    },
    openOtpScreen(user) {
      this.userDetail = user
      this.showOtp = true
    },
    backToLogin() {
      this.showOtp = false
      this.formType = 'login'
    }
  }
}
</script>

<style scoped>
/* Custom scrollbar (optional) */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

/* Tailwind animate.css extensions (add to your global CSS if not using plugin) */
.animate-in {
  animation: fadeIn 0.3s ease-out;
}

.animate-out {
  animation: fadeOut 0.2s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

@keyframes fadeOut {
  from {
    opacity: 1;
  }

  to {
    opacity: 0;
  }
}
</style>

<script setup>
import store from '@/config/store'
import { SHOW_LOGIN_DIALOG } from '@/services/store/actions.type'
import { useAuthStore } from '@/services/stores/auth'
import { ArrowLeft } from 'lucide-vue-next'
import { computed, defineAsyncComponent, onMounted, ref, Teleport, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'

// Props & Emits
defineEmits(['close'])

// Async Components
const Login = defineAsyncComponent(() => import('@/components/agent/auth/Login.vue'))
const Register = defineAsyncComponent(() => import('@/components/agent/auth/Register.vue'))
const OTPValidation = defineAsyncComponent(() => import('@/components/agent/auth/OTPValidation.vue'))

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

// Reactive State
const formType = ref('login')
const showOtp = ref(false)
const userDetail = ref({})

const isAuthenticated = computed(() => authStore.isAuthenticated)
const user = computed(() => authStore.user)

// Auto redirect if already logged in
watch(isAuthenticated, (val) => {
  if (val) {
    handleUserDashboard()
  }
}, { immediate: true })

function handleUserDashboard() {
  // if (user.value?.role === 'admin') {
  //   router.push({ name: 'Dashboard' })
  // } else if (user.value?.is_formFilled === 0) {
  //   router.push({ name: 'AddAgentDetails' })
  // } else {
  //   router.push({ name: 'DashboardFlights' })
  // }
}

function handleCloseDialog() {
  authStore.openDialog();
  // store.dispatch('auth/' + SHOW_LOGIN_DIALOG)
}
// OTP Flow
function openOtpScreen(userData) {
  userDetail.value = userData
  showOtp.value = true
}

function backToLogin() {
  showOtp.value = false
  userDetail.value = {}
}

// Email verification toast
onMounted(() => {
  if (route.query.verified) {
    toast.success('Your email has been verified successfully!')
    router.replace({ query: { ...route.query, verified: undefined } })
  }
})
</script>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

/* Focus ring */
input:focus,
select:focus,
button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(168, 150, 102, 0.25);
}

/* Modal pop animation */
@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(30px);
  }

  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.animate-in {
  animation: modalPop 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>