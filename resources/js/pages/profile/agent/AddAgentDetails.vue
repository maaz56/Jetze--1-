<script setup>
import { computed, onMounted, ref } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import InputMessage from "@/components/ui/inputMessage.vue";
import { useStore } from "vuex";
import { Textarea } from "@/components/ui/textarea";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon } from "lucide-vue-next";
import { useAuthStore } from "@/services/stores/auth";
import { SAVE_AGENT_DATA } from "@/services/store/actions.type";
import { useRouter } from "vue-router";

const store = useStore();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const router = useRouter();

const companyName = ref("");
const govtNumber = ref("");
const mobile = ref("");
const phone = ref("");
const ceoName = ref("");
const ceoContact = ref("");
const ceoEmail = ref("");
const companyEmail = ref("");
const address = ref("");
const logo = ref(null);
const e_id = ref(null);
const license = ref(null);
const validationErrors = ref([]);

const handleLogoChange = (event) => {
  const file = event.target.files[0];
  if (file && !file.type.startsWith("image/")) {
    validationErrors.value.push("Company Logo must be an image file.");
    logo.value = null;
  } else {
    logo.value = file;
  }
};

const handleEid = (event) => {
  const file = event.target.files[0];
  if (file && !file.type.startsWith("image/")) {
    validationErrors.value.push("E-ID must be an image file.");
    e_id.value = null;
  } else {
    e_id.value = file;
  }
};

const handleTraderLicence = (event) => {
  const file = event.target.files[0];
  if (file && !file.type.startsWith("image/")) {
    validationErrors.value.push("Trader Licence must be an image file.");
    license.value = null;
  } else {
    license.value = file;
  }
};

function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePhone(number) {
  const re = /^\+?[\d\s-]{8,15}$/;
  return re.test(number);
}

function handleAddAgent() {
  let errors = [];

  // Required field validations
  if (!companyName.value.trim()) {
    errors.push("Company Name is required.");
  }
  if (!mobile.value.trim()) {
    errors.push("Mobile Number is required.");
  }
  if (!ceoName.value.trim()) {
    errors.push("CEO Name is required.");
  }
  if (!address.value.trim()) {
    errors.push("Address is required.");
  }
  // if (!e_id.value) {
  //   errors.push("E-ID image is required.");
  // }
  // if (!license.value) {
  //   errors.push("Trader Licence image is required.");
  // }
//   if (!logo.value) {
//     errors.push("Company Logo image is required.");
//   }

  // Format validations
  if (mobile.value && !validatePhone(mobile.value)) {
    errors.push("Mobile Number must be a valid phone number (8-15 digits).");
  }

  if (errors.length > 0) {
    validationErrors.value = errors;
    return;
  }

  const agentData = new FormData();
  agentData.append("company_name", companyName.value);
  //agentData.append("govt_number", govtNumber.value);
  agentData.append("mobile", mobile.value);
  //agentData.append("phone", phone.value);
  agentData.append("ceo_name", ceoName.value);
  //agentData.append("ceo_contact", ceoContact.value);
  //agentData.append("ceo_email", ceoEmail.value);
  //agentData.append("company_email", companyEmail.value);
  agentData.append("agent_id", user?.value.id);
  agentData.append("address", address.value);
  if (logo.value) {
    agentData.append("logo", logo.value);
  }
  if (e_id.value) {
    agentData.append("e_id", e_id.value);
  }
  if (license.value) {
    agentData.append("license", license.value);
  }

  validationErrors.value = [];

  store.dispatch("user/" + SAVE_AGENT_DATA, agentData).then(() => {
    //console.log("Agent data saved successfully");
    router.push({ name: "AgentDashboard" });

    // Reset form fields
    companyName.value = "";
    //govtNumber.value = "";
    mobile.value = "";
    //phone.value = "";
   // ceoName.value = "";
   // ceoContact.value = "";
   // ceoEmail.value = "";
   // companyEmail.value = "";
    address.value = "";
    logo.value = null;
    e_id.value = null;
    license.value = null;
  }).catch((error) => {
    validationErrors.value = ["Failed to save agent data. Please try again."];
    console.error(error);
  });
}
</script>

<template>
  <div
    class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 flex items-center justify-center p-4 sm:p-6 lg:p-8"
  >
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden w-full max-w-6xl">
      <div class="flex flex-col lg:flex-row">
        <div class="w-full lg:w-3/5 p-6 sm:p-8 lg:p-10">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            Welcome to the  Jetze
          </h1>
          <h3>{{ user?.email }}</h3>

          <p class="text-gray-600 dark:text-gray-400 mb-6">
            Fill out the form below to add agent details.
          </p>
          <div class="mb-6" v-if="validationErrors.length > 0">
            <ul
              class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive"
            >
              <li v-for="error in validationErrors" :key="error">
                {{ error }}
              </li>
            </ul>
          </div>

          <form @submit.prevent="handleAddAgent" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="space-y-2">
                <Label for="companyName" class="text-sm font-medium text-gray-700 dark:text-gray-300"
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

              <!-- <div class="space-y-2">
                <Label for="govtNumber" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >Govt No / IATA Number</Label
                >
                <Input
                  v-model="govtNumber"
                  id="govtNumber"
                  type="text"
                  placeholder="Enter Govt No or IATA Number"
                  class="w-full"
                />
              </div> -->

              <div class="space-y-2">
                <Label for="mobile" class="text-sm font-medium text-gray-700 dark:text-gray-300"
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

              <!-- <div class="space-y-2">
                <Label for="phone" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >Phone</Label
                >
                <Input
                  v-model="phone"
                  id="phone"
                  type="text"
                  placeholder="Enter phone number"
                  class="w-full"
                />
              </div> -->

              <div class="space-y-2">
                <Label for="ceoName" class="text-sm font-medium text-gray-700 dark:text-gray-300"
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

              <!-- <div class="space-y-2">
                <Label for="ceoContact" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >CEO Contact</Label
                >
                <Input
                  v-model="ceoContact"
                  id="ceoContact"
                  type="text"
                  placeholder="Enter CEO's contact number"
                  class="w-full"
                />
              </div> -->

              <!-- <div class="space-y-2">
                <Label for="ceoEmail" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >CEO Email</Label
                >
                <Input
                  v-model="ceoEmail"
                  id="ceoEmail"
                  type="email"
                  placeholder="Enter CEO's email"
                  class="w-full"
                />
              </div> -->

              <!-- <div class="space-y-2">
                <Label for="companyEmail" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >Company Email</Label
                >
                <Input
                  v-model="companyEmail"
                  id="companyEmail"
                  type="email"
                  placeholder="Enter company email"
                  class="w-full"
                />
              </div> -->

              <div class="space-y-2">
                <div class="grid gap-2">
                  <Label for="e_id" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >E-ID
                    <span class="text-xs">(Must be uploaded in any image format.)</span></Label
                  >
                </div>
                <Input
                  class="w-full"
                  accept="image/*"
                  id="e_id"
                  type="file"
                  @change="handleEid"
                />
              </div>

              <div class="space-y-2">
                <div class="grid gap-2">
                  <Label for="license" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Trader Licence
                    <span class="text-xs">(Only in image format.)</span></Label
                  >
                </div>
                <Input
                  class="w-full"
                  accept="image/*"
                  id="license"
                  type="file"
                  @change="handleTraderLicence"
                />
              </div>

              <div class="space-y-2">
                <div class="grid gap-2">
                  <Label for="logo" class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Company Logo</Label
                  >
                </div>
                <Input
                  class="w-full"
                  accept="image/*"
                  id="logo"
                  type="file"
                  @change="handleLogoChange"
                />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="address" class="text-sm font-medium text-gray-700 dark:text-gray-300"
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
              Submit Agent Details
            </Button>
          </form>
        </div>

        <div
          class="w-full lg:w-2/5 bg-gradient-to-br from-primary to-gray-600 p-6 flex items-center justify-center"
        >
          <div class="text-center">
            <UserPlusIcon class="w-24 h-24 text-white mb-4 mx-auto" />
            <h2 class="text-2xl font-bold text-white mb-2">Welcome New Agent</h2>
            <p class="text-gray-100">Join our network and expand your business horizons.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>