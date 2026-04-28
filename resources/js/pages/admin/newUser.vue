<template>
    <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg border p-6 sm:p-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Create New Agent</h1>

            <form @submit.prevent="submitForm" class="space-y-6">
                <!-- Role Selection -->
                <!-- <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <Select v-model="form.role">
                        <SelectTrigger id="role"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Available Roles</SelectLabel>
                                <SelectItem v-for="role in roles" :key="role" :value="role">
                                    {{ role?.toUpperCase()?.charAt(0) + role?.slice(1) }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div> -->

                <!-- Email, Password, Confirm Password Section -->
                <div class="space-y-6">
                    <div>
                        <label for="companyEmail" class="block text-sm font-medium text-gray-700 mb-1">Company Email</label>
                        <input type="email" id="companyEmail" v-model="form.companyEmail"
                            placeholder="Enter company email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                        <p v-if="errors.companyEmail" class="mt-1 text-sm text-red-600">{{ errors.companyEmail }}</p>
                    </div>

                    <div v-if="form.role === 'agent'">
                        <label for="ceoEmail" class="block text-sm font-medium text-gray-700 mb-1">CEO Email</label>
                        <input type="email" id="ceoEmail" v-model="form.ceoEmail"
                            placeholder="Enter CEO email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                        <p v-if="errors.ceoEmail" class="mt-1 text-sm text-red-600">{{ errors.ceoEmail }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" v-model="form.password"
                                placeholder="••••••••"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary pr-10"
                                required />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <eye-icon v-if="!showPassword" class="h-5 w-5 text-gray-500" />
                                <eye-off-icon v-else class="h-5 w-5 text-gray-500" />
                            </button>
                        </div>
                        <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
                    </div>

                    <div>
                        <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <div class="relative">
                            <input :type="showConfirmPassword ? 'text' : 'password'" id="confirmPassword"
                                v-model="form.confirmPassword" placeholder="••••••••"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary pr-10"
                                required />
                            <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <eye-icon v-if="!showConfirmPassword" class="h-5 w-5 text-gray-500" />
                                <eye-off-icon v-else class="h-5 w-5 text-gray-500" />
                            </button>
                        </div>
                        <p v-if="errors.confirmPassword" class="mt-1 text-sm text-red-600">{{ errors.confirmPassword }}</p>
                    </div>
                </div>

                <!-- Company Details Section -->
                <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                    <div v-if="form.role === 'agent'">
                        <label for="companyName" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" id="companyName" v-model="form.companyName" placeholder="Enter company name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
                            required />
                    </div>

                    <div >
                        <label for="ceoName" class="block text-sm font-medium text-gray-700 mb-1">CEO Name</label>
                        <input type="text" id="ceoName" v-model="form.ceoName" placeholder="Enter CEO's name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" id="phone" v-model="form.phone" placeholder="Enter phone number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                    </div>

                    <div v-if="form.role === 'agent'">
                        <label for="language" class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                        <input type="text" id="language" v-model="form.language" placeholder="Enter preferred language"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                    </div>

                    <div v-if="form.role === 'agent'">
                        <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                        <input type="text" id="currency" v-model="form.currency" placeholder="Enter preferred currency"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary" />
                    </div>
                </div>

                <!-- File Uploads -->
                <div v-if="form.role === 'agent'" class="flex space-x-4">
                    <div class="space-y-2">
                        <div class="grid gap-2">
                            <label for="companyLogo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Company Logo</label>
                        </div>
                        <input class="w-full" id="companyLogo" type="file" @change="handleFileUpload" />
                    </div>
                    <div class="space-y-2">
                        <div class="grid gap-2">
                            <label for="traderLicense" class="text-sm font-medium text-gray-700 dark:text-gray-300">Trader License</label>
                        </div>
                        <input class="w-full" id="traderLicense" type="file" @change="handleTraderLicence" />
                    </div>
                    <div class="space-y-2">
                        <div class="grid gap-2">
                            <label for="Eid" class="text-sm font-medium text-gray-700 dark:text-gray-300">Emirates ID</label>
                        </div>
                        <input class="w-full" id="Eid" type="file" @change="handleEid" />
                    </div>
                </div>

                <!-- Address -->
                <div v-if="form.role === 'agent'">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" v-model="form.address" placeholder="Enter your address" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></textarea>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-gray-700 focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-primary"
                        :disabled="isSubmitting">
                        <span v-if="isSubmitting">Processing...</span>
                        <span v-else>Create {{ form.role?.toUpperCase()?.charAt(0) + form.role?.slice(1) || 'Agent' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { ref, reactive, computed } from 'vue';
import { EyeIcon, EyeOffIcon } from 'lucide-vue-next';
import { SAVE_ADMIN_AGENT } from '@/services/store/actions.type';
import { useStore } from "vuex";
import Input from '@/components/common/Input.vue';

const store = useStore();

// Roles array
//const roles = ref(['agent', 'salesman']);

// Form state
const companyLogo = ref(null);
const license = ref(null);
const e_id = ref(null);

const form = reactive({
    role: 'agent',
    companyEmail: '',
    ceoEmail: '',
    password: '',
    confirmPassword: '',
    companyName: '',
    phone: '',
    ceoName: '',
    language: '',
    currency: '',
    address: ''
});

// UI state
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const isSubmitting = ref(false);
const errors = reactive({
    companyEmail: '',
    ceoEmail: '',
    password: '',
    confirmPassword: ''
});

// Handle file upload
const handleFileUpload = (event) => {
    companyLogo.value = event.target.files[0];
};

const handleTraderLicence = (event) => {
    license.value = event.target.files[0];
};

const handleEid = (event) => {
    e_id.value = event.target.files[0];
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
    errors.companyEmail = '';
    errors.ceoEmail = '';
    errors.password = '';
    errors.confirmPassword = '';

    // Validate role
    if (!form.role) {
        isValid = false;
    }

    // Validate company email
    if (!validateEmail(form.companyEmail)) {
        errors.companyEmail = 'Please enter a valid company email address';
        isValid = false;
    }

    // Validate CEO email for agent role
    if (form.role === 'agent' && form.ceoEmail && !validateEmail(form.ceoEmail)) {
        errors.ceoEmail = 'Please enter a valid CEO email address';
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

    return isValid;
};

// Submit form
const submitForm = async () => {
    if (!validateForm()) {
        return;
    }

    const agentData = new FormData();
    agentData.append("company_email", form.companyEmail);
    agentData.append("email", form.companyEmail);
    agentData.append("password", form.password);
    agentData.append("company_name", form.companyName);
    agentData.append("phone", form.phone);
    agentData.append("role", "agent");

    if (form.role === 'agent') {
        agentData.append("ceo_email", form.ceoEmail);
        agentData.append("ceo_name", form.ceoName);
        agentData.append("language", form.language);
        agentData.append("currency", form.currency);
        agentData.append("address", form.address);
        if (companyLogo.value) {
            agentData.append("logo", companyLogo.value);
        }
        if (license.value) {
            agentData.append("license", license.value);
        }
        if (e_id.value) {
            agentData.append("e_id", e_id.value);
        }
    }

    isSubmitting.value = true;

    store.dispatch("user/" + SAVE_ADMIN_AGENT, agentData).then(() => {
        isSubmitting.value = false;
        Object.keys(form).forEach(key => {
            if (key === 'companyLogo' || key === 'license' || key === 'e_id') {
                form[key] = null;
            } else {
                form[key] = '';
            }
        });
    });
};
</script>