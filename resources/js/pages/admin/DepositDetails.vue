<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useStore } from "vuex";
import { useRoute, useRouter } from "vue-router";
import { ArrowLeft, UserIcon } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
  CHECK_PAYMENT_STATUS,
    FETCH_DEPOSIT_DETAILS,
    UPDATE_DEPOSIT_STATUS,
} from "@/services/store/actions.type";
import { ArrowLeftIcon, ReceiptIcon } from "lucide-vue-next";
import { Ban } from "lucide-vue-next";
import { upperCase } from "lodash";

const store = useStore();
const route = useRoute();
const loading = ref(true);
const error = ref(null);
const is_approved = ref();


const depositDetails = computed(() => store.getters["deposit/depositDetails"]);
const isApproved = computed(
    () => depositDetails.value.deposit.deposit_status === "approved",
);

function fetchDepostDetails() {
    if (route.query.deposit_id) {
        try {
            store.dispatch("deposit/" + FETCH_DEPOSIT_DETAILS, {
                DepositId: route.query.deposit_id,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}
watch(depositDetails, (newVal) => {
    if (depositDetails.value?.deposit?.payment_type === "abhipay-deposit") {
      checkPaymentStatus();
    }
});
function checkPaymentStatus() {
  store?.dispatch('payment/' + CHECK_PAYMENT_STATUS, {
    paymentMethod: depositDetails?.value?.deposit?.payment_type,
    deposit_id: depositDetails?.value?.deposit?.id,
  })
}
// function updateUserStatus(){
//     if (isApproved.value) {
//         depositDetails.value.deposit.deposit_status = "pending";
//     } else {
//         depositDetails.value.deposit.deposit_status = "approved";
//     }
//     try {
//         store.dispatch("deposit/" + UPDATE_DEPOSIT_STATUS, {
//             status: newStatus ? 1 : 0,
//             userId: route.query.user_id,
//         });
//     } catch (err) {
//         console.error("Failed to update user status:", err);
//         // Optionally, show an error message to the user
//     }
// }

async function updateUserStatus() {
    try {
        // Determine the new status based on the current checkbox state
        const newStatus = !isApproved.value;
        const updatedStatus = newStatus ? "approved" : "pending";

        // Update the local state
        depositDetails.value.deposit.deposit_status = updatedStatus;

        // Send the new status to the API
        await store.dispatch("deposit/" + UPDATE_DEPOSIT_STATUS, {
            status: newStatus ? 1 : 0, // Assuming 1 = approved, 0 = pending
            depositId: route.query.deposit_id,
        });

        //console.log("User status updated successfully.");
    } catch (err) {
        console.error("Failed to update user status:", err);

        // Optionally, revert the local state if the API call fails
        depositDetails.value.deposit.deposit_status = isApproved.value
            ? "approved"
            : "pending";

        // Show an error message to the user
        alert("An error occurred while updating the status. Please try again.");
    }
}

async function rejectDepositRequest(rejectionReason) {
    //console.log("Rejecting" + rejectionReason);
    try {
        // Update the local state to reflect rejection
        depositDetails.value.deposit.deposit_status = "rejected";
        depositDetails.value.deposit.rejection_reason = rejectionReason || null;

        // Send the rejection status to the API
        await store.dispatch("deposit/" + UPDATE_DEPOSIT_STATUS, {
            status: 2, // Assuming 2 = rejected
            depositId: route.query.deposit_id,
            rejectionReason: rejectionReason,
        });

        //console.log("Deposit request rejected successfully.");
    } catch (err) {
        console.error("Failed to reject the deposit request:", err);

        // Optionally, revert the local state if the API call fails
        depositDetails.value.deposit.deposit_status = "pending";

        // Show an error message to the user
        alert(
            "An error occurred while rejecting the deposit. Please try again.",
        );
    }
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};
onMounted(() => {
    fetchDepostDetails();
});
</script>

<template>
  <div class="min-h-screen p-4">
    <div class="max-w-full mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
          <Button
            @click="$router.push({ name: 'Dashboard' })"
            variant="outline"
            size="sm"
          >
            <ArrowLeft class="w-4 h-4 mr-1" />
            Back
          </Button>

          <h1 class="text-3xl font-medium leading-none tracking-tight text-gray-900">
            Deposit Details
          </h1>
        </div>
      </div>

      <!-- Deposit Info -->
      <div class="p-6 border rounded-lg" v-if="depositDetails?.deposit">
        <div class="flex items-start">
          <div class="w-32 h-32 bg-gray-200 rounded-lg mr-6 flex items-center justify-center">
            <ReceiptIcon class="h-24 w-24 text-gray-400" />
          </div>

          <div class="space-y-4 flex-1">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold text-gray-800">
                {{ depositDetails?.deposit?.receipt_reference }}
              </h2>

              <Button
                @click="
                  rejectDepositRequest(
                    'Request is rejected by the admin. Please contact the admin'
                  )
                "
                class="bg-red-100 hover:bg-red-200"
                size="sm"
              >
                <Ban class="w-4 h-4 text-red-500" />
              </Button>
            </div>

            <p class="text-sm text-gray-600">
              ID: {{ depositDetails?.deposit?.id }} | Status:
              <span
                :class="{
                  'text-yellow-700 bg-yellow-200 rounded-lg px-2 py-1':
                    depositDetails?.deposit?.deposit_status === 'pending',
                  'text-gray-600 bg-gray-200 rounded-lg px-2 py-1':
                    depositDetails?.deposit?.deposit_status === 'approved',
                  'text-red-500 bg-red-200 rounded-lg px-2 py-1':
                    depositDetails?.deposit?.deposit_status === 'rejected',
                  'uppercase': true,
                }"
              >
                {{ upperCase(depositDetails?.deposit?.deposit_status) }}
              </span>
            </p>

            <!-- Toggle Switch -->
            <div>
              <label class="inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="isApproved"
                  @change="updateUserStatus"
                  class="sr-only peer"
                />
                <div
                  class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"
                ></div>
              </label>
            </div>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="mt-6 grid grid-cols-3 gap-4">
          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-sm font-medium text-gray-500">Amount</h3>
            <p class="mt-1 text-xl font-semibold text-gray-900">
              Rs: {{ depositDetails?.deposit?.amount }}
            </p>
          </div>

          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-sm font-medium text-gray-500">Date</h3>
            <p class="mt-1 text-xl font-semibold text-gray-900">
              {{ formatDate(depositDetails?.deposit?.date) }}
            </p>
          </div>

          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-sm font-medium text-gray-500">Payment Source</h3>
            <p class="mt-1 text-xl font-semibold text-gray-900">
              {{ depositDetails?.deposit?.payment_type }}
            </p>
          </div>
         <div class="p-4 bg-gray-50 rounded-lg">
  <h3 class="text-sm font-medium text-gray-500">Payment Status</h3>

  <p class="mt-2">
    <span
      class="px-3 py-1 text-sm font-semibold rounded-full"
      :class="{
        'bg-yellow-100 text-yellow-700': depositDetails?.deposit?.t_status === 'pending',
        'bg-green-100 text-green-700': depositDetails?.deposit?.t_status === 'approved'
      }"
    >
      {{ depositDetails?.deposit?.t_status }}
    </span>
  </p>
</div>
        </div>

        <!-- Agent Details -->
        <div class="mt-8 border-t pt-6" v-if="depositDetails?.deposit?.agent">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Agent Details
          </h3>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <h4 class="text-sm font-medium text-gray-500">Agent Name</h4>
              <p class="mt-1 text-sm text-gray-900">
                {{ depositDetails?.deposit?.agent?.agent_data?.ceo_name || depositDetails?.deposit?.agent?.customer?.name }}
              </p>
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-500">Agent Email</h4>
              <p class="mt-1 text-sm text-gray-900">
                {{ depositDetails?.deposit?.agent?.agent_data?.ceo_email || depositDetails?.deposit?.agent?.customer?.email }}
              </p>
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-500">Agent Phone</h4>
              <p class="mt-1 text-sm text-gray-900">
                {{ depositDetails?.deposit?.agent?.agent_data?.ceo_contact || depositDetails?.deposit?.agent?.customer?.phone }}
              </p>
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-500">Agent Address</h4>
              <p class="mt-1 text-sm text-gray-900">
                {{ depositDetails?.deposit?.agent?.agent_data?.address || depositDetails?.deposit?.agent?.customer?.address }}
              </p>
            </div>
          </div>
        </div>

        <!-- Additional Details -->
        <div class="mt-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Additional Details
          </h3>
          <p class="text-sm text-gray-700">
            {{ depositDetails?.deposit?.additional_details }}
          </p>
        </div>

        <!-- Receipt Image -->
        <div class="mt-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Receipt Image
          </h3>
          <img
            v-if="depositDetails?.deposit?.receipt_image"
            :src="depositDetails?.deposit?.receipt_image"
            alt="Receipt"
            class="max-w-full h-auto rounded-lg shadow-md"
          />
          <p v-else class="text-sm text-gray-500">No receipt image uploaded.</p>
        </div>
      </div>

      <!-- Loading / Empty State -->
      <div v-else class="p-6 text-center">
        <LoaderIcon class="w-10 h-10 animate-spin mx-auto text-gray-500" />
        <p class="mt-2 text-gray-600">Loading deposit details…</p>
      </div>
    </div>
  </div>
</template>