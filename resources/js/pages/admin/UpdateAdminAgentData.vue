```vue
<script setup>
import { computed, onMounted, ref, reactive, watch } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import InputMessage from "@/components/ui/inputMessage.vue";
import { useStore } from "vuex";
import { Textarea } from "@/components/ui/textarea";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon, EyeIcon, EyeOffIcon } from "lucide-vue-next";
import { useAuthStore } from "@/services/stores/auth";
import {
    SAVE_AGENT_DATA,
    FETCH_AGENT_DATA,
    UPDATE_AGENT_DATA,
} from "@/services/store/actions.type";
import { useRouter, useRoute } from "vue-router";

const store = useStore();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const agentData = computed(() => store.getters["user/agentData"]);
const router = useRouter();
const route = useRoute();

const isEditing = ref(false);
const agentId = ref(null);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = reactive({
    companyName: agentData.value != null ? agentData.value.agent_data?.company_name : "",
    govtNumber: agentData.value != null ? agentData.value.agent_data?.govt_number : "",
    mobile: agentData.value != null ? agentData.value.agent_data?.mobile : "",
    phone: agentData.value != null ? agentData.value.agent_data?.phone : "",
    ceoName: agentData.value != null ? agentData.value.agent_data?.ceo_name : "",
    ceoContact: agentData.value != null ? agentData.value.agent_data?.ceo_contact : "",
    ceoEmail: agentData.value != null ? agentData.value.agent_data?.ceo_email : "",
    companyEmail: agentData.value != null ? agentData.value.agent_data?.company_email : "",
    address: agentData.value != null ? agentData.value.agent_data?.address : "",
    logo: agentData.value != null ? agentData.value.agent_data?.logo : "",
    e_id: agentData.value != null ? agentData.value.agent_data?.e_id : "",
    trade_license: agentData.value != null ? agentData.value.agent_data?.trade_license : "",
    password: "",
    confirmPassword: "",
});

const logoPreview = ref(null);
const trade_license_preview = ref(null);
const e_id_preview = ref(null);
const validationErrors = ref([]);

onMounted(() => {
    agentId.value = route.query.user_id;
    isEditing.value = !!agentId.value;
    //console.log("isEditing", isEditing.value);
    //console.log("agentId", agentId.value);
    //console.log("agentData", agentData.value);

    if (isEditing.value || agentData.value == null) {
        fetchAgentData();
    }
});

function fetchAgentData() {
    try {
        store.dispatch(`user/${FETCH_AGENT_DATA}`, {
            userId: agentId.value,
        });
    } catch (error) {
        console.error("Error fetching agent data:", error);
        validationErrors.value = [
            "Failed to load agent data. Please try again.",
        ];
    }
}

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleTraderLicence = (event) => {
  const file = event.target.files[0];
    if (file) {
        form.trade_license = file;

        trade_license_preview.value = URL.createObjectURL(file);
    }
  
};
const handleEid = (event) => {
  const file = event.target.files[0];
    if (file) {
        form.e_id = file;
        
        e_id_preview.value = URL.createObjectURL(file);
    }
  
};

function validateForm() {
    let errors = [];

    if (!form.companyName) errors.push("Company Name field is required.");
    if (!isEditing.value && !form.password) errors.push("Password field is required.");
    if (!isEditing.value && !form.confirmPassword) errors.push("Confirm Password field is required.");
    if (!isEditing.value && form.password !== form.confirmPassword) {
        errors.push("Passwords do not match.");
    }

    validationErrors.value = errors;
    return errors.length === 0;
}

function handleSubmit() {
    if (!validateForm()) return;

    const formData = new FormData();
    formData.set("company_name", form.companyName);
    formData.append("mobile", form.phone);
    formData.append("phone", form.phone);
    formData.append("ceo_name", form.ceoName);
    formData.append("ceo_contact", form.ceoContact);
    formData.append("ceo_email", form.ceoEmail);
    formData.append("company_email", form.companyEmail);
    formData.append("agent_id", route.query.user_id);
    formData.append("address", form.address);

        formData.append("password", form.password);
    if (form.logo) {
        formData.append("logo", form.logo);
        formData.append("e_id", form.e_id);
        formData.append("license", form.trade_license);
    }

    store
        .dispatch(`user/${UPDATE_AGENT_DATA}`, formData)
        .then((isSaved) => {
            //console.log("Agent data saved successfully", isSaved);
                fetchAgentData();
                router.push({ name: "Users" });
            
        })
        .catch((error) => {
            console.error("Error saving agent data:", error);
            validationErrors.value = ["Failed to save agent data. Please try again."];
        });
}

watch(
    agentData,
    (newAgentData) => {
        if (newAgentData?.agent_data) {
            form.companyName = newAgentData.agent_data.company_name || "";
            form.govtNumber = newAgentData.agent_data.govt_number || "";
            form.mobile = newAgentData.agent_data.mobile || "";
            form.phone = newAgentData.agent_data.phone || "";
            form.ceoName = newAgentData.agent_data.ceo_name || "";
            form.ceoContact = newAgentData.agent_data.ceo_contact || "";
            form.ceoEmail = newAgentData.agent_data.ceo_email || "";
            form.companyEmail = newAgentData.agent_data.company_email || "";
            form.address = newAgentData.agent_data.address || "";
            form.logo = newAgentData.agent_data.logo || "";
            logoPreview.value = newAgentData.agent_data.logo || null;
        }
    },
    { immediate: true }
);
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden w-full max-w-6xl">
            <div class="flex flex-col lg:flex-row">
                <div class="w-full lg:w-3/5 p-6 sm:p-8 lg:p-10">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        {{ isEditing ? "Update" : "Add" }} Agent Details
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ isEditing ? "Update" : "Fill out" }} the form below
                        to {{ isEditing ? "edit" : "add" }} agent details.
                    </p>

                    <div v-if="validationErrors.length > 0" class="mb-6">
                        <ul class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                            <li v-for="error in validationErrors" :key="error">
                                {{ error }}
                            </li>
                        </ul>
                    </div>

                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-1 gap-6">
                            <div class="space-y-2">
                                <Label for="companyName" class="text-sm font-medium text-gray-700 dark:text-gray-300">Company Name</Label>
                                <Input v-model="form.companyName" id="companyName" type="text" placeholder="Enter company name" class="w-full" />
                            </div>

                            <div class="space-y-2">
                                <Label for="companyEmail" class="text-sm font-medium text-gray-700 dark:text-gray-300">Company Email</Label>
                                <Input v-model="form.companyEmail" id="companyEmail" type="email" placeholder="Enter company email" class="w-full" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</Label>
                                <div class="relative">
                                    <Input :type="showPassword ? 'text' : 'password'" v-model="form.password" id="password" placeholder="••••••••" class="w-full pr-10" />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <EyeIcon v-if="!showPassword" class="h-5 w-5 text-gray-500" />
                                        <EyeOffIcon v-else class="h-5 w-5 text-gray-500" />
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="confirmPassword" class="text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</Label>
                                <div class="relative">
                                    <Input :type="showConfirmPassword ? 'text' : 'password'" v-model="form.confirmPassword" id="confirmPassword" placeholder="••••••••" class="w-full pr-10" />
                                    <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <EyeIcon v-if="!showConfirmPassword" class="h-5 w-5 text-gray-500" />
                                        <EyeOffIcon v-else class="h-5 w-5 text-gray-500" />
                                    </button>
                                </div>
                            </div>

                            <!-- <div class="space-y-2">
                                <Label for="mobile" class="text-sm font-medium text-gray-700 dark:text-gray-300">Mobile</Label>
                                <Input v-model="form.mobile" id="mobile" type="text" placeholder="Enter mobile number" class="w-full" />
                            </div> -->

                            <div class="space-y-2">
                                <Label for="phone" class="text-sm font-medium text-gray-700 dark:text-gray-300">Phone</Label>
                                <Input v-model="form.phone" id="phone" type="text" placeholder="Enter phone number" class="w-full" />
                            </div>

                            <div class="space-y-2">
                                <Label for="ceoName" class="text-sm font-medium text-gray-700 dark:text-gray-300">CEO Name</Label>
                                <Input v-model="form.ceoName" id="ceoName" type="text" placeholder="Enter CEO's name" class="w-full" />
                            </div>

                            <!-- <div class="space-y-2">
                                <Label for="ceoContact" class="text-sm font-medium text-gray-700 dark:text-gray-300">CEO Contact</Label>
                                <Input v-model="form.ceoContact" id="ceoContact" type="text" placeholder="Enter CEO's contact number" class="w-full" />
                            </div>

                            <div class="space-y-2">
<Label for="ceoEmail" class="text-sm font-medium text-gray-700 dark:text-gray-300">
  CEO Email
</Label>
                                <Input v-model="form.ceoEmail" id="ceoEmail" type="email" placeholder="Enter CEO's email" class="w-full" />
                            </div> -->

                            
                        </div>
<div class="flex gap-4">
  <!-- Company Logo -->
  <div class="space-y-2">
    <Label for="logo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Company Logo</Label>
    <Input class="w-full" id="logo" type="file" @change="handleLogoChange" />
    <img
      v-if="logoPreview"
      :src="logoPreview"
      alt="Company Logo Preview"
      class="mt-2 h-20 w-auto rounded"
    />
    <img
      v-else-if="form.logo"
      :src="form.logo"
      alt="Current Logo"
      class="mt-2 h-20 w-auto rounded"
    />
  </div>

  <!-- Trade License -->
  <div class="space-y-2">
    <Label for="trader_license" class="text-sm font-medium text-gray-700 dark:text-gray-300">Trade License</Label>
    <Input class="w-full" id="trader_license" type="file" @change="handleTraderLicence" />
    <img
      v-if="trade_license_preview"
      :src="trade_license_preview"
      alt="Trade License Preview"
      class="mt-2 h-20 w-auto rounded"
    />
    <img
      v-else-if="form.trade_license"
      :src="form.trade_license"
      alt="Current Trade License"
      class="mt-2 h-20 w-auto rounded"
    />
  </div>

  <!-- Emirates ID -->
  <div class="space-y-2">
    <Label for="e_id" class="text-sm font-medium text-gray-700 dark:text-gray-300">Emirates ID</Label>
    <Input class="w-full" id="e_id" type="file" @change="handleEid" />
    <img
      v-if="e_id_preview"
      :src="e_id_preview"
      alt="Emirates ID Preview"
      class="mt-2 h-20 w-auto rounded"
    />
    <img
      v-else-if="form.e_id"
      :src="form.e_id"
      alt="Current Emirates ID"
      class="mt-2 h-20 w-auto rounded"
    />
  </div>
</div>

                        <div class="space-y-2">
                            <Label for="address" class="text-sm font-medium text-gray-700 dark:text-gray-300">Address</Label>
                            <Textarea v-model="form.address" id="address" placeholder="Enter your address" rows="3" class="w-full" />
                        </div>

                        <Button type="submit" class="w-full bg-primary hover:bg-primary/50 text-white font-semibold py-3 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
                            <SaveIcon class="w-5 h-5 mr-2" />
                            {{ isEditing ? "Update" : "Submit" }} Agent Details
                        </Button>
                    </form>
                </div>

                <div class="w-full lg:w-2/5 bg-primary p-6 flex items-center justify-center">
                    <div class="text-center">
                        <UserPlusIcon class="w-24 h-24 text-white mb-4 mx-auto" />
                        <h2 class="text-2xl font-bold text-white mb-2">
                            {{ isEditing ? "Update Agent Info" : "Welcome New Agent" }}
                        </h2>
                        <p class="text-blue-100">
                            {{ isEditing ? "Keep your information up to date." : "Join our network and expand your business horizons." }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```