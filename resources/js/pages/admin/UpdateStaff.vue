<template>
  <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg border p-6 sm:p-8">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Update Staff Member</h1>
      
      <!-- Loading state -->
      <div v-if="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        <span class="ml-2">Loading user data...</span>
      </div>
      
      <form v-else @submit.prevent="submitForm" class="space-y-6">
        <!-- Email, Password, Confirm Password Section -->
        <div class="space-y-6">
          <div>
            <label for="staffName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              type="text"
              id="staffName"
              v-model="form.name"
              placeholder="Ali Ahmed"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
            <p v-if="errors.staffName" class="mt-1 text-sm text-red-600">{{ errors.staffName }}</p>
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              type="email"
              id="email"
              v-model="form.email"
              placeholder="m@example.com"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <div class="relative">
              <input
                :type="showPassword ? 'text' : 'password'"
                id="password"
                v-model="form.password"
                placeholder="••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary pr-10"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center"
              >
                <EyeIcon v-if="!showPassword" class="h-5 w-5 text-gray-500" />
                <EyeOffIcon v-else class="h-5 w-5 text-gray-500" />
              </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password</p>
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
          </div>

          <div>
            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">
              Confirm Password
            </label>
            <div class="relative">
              <input
                :type="showConfirmPassword ? 'text' : 'password'"
                id="confirmPassword"
                v-model="form.confirmPassword"
                placeholder="••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary pr-10"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center"
              >
                <EyeIcon v-if="!showConfirmPassword" class="h-5 w-5 text-gray-500" />
                <EyeOffIcon v-else class="h-5 w-5 text-gray-500" />
              </button>
            </div>
            <p v-if="errors.confirmPassword" class="mt-1 text-sm text-red-600">
              {{ errors.confirmPassword }}
            </p>
          </div>

          <!-- Staff Role Dropdown -->
          <div>
            <label for="staffRole" class="block text-sm font-medium text-gray-700 mb-1">Staff Role</label>
            <div class="relative">
              <select
                id="staffRole"
                v-model="form.staffRole"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary appearance-none"
                required
              >
                <option value="" disabled selected>Select a role</option>
                <option v-for="role in staffRoles" :key="role.id" :value="role.id">
                  {{ role.name }}
                </option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                <ChevronDownIcon class="h-5 w-5" />
              </div>
            </div>
            <p v-if="errors.staffRole" class="mt-1 text-sm text-red-600">{{ errors.staffRole }}</p>
          </div>
        </div>

        <!-- Submit Button -->
        <div>
          <button
            type="submit"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-gray-700 focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-primary"
            :disabled="isSubmitting"
          >
            <span v-if="isSubmitting">Processing...</span>
            <span v-else>Update Staff Member</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { EyeIcon, EyeOffIcon, ChevronDownIcon } from 'lucide-vue-next';
import { SAVE_STAFF, UPDATE_STAFF } from '@/services/store/actions.type';
import { useStore } from "vuex";
import { useUserStore } from "@/services/stores/user";
import { useRoute } from "vue-router";

const store = useStore();
const userStore = useUserStore();
const user = computed(() => userStore.user);
const route = useRoute();
const loading = ref(true);

// Initialize form with empty values first
const form = ref({
  email: '',
  password: '',
  confirmPassword: '',
  name: '',
  staffRole: ''
});

// Staff roles data
const staffRoles = ref([
  { id: 'admin', name: 'Administrator' },
  { id: 'reservation', name: 'Reservation Staff' },
  { id: 'accounts', name: 'accounts' },
]);

// UI state
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const isSubmitting = ref(false);
const errors = reactive({
  email: '',
  password: '',
  confirmPassword: '',
  staffName: '',
  staffRole: '',
});

// Fetch user data
async function fetchUser() {
  loading.value = true;
  try {
    await userStore.fetchUser({
      id: route.query.user_id
    });
    
    // Update form with user data after it's fetched
    if (user.value) {
      form.value = {
        email: user.value.email || '',
        password: '',
        confirmPassword: '',
        name: user.value.name || '',
        staffRole: user.value.role || ''
      };
    }
  } catch (error) {
    console.error('Failed to fetch user:', error);
  } finally {
    loading.value = false;
  }
}

// Watch for changes to route.query.user_id
watch(() => route.query.user_id, (newId) => {
  if (newId) {
    fetchUser();
  }
}, { immediate: true });

// Watch for changes to user data
watch(() => user.value, (newUserData) => {
  if (newUserData) {
    form.value = {
      email: newUserData.email || '',
      password: '',
      confirmPassword: '',
      name: newUserData.name || '',
      staffRole: newUserData.role || ''
    };
  }
}, { deep: true });

// Validate email
const validateEmail = (email) => {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(String(email).toLowerCase());
};

// Validate form
const validateForm = () => {
  let isValid = true;

  // Reset errors
  errors.staffName = '';
  errors.email = '';
  errors.password = '';
  errors.confirmPassword = '';
  errors.staffRole = '';

  // Validate email
  if (!validateEmail(form.value.email)) {
    errors.email = 'Please enter a valid email address';
    isValid = false;
  }

  // Only validate password if provided (allow empty for keeping current password)
  if (form.value.password) {
    // Validate password
    if (form.value.password.length < 8) {
      errors.password = 'Password must be at least 8 characters long';
      isValid = false;
    }

    // Validate confirm password
    if (form.value.password !== form.value.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match';
      isValid = false;
    }
  }

  // Validate staff role
  if (!form.value.staffRole) {
    errors.staffRole = 'Please select a staff role';
    isValid = false;
  }

  return isValid;
};

// Submit form
const submitForm = async () => {
  if (!validateForm()) {
    return;
  }

  const staffData = new FormData();
  staffData.append("name", form.value.name);
  staffData.append("email", form.value.email);
  
  // Only include password if it was changed
  if (form.value.password) {
    staffData.append("password", form.value.password);
  }
  
  staffData.append("role", form.value.staffRole);
  staffData.append("id", route.query.user_id);
  
  isSubmitting.value = true;

  try {
    await store.dispatch("user/" + UPDATE_STAFF, staffData);
    //console.log("Staff updated successfully");
    
    // Reset password fields after successful update
    form.value.password = "";
    form.value.confirmPassword = "";
    
    // Show success message or redirect
  } catch (error) {
    console.error("Error updating staff:", error);
    // Show error message
  } finally {
    isSubmitting.value = false;
  }
};

// Fetch user data on component mount
onMounted(() => {
  if (route.query.user_id) {
    fetchUser();
  }
});
</script>