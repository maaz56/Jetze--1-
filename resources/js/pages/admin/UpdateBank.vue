<template>
  <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg border p-6 sm:p-8">
      <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Update Bank</h1>
        <button
          type="button"
          @click="router.push({ name: 'Banks' })"
          class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-4"
        >
          Back
        </button>
      </div>

      <div v-if="isLoading" class="py-12 text-center text-sm text-gray-500">
        Loading bank details...
      </div>

      <div v-else-if="!selectedBank" class="py-12 text-center text-sm text-gray-500">
        Bank details not found.
      </div>

      <form v-else @submit.prevent="submitForm" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="bankName" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
            <input
              type="text"
              id="bankName"
              v-model="form.bankName"
              placeholder="Enter bank name"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
          </div>

          <div>
            <label for="accountTitle" class="block text-sm font-medium text-gray-700 mb-1">Account Title</label>
            <input
              type="text"
              id="accountTitle"
              v-model="form.accountTitle"
              placeholder="Enter account title"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
          </div>

          <div>
            <label for="accountNumber" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
            <input
              type="text"
              id="accountNumber"
              v-model="form.accountNumber"
              placeholder="Enter account number"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
          </div>

          <div>
            <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
            <input
              type="text"
              id="currency"
              v-model="form.currency"
              placeholder="Enter currency (e.g., USD)"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
              required
            />
          </div>

          <div class="md:col-span-2">
            <label for="iban" class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
            <input
              type="text"
              id="iban"
              v-model="form.iban"
              placeholder="Enter IBAN"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
            />
          </div>

          <div>
            <label for="isActive" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              id="isActive"
              v-model="form.is_active"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
            >
              <option :value="true">Active</option>
              <option :value="false">Inactive</option>
            </select>
          </div>
        </div>

        <div class="space-y-3">
          <label for="bankLogo" class="text-sm font-medium text-gray-700">Bank Logo</label>
          <div v-if="selectedBank.logo_path" class="flex items-center gap-3">
            <img :src="selectedBank.logo_path" :alt="`${selectedBank.bank_name} logo`" class="w-14 h-14 object-contain rounded border" />
            <span class="text-sm text-gray-500">Current logo</span>
          </div>
          <input
            class="w-full block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
            id="bankLogo"
            type="file"
            @change="handleFileUpload"
          />
        </div>

        <div>
          <button
            type="submit"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-gray-700 focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-primary"
            :disabled="isSubmitting"
          >
            <span v-if="isSubmitting">Processing...</span>
            <span v-else>Update Bank</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { FETCH_BANKS, UPDATE_BANK } from "@/services/store/actions.type";
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";

const route = useRoute();
const router = useRouter();
const store = useStore();

const bankLogo = ref(null);
const isSubmitting = ref(false);
const hasLoaded = ref(false);

const form = reactive({
  bankName: "",
  accountTitle: "",
  accountNumber: "",
  currency: "",
  iban: "",
  is_active: true,
});

const banks = computed(() => store.getters["bank/banks"] || []);
const isLoading = computed(() => store.getters["bank/isLoading"] && !hasLoaded.value);
const selectedBank = computed(() => {
  return banks.value.find((bank) => Number(bank.id) === Number(route.params.id));
});

function fillForm(bank) {
  if (!bank) return;

  form.bankName = bank.bank_name || "";
  form.accountTitle = bank.account_title || "";
  form.accountNumber = bank.account_number || "";
  form.currency = bank.currency || "";
  form.iban = bank.iban || "";
  form.is_active = Boolean(bank.is_active);
}

function handleFileUpload(event) {
  bankLogo.value = event.target.files[0] || null;
}

async function loadBank() {
  await store.dispatch("bank/" + FETCH_BANKS);
  fillForm(selectedBank.value);
  hasLoaded.value = true;
}

async function submitForm() {
  isSubmitting.value = true;

  try {
    const bankData = new FormData();
    bankData.append("id", route.params.id);
    bankData.append("bankName", form.bankName);
    bankData.append("accountTitle", form.accountTitle);
    bankData.append("accountNumber", form.accountNumber);
    bankData.append("currency", form.currency);
    bankData.append("iban", form.iban || "");
    bankData.append("is_active", form.is_active ? "1" : "0");

    if (bankLogo.value) {
      bankData.append("logo", bankLogo.value);
    }

    const response = await store.dispatch("bank/" + UPDATE_BANK, bankData);
    if (response) {
      router.push({ name: "Banks" });
    }
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(() => {
  loadBank();
});
</script>
