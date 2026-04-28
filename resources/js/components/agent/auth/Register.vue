<script setup>
import FileUploaderWithView from '@/components/common/FileUploaderWithView.vue';
import Label from "@/components/ui/label/Label.vue";
import { Check, ChevronsUpDown } from "lucide-vue-next";
import Button from "@/components/ui/button/Button.vue";
import { cn } from "@/lib/utils";


import { custom, email, minLength, required, useValidation } from '@/components/composables/useFormValidation';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import { useAuthStore } from '@/services/stores/auth';
import { LucideBuilding, LucideHome, UploadCloud, UserRoundPlus } from 'lucide-vue-next';
import { VisuallyHidden } from 'radix-vue';
import { computed, ref } from 'vue';
import { VueTelInput } from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';
import {  FETCH_CURRENCIES } from "@/services/store/actions.type";
import { useStore } from "vuex";
const store = useStore();

const currencies = computed(() => store.getters["currency/currencies"] || []);

const authStore = useAuthStore();
const isOpenCurrencyDropdown = ref(false);
const selectedCurrency = ref();


const userDetail = ref({
    role: 'customer', // 'agent' or 'customer'
    name: '',
    lastName: '',
    email: '',
    phone: '',
    password: '',
    confirmPassword: '',
    logo: '',
    license: '',
    e_id: '',
    companyName: '',
    address: '',
    preferredCurrency: '',
});
const phoneValid = ref(false);
const phoneInputOption = {
    placeholder: "Enter Phone Number",
    maxlength: 15,
};
const dropdown = {
    showSearchBox: true,
    showFlags: true,
    width: "390px",
};
const isSubmitForm = ref(false);
const validator = {
    role: useValidation([required('Please select a role')]),
    name: useValidation([required('Please enter first name'), custom(() => (/^[A-Za-z\s]+$/.test(userDetail.value.name.trim())), 'First Name should be only in alphabetic',)]),
    lastName: useValidation([required('Please enter last name'), custom(() => (/^[A-Za-z\s]+$/.test(userDetail.value.lastName.trim())), 'Last name should be only in alphabetic',)]),
    companyName: useValidation([custom(() => {
        if (userDetail.value.role === 'agent') {
            return required('Please enter Company Name')(userDetail.value.companyName) === true;
        }
        return true;
    }, 'Please enter Company Name')]),
    email: useValidation([required('Please enter email address'), email('Please enter valid email address')]),
    password: useValidation([
        required('Password is required'),
        minLength(8, 'Password must be at least 8 characters long.'),
        custom(() => /[!@#$%^&*(),.?":{}|<>]/.test(userDetail.value.password), 'Must contain a special character'),
        custom(() => /[A-Z]/.test(userDetail.value.password), 'Must contain an uppercase letter'),
        custom(() => /[a-z]/.test(userDetail.value.password), 'Must contain a lowercase letter'),
        custom(() => /\d/.test(userDetail.value.password), 'Must contain a number')
    ]),
    confirmPassword: useValidation([custom(() => {
        const pwd = String(userDetail.value.password ?? '').trim();
        const cpwd = String(userDetail.value.confirmPassword ?? '').trim();
        if (!cpwd) return false; // pass validation until user types confirm
        return cpwd === pwd;
    }, 'Confirm password must match the password.')]),
    phone: useValidation([custom(() => phoneValid.value, 'Please enter a valid phone number.')]),
}
const currentStep = ref(1)

const getValidation = computed(() => {
    const shouldValidate = isSubmitForm.value;
    const { role, name, lastName, companyName, email, password, phone, confirmPassword } = userDetail.value;

    return {
        role: shouldValidate && !!validator.role.validate(role),
        name: shouldValidate && !!validator.name.validate(name),
        lastName: shouldValidate && !!validator.lastName.validate(lastName),
        companyName: shouldValidate && !!validator.companyName.validate(companyName),
        email: shouldValidate && !!validator.email.validate(email),
        password: shouldValidate && !!validator.password.validate(password),
        confirmPassword: shouldValidate && !!validator.confirmPassword.validate(confirmPassword),
        phone: shouldValidate && !!validator.phone.validate(phone),
    };
});
const isValidFirstStep = computed(() => {
    return Object.values(getValidation.value).every(val => !val);
});
const showPassword = ref(false)
const isLoading = computed(() => authStore.isLoading)
const validationMessages = computed(() => authStore.validationMessages);

// Check if user is registering as agent
const isAgent = computed(() => userDetail.value.role === 'agent');

async function handleRegister() {
    isSubmitForm.value = true;
    
    // // Validate role selection
    // if (!userDetail.value.role) {
    //     currentStep.value = 1;
    //     return;
    // }
    
    // if (!isValidFirstStep.value) {
    //     currentStep.value = 1;
    //     return
    // }
    // else if (currentStep.value == 1 && isAgent.value) {
    //     currentStep.value = 2;
    //     return;
    // }
    
    const { role, name, lastName, email, phone, password, confirmPassword, logo, license, e_id, companyName, address, preferredCurrency } = userDetail.value
    const formData = new FormData();
    formData.append('role', role);
    formData.append('name', name);
    formData.append('lastName', lastName);
    formData.append('email', email);
    formData.append('phone', phone);
    formData.append('mobile', phone);
    formData.append('password', password);
    formData.append('password_confirmation', confirmPassword);
    formData.append('company_name', companyName);
    formData.append('preferredCurrency', selectedCurrency.value || preferredCurrency || 'PKR');
    
    // Only add agent-specific fields if registering as agent
    if (isAgent.value) {
        formData.append('logo', logo);
        formData.append('license', license);
        if (Array.isArray(e_id)) {
            e_id.forEach((file) => {
                formData.append('e_id[]', file);
            });
        } else {
            formData.append('e_id', e_id);
        }
        formData.append('company_name', companyName);
    }
    
    formData.append('address', address);
    
    await authStore.register(formData);
    if (validationMessages.value) {
        currentStep.value = 1;
    }
}

function fetchCurrencies(event) {
    store.dispatch("currency/" + FETCH_CURRENCIES, {
        searchQuery: event.target.value,
    });
}

const handleUploadFile = (event, key) => {
    const files = event;
    userDetail.value[key] = key == 'e_id' ? files : files[0];
};
</script>

<template>
    <!-- <div class="flex justify-center">
        <Stepper v-model="currentStep">
            <StepperItem :step="1">
                <StepperTrigger>
                    <StepperIndicator>
                        <UserRoundPlus></UserRoundPlus>
                    </StepperIndicator>
                    <StepperTitle>Information</StepperTitle>
                    <VisuallyHidden>
                        <StepperDescription>Enter your details</StepperDescription>
                    </VisuallyHidden>
                </StepperTrigger>
                <StepperSeparator />
            </StepperItem>
            <StepperItem v-if="isAgent" :step="2">
                <StepperTrigger>
                    <StepperIndicator>
                        <UploadCloud></UploadCloud>
                    </StepperIndicator>
                    <StepperTitle>Documents</StepperTitle>
                    <VisuallyHidden>
                        <StepperDescription>Upload your files</StepperDescription>
                    </VisuallyHidden>
                </StepperTrigger>
            </StepperItem>
        </Stepper>
    </div> -->
    <form @submit.prevent="handleRegister()" class="space-y-4">
        <!-- Role Selection -->
        <!-- <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Register as:</label>
            <div class="flex space-x-4">
                <label class="flex items-center">
                    <input 
                        type="radio" 
                        v-model="userDetail.role" 
                        value="customer"
                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                    >
                    <span class="ml-2 text-gray-700">Customer</span>
                </label>
                <label class="flex items-center">
                    <input 
                        type="radio" 
                        v-model="userDetail.role" 
                        value="agent"
                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                    >
                    <span class="ml-2 text-gray-700">Agent</span>
                </label>
            </div>
            <p v-if="(!!getValidation.role) && isSubmitForm" class="text-red-500 text-xs mt-1">
                {{ validator.role.validate(userDetail.role) }}
            </p>
        </div> -->

        <template v-if="currentStep == 1">
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" v-model="userDetail.name" placeholder="Enter Full Name"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                
                <p v-if="(!!getValidation.name) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.name.validate(userDetail.name) }}
                </p>
                <p v-else-if="validationMessages?.name" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.name }}
                </p>
            </div>
            <!-- <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" v-model="userDetail.lastName" placeholder="Enter Last Name"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                <p v-if="(!!getValidation.lastName) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.lastName.validate(userDetail.lastName) }}
                </p>
                <p v-else-if="validationMessages?.lastName" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.lastName }}
                </p>
            </div> -->
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="email" v-model="userDetail.email" placeholder="Enter Email"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                <p v-if="(!!getValidation.email) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.email.validate(userDetail.email) }}
                </p>
                <p v-else-if="validationMessages?.email" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.email }}
                </p>
            </div>
            <div>
                <VueTelInput v-model="userDetail.phone" :inputOptions="phoneInputOption" :dropdownOptions="dropdown"
                    @validate="({ valid }) => phoneValid = valid" mode="international" :validCharactersOnly="true">
                </VueTelInput>
                <p v-if="(!phoneValid) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    Please enter valid phone number
                </p>
                <p v-else-if="validationMessages?.phone" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.phone }}
                </p>
            </div>
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input :type="showPassword ? 'text' : 'password'" v-model="userDetail.password"
                        placeholder="Enter Password"
                        class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                    <button type="button" @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg v-if="showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                        </svg>
                        <svg v-else class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <p v-if="(!!getValidation.password) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.password.validate(userDetail.password) }}
                </p>
                <p v-else-if="validationMessages?.password" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.password }}
                </p>
            </div>
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" v-model="userDetail.confirmPassword" placeholder="Confirm Password"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                <p v-if="(!!getValidation.confirmPassword) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.confirmPassword.validate(userDetail.confirmPassword) }}
                </p>
                <p v-else-if="validationMessages?.password_confirmation" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.password_confirmation }}
                </p>
            </div>
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <LucideHome class="w-5 h-5 text-gray-400" />
                    </div>
                    <input type="text" v-model="userDetail.address" placeholder="Your address"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                <p v-if="validationMessages?.address" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.address }}
                </p>
            </div>
            
            <!-- Company Name - Only for Agents -->
            <div >
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <LucideBuilding class="w-5 h-5 text-gray-400" />
                    </div>
                    <input type="text" v-model="userDetail.companyName" placeholder="Company Name (Optional)"
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                </div>
                <p v-if="(!!getValidation.companyName) && isSubmitForm" class="text-red-500 text-xs mt-1">
                    {{ validator.companyName.validate(userDetail.companyName) }}
                </p>
                <p v-else-if="validationMessages?.companyName" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.companyName }}
                </p>
            </div>
            <div>
                <Popover v-model:open="isOpenCurrencyDropdown">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            role="combobox"
                            class="w-full justify-between rounded-md py-6 text-gray-400"
                        >
                            {{
                                selectedCurrency !== ""
                                    ? currencies.find(
                                          (currency) =>
                                              currency.code === selectedCurrency
                                      )?.code || "Select a currency..."
                                    : "Select a currency ..."
                            }}
                            <ChevronsUpDown
                                class="ml-2 h-4 w-4 shrink-0 opacity-50"
                            />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-full p-0">
                        <Command>
                            <CommandInput
                                class="h-9"
                                @input="fetchCurrencies"
                                placeholder="Search currency..."
                            />
                            <CommandEmpty>No results found.</CommandEmpty>
                            <CommandList>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="currency in currencies"
                                        :key="currency.code"
                                        :value="currency.code"
                                        @select="
                                            (ev) => {
                                                if (
                                                    typeof ev.detail.value ===
                                                    'string'
                                                ) {
                                                    selectedCurrency =
                                                        ev.detail.value;
                                                }
                                                open = false;
                                            }
                                        "
                                    >
                                        {{ currency.code }}
                                        <Check
                                            :class="
                                                cn(
                                                    'ml-auto h-4 w-4',
                                                    selectedCurrency ===
                                                        currency.code
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                )
                                            "
                                        />
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>
        </template>
        
        <!-- Document Step - Only for Agents -->
        <template v-else-if="currentStep == 2 && isAgent">
            <div class="space-y-2">
                <Label for="logo" title="Logo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Logo</Label>
                <FileUploaderWithView @update:model-value="handleUploadFile($event, 'logo')" :required="true"
                    :max-size="2" accept="image/*,application/pdf">
                </FileUploaderWithView>
                <p v-if="validationMessages?.logo" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.logo }}
                </p>
            </div>
            <div class="space-y-2">
                <Label for="license" title="License"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300">T-License</Label>
                <FileUploaderWithView @update:model-value="handleUploadFile($event, 'license')" :required="true"
                    :max-size="2" accept="image/*,application/pdf">
                </FileUploaderWithView>
                <p v-if="validationMessages?.tradeLicense" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.tradeLicense }}
                </p>
            </div>
            <div class="space-y-2">
                <Label for="e_id" title="E-ID" class="text-sm font-medium text-gray-700 dark:text-gray-300">Emirates
                    ID</Label>
                <FileUploaderWithView :multiple="true" @update:model-value="handleUploadFile($event, 'e_id')"
                    :required="true" :max-size="2" accept="image/*,application/pdf">
                </FileUploaderWithView>
                <p v-if="validationMessages?.emirateId" class="text-red-500 text-xs mt-1">
                    {{ validationMessages.emirateId }}
                </p>
            </div>
        </template>
        
        <!-- <button type="submit" :disabled="isLoading"
            class="w-full bg-primary hover:bg-primary/80 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center disabled:opacity-50 shadow-lg">
            <span v-if="!isLoading">
                {{ 
                    currentStep == 1 && isAgent ? 'NEXT STEP' : 
                    (isAgent ? 'CREATE AGENT ACCOUNT' : 'CREATE CUSTOMER ACCOUNT')
                }}
            </span>
            <span v-else>Processing...</span>
            <svg v-if="!isLoading && currentStep == 1 && isAgent" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button> -->
        <button @click = "handleRegister()" :disabled="isLoading"
            class="w-full bg-primary hover:bg-primary/80 text-white font-semibold py-3 px-4 rounded-md transition-all duration-200 flex items-center justify-center disabled:opacity-50 shadow-lg">
            <span v-if="!isLoading">
                {{ 
                    currentStep == 1 && isAgent ? 'NEXT STEP' : 
                    (isAgent ? 'CREATE AGENT ACCOUNT' : 'CREATE CUSTOMER ACCOUNT')
                }}
            </span>
            <span v-else>Processing...</span>
            <svg v-if="!isLoading && currentStep == 1 && isAgent" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
       
    </form>
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

</template>
<style>
.vti__input {
    border-radius: 1rem !important;
}

.vue-tel-input {
    display: flex;
    border-radius: 6px;
    border: 1px solid #bbb;
    text-align: left;
    border-color: rgb(209 213 219 / var(--tw-border-opacity, 1));
    padding-top: 0.50rem;
    padding-bottom: 0.50rem;
}


</style>