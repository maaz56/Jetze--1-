<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import FileUploaderWithView from '@/components/common/FileUploaderWithView.vue';
import Label from "@/components/ui/label/Label.vue";
import Button from "@/components/ui/button/Button.vue";
import { ChevronsUpDown, Check } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import { VueTelInput } from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';
import { LucideBuilding, LucideHome } from 'lucide-vue-next';
import { Popover, PopoverTrigger, PopoverContent } from "@/components/ui/popover";
import { Command, CommandInput, CommandEmpty, CommandGroup, CommandItem, CommandList } from "@/components/ui/command";
import { useAuthStore } from '@/services/stores/auth';
import { FETCH_CURRENCIES, FETCH_CUSTOMER_DATA, UPDATE_CUSTOMER_DATA } from "@/services/store/actions.type";

const store = useStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref(null);
const isSubmitting = ref(false);

const isOpenCurrencyDropdown = ref(false);
const selectedCurrency = ref('');

const userDetail = ref({
    name: '',
    email: '',
    phone: '',
    address: '',
    companyName: '',
    preferredCurrency: '',
    logo: null,
    license: null,
    e_id: [],
});

const phoneValid = ref(true);

const phoneInputOption = {
    placeholder: "Enter Phone Number",
    maxlength: 15,
};

const dropdown = {
    showSearchBox: true,
    showFlags: true,
    width: "390px",
};

const currencies = computed(() => store.getters["currency/currencies"] || []);
const customerData = computed(() => store.getters["customer/customer"] || null);

const isAgent = computed(() => {
    return customerData.value?.role === 'agent' || !!userDetail.value.companyName;
});

function fetchCurrencies(event) {
    store.dispatch("currency/" + FETCH_CURRENCIES, {
        searchQuery: event.target.value,
    });
}

function handleUploadFile(files, key) {
    userDetail.value[key] = key === 'e_id' ? files : files[0] || null;
}

async function loadCustomerData() {
    const customerId = route.query.customer_id || route.params.id;

    if (!customerId) {
        error.value = "No customer ID provided.";
        loading.value = false;
        return;
    }

    try {
        await store.dispatch(`customer/${FETCH_CUSTOMER_DATA}`, { id: customerId });
        const data = customerData.value;

        if (data) {
            userDetail.value = {
                name: data.name || '',
                email: data.email || '',
                phone: data.phone || data.mobile || '',
                address: data.address || '',
                companyName: data.company_name || data.companyName || '',
                preferredCurrency: data.preferred_currency,
                logo: null,
                license: null,
                e_id: [],
            };
            selectedCurrency.value = data.preferred_currency;
        }
    } catch (err) {
        error.value = "Failed to load customer data.";
        console.error(err);
    } finally {
        loading.value = false;
    }
}

async function handleUpdate() {
    isSubmitting.value = true;
    
    try {
        // Replace this with your actual update API call
        store.dispatch('customer/' + UPDATE_CUSTOMER_DATA, {
            id: customerData.value.id,
            name: userDetail.value.name,
            email: userDetail.value.email,
            phone: userDetail.value.phone,
            address: userDetail.value.address,
            companyName: userDetail.value.companyName,
            preferredCurrency: selectedCurrency.value || 'PKR',
            logo: userDetail.value.logo,
            license: userDetail.value.license,
            e_id: userDetail.value.e_id,    
        }).then(() => {
            router.push({ name: 'Customers' });
        });

    } catch (err) {
        console.error("Update failed:", err);
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(loadCustomerData);
watch(() => route.query.customer_id, loadCustomerData);
</script>

<template>
    <div class="max-w-2xl mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6">Update Customer Profile</h2>

        <div v-if="loading" class="text-center py-10">Loading...</div>
        <div v-else-if="error" class="text-red-600 text-center py-10">{{ error }}</div>

        <form v-else @submit.prevent="handleUpdate" class="space-y-6">
            <!-- Full Name -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <input v-model="userDetail.name" type="text" placeholder="Full Name" required
                    class="w-full pl-10 pr-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary" />
            </div>

            <!-- Email -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <input v-model="userDetail.email" type="email" placeholder="Email" required
                    class="w-full pl-10 pr-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary" />
            </div>

            <!-- Phone -->
            <div>
                <VueTelInput
                class="pr-12 py-2 border rounded-xl focus:ring-2 focus:ring-primary w-full bg-white"
                    v-model="userDetail.phone"
                    :inputOptions="phoneInputOption"
                    :dropdownOptions="dropdown"
                    @validate="({ valid }) => phoneValid = valid"
                    mode="international"
                    :validCharactersOnly="true"
                />
                <p v-if="!phoneValid" class="text-red-500 text-xs mt-1">Invalid phone number</p>
            </div>

            <!-- Address -->
            <div class="relative">
                <LucideHome class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" />
                <input v-model="userDetail.address" type="text" placeholder="Address"
                    class="w-full pl-10 pr-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary" />
            </div>

            <!-- Company Name -->
            <div class="relative">
                <LucideBuilding class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" />
                <input v-model="userDetail.companyName" type="text" placeholder="Company Name (Optional)"
                    class="w-full pl-10 pr-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary" />
            </div>

            <!-- Currency -->
            <Popover v-model:open="isOpenCurrencyDropdown">
                <PopoverTrigger as-child>
                    <Button variant="outline" class="w-full bg-white justify-between py-6">
                        {{ selectedCurrency || "Select currency..." }}
                        <ChevronsUpDown class="ml-2 h-4 w-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-full p-0">
                    <Command>
                        <CommandInput @input="fetchCurrencies" placeholder="Search..." />
                        <CommandEmpty>No results.</CommandEmpty>
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

       

            <Button type="submit" :disabled="isSubmitting || !phoneValid" class="w-full">
                {{ isSubmitting ? 'Updating...' : 'Update Profile' }}
            </Button>
        </form>
    </div>
</template>