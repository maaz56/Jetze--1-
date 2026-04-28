<script setup>
import { computed, onMounted, ref, reactive } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import InputMessage from "@/components/ui/inputMessage.vue";
import { useStore } from "vuex";
import { Textarea } from "@/components/ui/textarea";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon } from "lucide-vue-next";
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

const companyName = ref(
    agentData.value != null ? agentData.value.agent_data.company_name : "",
);
const govtNumber = ref(
    agentData.value != null ? agentData.value.agent_data.govt_number : "",
);
const mobile = ref(
    agentData.value != null ? agentData.value.agent_data.mobile : "",
);
const phone = ref(
    agentData.value != null ? agentData.value.agent_data.phone : "",
);
const ceoName = ref(
    agentData.value != null ? agentData.value.agent_data.ceo_name : "",
);
const ceoContact = ref(
    agentData.value != null ? agentData.value.agent_data.ceo_contact : "",
);
const ceoEmail = ref(
    agentData.value != null ? agentData.value.agent_data.ceo_email : "",
);
const companyEmail = ref(
    agentData.value != null ? agentData.value.agent_data.company_email : "",
);
const address = ref(
    agentData.value != null ? agentData.value.agent_data.address : "",
);
const logo = ref(
    agentData.value != null ? agentData.value.agent_data.logo : "",
);
const logoPreview = ref(null);
const validationErrors = ref([]);

// const companyName = ref('');
// const govtNumber = ref('');
// const mobile = ref('');
// const phone = ref('');
// const ceoName = ref('');
// const ceoContact = ref('');
// const ceoEmail = ref('');
// const companyEmail = ref('');
// const address = ref('');
// const logo = ref(null); // Assuming file input for logo
// const validationErrors = ref([]);

// onMounted(async () => {
//     agentId.value = route.query.user_id;
//     isEditing.value = !!agentId.value;

//     if (isEditing.value) {
//          fetchAgentData();
//     }
// });

onMounted(() => {
    agentId.value = route.query.user_id;
    isEditing.value = !!agentId.value;

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
        logo.value = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

function validateForm() {
    let errors = [];

    if (!companyName.value) errors.push("Company Name field is required.");
    if (!govtNumber.value) errors.push("Government Number field is required.");
    if (!mobile.value) errors.push("Mobile Number field is required.");

    validationErrors.value = errors;
    return errors.length === 0;
}

function handleSubmit() {
    if (!validateForm()) return;

    var formData = new FormData();
    formData.set("company_name", companyName.value);
    formData.append("govt_number", govtNumber.value);
    formData.append("mobile", mobile.value);
    formData.append("phone", phone.value);
    formData.append("ceo_name", ceoName.value);
    formData.append("ceo_contact", ceoContact.value);
    formData.append("ceo_email", ceoEmail.value);
    formData.append("company_email", companyEmail.value);
    formData.append("agent_id", route.query.user_id);
    formData.append("address", address.value);
    if (logo.value) {
        formData.append("logo", logo.value); // Append the logo file if it exists
    }
    ////console.log(agentData.get("company_name"));

    // for (let pair of agentData.entries()) {
    //     //console.log(pair[0] + ": " + pair[1]);
    // }
    const isSaved = store
        .dispatch("user/" + UPDATE_AGENT_DATA, formData)
        .then(() => {
            //console.log("Agent data saved successfully", isSaved);

            if (isSaved) {
                router.push({ name: "AgentDashboard" });
            }
            // Reset form fields
        });
}
</script>

<template>
    <div
        class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8"
    >
        <div
            class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden w-full max-w-6xl"
        >
            <div class="flex flex-col lg:flex-row">
                <div class="w-full lg:w-3/5 p-6 sm:p-8 lg:p-10">
                    <h1
                        class="text-3xl font-bold text-gray-900 dark:text-white mb-2"
                    >
                        {{ isEditing ? "Update" : "Add" }} Agent Details
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ isEditing ? "Update" : "Fill out" }} the form below
                        to {{ isEditing ? "edit" : "add" }} agent details.
                    </p>

                    <div v-if="validationErrors.length > 0" class="mb-6">
                        <ul
                            class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive"
                        >
                            <li v-for="error in validationErrors" :key="error">
                                {{ error }}
                            </li>
                        </ul>
                    </div>

                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <Label
                                    for="companyName"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Company Name</Label
                                >
                                <Input
                                    v-model="companyName"
                                    id="companyName"
                                    type="text"
                                    placeholder="Enter company name"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="govtNumber"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Govt No / IATA Number</Label
                                >
                                <Input
                                    v-model="govtNumber"
                                    id="govtNumber"
                                    type="text"
                                    placeholder="Enter Govt No or IATA Number"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="mobile"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Mobile</Label
                                >
                                <Input
                                    v-model="mobile"
                                    id="mobile"
                                    type="text"
                                    placeholder="Enter mobile number"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="phone"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Phone</Label
                                >
                                <Input
                                    v-model="phone"
                                    id="phone"
                                    type="text"
                                    placeholder="Enter phone number"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="ceoName"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >CEO Name</Label
                                >
                                <Input
                                    v-model="ceoName"
                                    id="ceoName"
                                    type="text"
                                    placeholder="Enter CEO's name"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="ceoContact"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >CEO Contact</Label
                                >
                                <Input
                                    v-model="ceoContact"
                                    id="ceoContact"
                                    type="text"
                                    placeholder="Enter CEO's contact number"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="ceoEmail"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >CEO Email</Label
                                >
                                <Input
                                    v-model="ceoEmail"
                                    id="ceoEmail"
                                    type="email"
                                    placeholder="Enter CEO's email"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="companyEmail"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Company Email</Label
                                >
                                <Input
                                    v-model="companyEmail"
                                    id="companyEmail"
                                    type="email"
                                    placeholder="Enter company email"
                                    class="w-full"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="grid gap-2">
                                <Label
                                    for="logo"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Company Logo</Label
                                >
                            </div>
                            <Input
                                class="w-full"
                                id="logo"
                                type="file"
                                @change="handleLogoChange"
                            />
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                alt="Company Logo Preview"
                                class="mt-2 h-20 w-auto"
                            />
                            <img
                                v-else
                                :src="logo"
                                alt="Company Logo Preview"
                                class="mt-2 h-20 w-auto"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="address"
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Address</Label
                            >
                            <Textarea
                                v-model="address"
                                id="address"
                                placeholder="Enter your address"
                                rows="3"
                                class="w-full"
                            />
                        </div>

                        <Button
                            type="submit"
                            class="w-full bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-3 rounded-lg transition duration-300 ease-in-out transform hover:scale-105"
                        >
                            <SaveIcon class="w-5 h-5 mr-2" />
                            {{ isEditing ? "Update" : "Submit" }} Agent Details
                        </Button>
                    </form>
                </div>

                <div
                    class="w-full lg:w-2/5 bg-gradient-to-br from-primary to-gray-600 p-6 flex items-center justify-center"
                >
                    <div class="text-center">
                        <UserPlusIcon
                            class="w-24 h-24 text-white mb-4 mx-auto"
                        />
                        <h2 class="text-2xl font-bold text-white mb-2">
                            {{
                                isEditing
                                    ? "Update Agent Info"
                                    : "Welcome New Agent"
                            }}
                        </h2>
                        <p class="text-gray-100">
                            {{
                                isEditing
                                    ? "Keep your information up to date."
                                    : "Join our network and expand your business horizons."
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
