<template>
    <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-3xl mx-auto bg-white rounded-lg border p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Create New Staff Member</h1>
  
        <form @submit.prevent="submitForm" class="space-y-6">
          <!-- Email, Password, Confirm Password Section -->
          <div class="space-y-6">
            <div>
              <label for="staffName" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input
                type="text"
                id="staffName"
                v-model="form.staffName"
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
                  required
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
                  required
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
              <span v-else>Create Agent</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, reactive, computed } from 'vue';
  import { EyeIcon, EyeOffIcon, ChevronDownIcon } from 'lucide-vue-next';
  import { SAVE_STAFF } from '@/services/store/actions.type';
  import { useStore } from "vuex";
  
  const store = useStore();
  
  // Form state
  const companyLogo = ref(null);
  
  const form = reactive({
    email: '',
    password: '',
    confirmPassword: '',
    staffName: '',
    staffRole: '',
  });
  
  // Staff roles data
  const staffRoles = ref([
    { id: 'admin', name: 'Administrator' },
    { id: 'reservation', name: 'Reservation Staff' },
    { id: 'accounts', name: 'accounts' },
    { id: 'salesman', name: 'Salesman' }
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
  
  // Handle file upload
  const handleFileUpload = (event) => {
    companyLogo.value = event.target.files[0];
  };
  
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
    if (!validateEmail(form.email)) {
      errors.email = 'Please enter a valid email address';
      isValid = false;
    }
  
    // Validate password
    if (form.password.length < 8) {
      errors.password = 'Password must be at least 8 characters long';
      isValid = false;
    }
  
    // Validate confirm password
    if (form.password !== form.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match';
      isValid = false;
    }
  
    // Validate staff role
    if (!form.staffRole) {
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
    staffData.append("name", form.staffName);
    staffData.append("email", form.email);
    staffData.append("password", form.password);
    staffData.append("role", form.staffRole);
    
    isSubmitting.value = true;
  
    //console.log(JSON.stringify(Object.fromEntries(staffData.entries())));
  
    store.dispatch("user/" + SAVE_STAFF, staffData).then(() => {
      //console.log("Staff saved successfully");
      form.staffName = "";
        form.email = "";
        form.password = "";
        form.staffRole = "";

      isSubmitting.value = false;
    
    });
  };
  </script>