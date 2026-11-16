<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import {
  ArrowLeft,
  Building2,
  CalendarDays,
  CheckCircle2,
  Clock,
  Download,
  ExternalLink,
  FileText,
  Hash,
  ImageOff,
  LoaderIcon,
  Mail,
  MapPin,
  Phone,
  ReceiptText,
  ShieldCheck,
  User,
  Wallet,
  XCircle,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { formatAmount } from "@/lib/utils";
import {
  CHECK_PAYMENT_STATUS,
  FETCH_AGENT_LEDGER,
  FETCH_DEPOSIT_DETAILS,
  UPDATE_DEPOSIT_STATUS,
} from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();

const error = ref(null);
const isLoading = ref(true);
const isSubmitting = ref(false);

// Review dialog (single entry point for approve / reject)
const isReviewOpen = ref(false);
const decision = ref("approved"); // 'approved' | 'rejected'
const rejectionReason = ref("");
const DEFAULT_REJECTION_REASON = "Request is rejected by the admin. Please contact the admin";

const depositDetails = computed(() => store.getters["deposit/depositDetails"]);
const ledger = computed(() => store.getters["ledger/agentLedgerData"]);

// Ignore a deposit left in the store from a previously viewed page.
const deposit = computed(() => {
  const current = depositDetails.value?.deposit;
  return current && String(current.id) === String(route.query.deposit_id) ? current : null;
});
const status = computed(() => deposit.value?.deposit_status || "pending");
const agent = computed(() => deposit.value?.agent || null);

// ---------------------------------------------------------------- customer
const customer = computed(() => {
  const a = agent.value;
  if (!a) return null;
  const data = a.agent_data;
  const c = a.customer;

  return {
    uid: c?.customer_uid || data?.agent_uid || null,
    name: data?.ceo_name || data?.company_name || c?.name || a.name || "—",
    company: data?.company_name || c?.company_name || null,
    email: data?.ceo_email || c?.email || a.email || "—",
    phone: data?.ceo_contact || c?.phone || "—",
    address: data?.address || c?.address || "—",
    role: a.role || null,
  };
});

// ----------------------------------------------------------------- balance
const balanceAed = computed(() => {
  const value = Number(ledger.value?.balance);
  return Number.isFinite(value) ? value : null;
});
const depositAed = computed(() => {
  const value = Number(deposit.value?.aed_amount);
  return Number.isFinite(value) ? value : null;
});
// An approved deposit is already included in the ledger balance.
const balanceBeforeDeposit = computed(() =>
  balanceAed.value !== null && depositAed.value !== null && status.value === "approved"
    ? balanceAed.value - depositAed.value
    : balanceAed.value,
);
const balanceAfterApproval = computed(() =>
  balanceBeforeDeposit.value !== null && depositAed.value !== null
    ? balanceBeforeDeposit.value + depositAed.value
    : null,
);

// -------------------------------------------------------------- formatting
const aed = (value) => formatAmount(value, "AED");
const money = (m, fallbackAmount, fallbackCurrency = "AED") =>
  formatAmount(m?.amount ?? fallbackAmount, m?.currency ?? fallbackCurrency);
const formatDate = (value) =>
  value
    ? new Date(value).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
    : "—";
const formatDateTime = (value) =>
  value
    ? new Date(value).toLocaleString("en-GB", {
        day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit",
      })
    : "—";

const statusStyle = computed(
  () =>
    ({
      pending: { label: "Pending review", cls: "bg-amber-50 text-amber-700 ring-amber-200", icon: Clock },
      approved: { label: "Approved", cls: "bg-emerald-50 text-emerald-700 ring-emerald-200", icon: CheckCircle2 },
      rejected: { label: "Rejected", cls: "bg-red-50 text-red-700 ring-red-200", icon: XCircle },
    })[status.value] || { label: status.value, cls: "bg-gray-100 text-gray-700 ring-gray-200", icon: Clock },
);

const isImageReceipt = computed(() => !!deposit.value?.receipt_image);

// ----------------------------------------------------------------- loading
async function loadDeposit() {
  if (!route.query.deposit_id) {
    error.value = "No deposit ID provided.";
    isLoading.value = false;
    return;
  }

  try {
    await store.dispatch("deposit/" + FETCH_DEPOSIT_DETAILS, { DepositId: route.query.deposit_id });
  } catch (err) {
    error.value = "Failed to load deposit details. Please try again.";
  } finally {
    isLoading.value = false;
  }
}

async function loadBalance() {
  const userId = deposit.value?.agent_id;
  if (!userId) return;
  try {
    await store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, { userId, currency_code: "AED" });
  } catch (err) {
    // Balance is informational; the page still works without it.
  }
}

// Load the customer's balance once the deposit (and so its owner) is known.
watch(
  () => deposit.value?.agent_id,
  (id) => id && loadBalance(),
  { immediate: true },
);

watch(
  () => deposit.value?.payment_type,
  (type) => {
    if (type === "abhipay-deposit") {
      store.dispatch("payment/" + CHECK_PAYMENT_STATUS, {
        paymentMethod: type,
        deposit_id: deposit.value?.id,
      });
    }
  },
);

// ------------------------------------------------------------ review dialog
function openReview() {
  decision.value = status.value === "approved" ? "rejected" : "approved";
  rejectionReason.value = "";
  error.value = null;
  isReviewOpen.value = true;
}

async function submitDecision() {
  if (isSubmitting.value || !deposit.value) return;

  const approve = decision.value === "approved";
  isSubmitting.value = true;
  error.value = null;

  try {
    await store.dispatch("deposit/" + UPDATE_DEPOSIT_STATUS, {
      status: approve ? 1 : 2,
      depositId: deposit.value.id,
      ...(approve ? {} : { rejectionReason: rejectionReason.value.trim() || DEFAULT_REJECTION_REASON }),
    });

    // The store reports API errors with a toast but does not throw, so confirm
    // the new status from the server before closing.
    await loadDeposit();
    const expected = approve ? "approved" : "rejected";

    if (status.value !== expected) {
      error.value = "The status could not be updated. Please try again.";
      return;
    }

    isReviewOpen.value = false;
    await loadBalance();
  } catch (err) {
    error.value = err?.response?.data?.message || "An error occurred while updating the status. Please try again.";
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(loadDeposit);
</script>

<template>
  <div class="min-h-screen p-6">
    <div class="max-w-full mx-auto">
      <!-- Page header (same pattern as the other admin detail pages) -->
      <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Button variant="outline" size="sm" @click="$router.push({ name: 'TopUpRequest' })">
            <ArrowLeft class="mr-1 h-4 w-4" />
            Back
          </Button>
          <h1 class="text-3xl font-medium leading-none tracking-tight text-gray-900">Deposit Details</h1>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading && !deposit" class="py-10 text-center">
        <LoaderIcon class="mx-auto h-8 w-8 animate-spin text-gray-400" />
        <p class="mt-3 text-lg text-gray-600">Loading deposit details...</p>
      </div>

      <!-- Error / not found -->
      <div v-else-if="!deposit" class="py-10 text-center">
        <p class="text-lg text-red-600">{{ error || "Deposit not found." }}</p>
      </div>

      <div v-else class="space-y-6">
        <!-- ===== Customer: highlighted profile card ===== -->
        <div v-if="customer" class="overflow-hidden rounded-xl border border-primary/30 bg-white shadow-sm">
          <div class="h-1.5 bg-primary"></div>
          <div class="flex flex-col gap-6 bg-gradient-to-r from-primary/10 via-primary/5 to-white p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
              <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-2 border-white bg-primary text-white shadow">
                <User class="h-10 w-10" />
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-xl font-bold text-gray-900">{{ customer.name }}</h2>
                  <span v-if="customer.uid" class="rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-white">
                    {{ customer.uid }}
                  </span>
                  <span v-if="customer.role" class="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium capitalize text-gray-600 ring-1 ring-inset ring-gray-200">
                    {{ customer.role }}
                  </span>
                </div>
                <p class="text-sm text-gray-500">
                  {{ customer.company && customer.company !== customer.name ? customer.company : "Individual Customer" }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
              <div class="flex items-start gap-2">
                <Mail class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div class="min-w-0">
                  <p class="text-gray-500">Email</p>
                  <p class="break-words font-medium text-gray-900">{{ customer.email }}</p>
                </div>
              </div>
              <div class="flex items-start gap-2">
                <Phone class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div>
                  <p class="text-gray-500">Phone</p>
                  <p class="font-medium text-gray-900" dir="ltr">{{ customer.phone }}</p>
                </div>
              </div>
              <div class="flex items-start gap-2">
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div>
                  <p class="text-gray-500">Address</p>
                  <p class="font-medium text-gray-900">{{ customer.address }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
          <!-- ===== LEFT: deposit ===== -->
          <div class="space-y-6 lg:col-span-2">
            <!-- Deposit summary + the single review action -->
            <div class="rounded-xl border bg-white p-6 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                  <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                    <ReceiptText class="h-7 w-7" />
                  </div>
                  <div>
                    <div class="flex flex-wrap items-center gap-3">
                      <h2 class="text-xl font-bold text-gray-900">{{ deposit.receipt_reference }}</h2>
                      <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                        :class="statusStyle.cls"
                      >
                        <component :is="statusStyle.icon" class="h-3.5 w-3.5" />
                        {{ statusStyle.label }}
                      </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                      Deposit #{{ deposit.id }} · Submitted {{ formatDateTime(deposit.created_at) }}
                    </p>
                  </div>
                </div>

                <Button class="text-white" @click="openReview">
                  <ShieldCheck class="mr-2 h-4 w-4" />
                  {{ status === "pending" ? "Review deposit" : "Update decision" }}
                </Button>
              </div>

              <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4">
                  <p class="text-sm text-gray-500">Deposited amount</p>
                  <p class="mt-1 text-2xl font-semibold text-gray-900" dir="ltr">
                    {{ money(deposit.source_money, deposit.amount, deposit.currency) }}
                  </p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                  <p class="text-sm text-gray-500">Credited to wallet (AED)</p>
                  <p class="mt-1 text-2xl font-semibold text-gray-900" dir="ltr">
                    {{ deposit.base_money ? money(deposit.base_money, deposit.aed_amount, "AED") : "—" }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Deposit information -->
            <div class="rounded-xl border bg-white p-6 shadow-sm">
              <h3 class="mb-4 text-lg font-semibold text-gray-800">Deposit Information</h3>
              <div class="grid grid-cols-1 gap-x-6 gap-y-5 text-sm sm:grid-cols-2">
                <div class="flex gap-3">
                  <CalendarDays class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Deposit date</p>
                    <p class="font-medium text-gray-900">{{ formatDate(deposit.date) }}</p>
                  </div>
                </div>
                <div class="flex gap-3">
                  <Building2 class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Payment source</p>
                    <p class="font-medium text-gray-900">{{ deposit.payment_type || "—" }}</p>
                  </div>
                </div>
                <div class="flex gap-3">
                  <Hash class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Receipt reference</p>
                    <p class="font-medium text-gray-900">{{ deposit.receipt_reference }}</p>
                  </div>
                </div>
                <div v-if="deposit.t_status" class="flex gap-3">
                  <Clock class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Payment status</p>
                    <span
                      class="mt-0.5 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                      :class="deposit.t_status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                    >
                      {{ deposit.t_status }}
                    </span>
                  </div>
                </div>
                <div v-if="deposit.approver" class="flex gap-3">
                  <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Approved by</p>
                    <p class="font-medium text-gray-900">
                      {{ deposit.approver.name }}
                      <span class="font-normal text-gray-500">· {{ formatDateTime(deposit.approved_at) }}</span>
                    </p>
                  </div>
                </div>
                <div class="flex gap-3 sm:col-span-2">
                  <FileText class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                  <div>
                    <p class="text-gray-500">Additional details</p>
                    <p class="font-medium text-gray-900">{{ deposit.additional_details || "—" }}</p>
                  </div>
                </div>
              </div>

              <div
                v-if="status === 'rejected' && deposit.rejection_reason"
                class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4"
              >
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Rejection reason</p>
                <p class="mt-1 text-sm text-red-800">{{ deposit.rejection_reason }}</p>
              </div>
            </div>
          </div>

          <!-- ===== RIGHT: balance + receipt ===== -->
          <div class="space-y-6 lg:sticky lg:top-4 lg:self-start">
            <!-- Customer balance -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
              <div class="flex items-center gap-2 border-b bg-gray-50 px-6 py-3">
                <Wallet class="h-4 w-4 text-primary" />
                <h3 class="text-lg font-semibold text-gray-800">Customer Balance</h3>
              </div>
              <div class="p-6">
                <p class="text-sm text-gray-500">
                  {{ status === "approved" ? "Current balance (includes this deposit)" : "Current balance" }}
                </p>
                <p
                  class="mt-1 text-3xl font-bold"
                  :class="balanceAed !== null && balanceAed < 0 ? 'text-red-600' : 'text-gray-900'"
                  dir="ltr"
                >
                  {{ balanceAed !== null ? aed(balanceAed) : "—" }}
                </p>

                <div
                  v-if="status !== 'approved' && balanceAfterApproval !== null"
                  class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3"
                >
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-emerald-800">Deposit</span>
                    <span class="font-semibold text-emerald-800" dir="ltr">+ {{ aed(depositAed) }}</span>
                  </div>
                  <div class="mt-2 flex items-center justify-between border-t border-emerald-200 pt-2 text-sm">
                    <span class="text-emerald-900">Balance after approval</span>
                    <span class="font-semibold text-emerald-900" dir="ltr">{{ aed(balanceAfterApproval) }}</span>
                  </div>
                </div>
                <p v-else-if="status !== 'approved'" class="mt-3 text-xs text-gray-500">
                  Balance after approval can’t be shown because this deposit has no AED value.
                </p>
              </div>
            </div>

            <!-- Receipt -->
            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
              <div class="flex items-center justify-between border-b bg-gray-50 px-6 py-3">
                <div class="flex items-center gap-2">
                  <ReceiptText class="h-4 w-4 text-primary" />
                  <h3 class="text-lg font-semibold text-gray-800">Receipt</h3>
                </div>
                <div v-if="isImageReceipt" class="flex items-center gap-1">
                  <a
                    :href="deposit.receipt_image"
                    target="_blank"
                    rel="noopener"
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-800"
                    title="Open full size"
                  >
                    <ExternalLink class="h-4 w-4" />
                  </a>
                  <a
                    :href="deposit.receipt_image"
                    download
                    class="rounded-md p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-800"
                    title="Download"
                  >
                    <Download class="h-4 w-4" />
                  </a>
                </div>
              </div>
              <div class="p-4">
                <a v-if="isImageReceipt" :href="deposit.receipt_image" target="_blank" rel="noopener" class="block">
                  <img :src="deposit.receipt_image" alt="Deposit receipt" class="w-full rounded-lg border object-contain" />
                </a>
                <div
                  v-else
                  class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-gray-400"
                >
                  <ImageOff class="h-8 w-8" />
                  <p class="mt-2 text-sm">No receipt uploaded</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Review dialog: the one place a deposit is approved or rejected -->
    <Dialog v-model:open="isReviewOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Review deposit</DialogTitle>
          <DialogDescription>
            {{ deposit?.receipt_reference }} ·
            {{ money(deposit?.source_money, deposit?.amount, deposit?.currency) }}
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-1">
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="rounded-lg border-2 p-3 text-left transition"
              :class="decision === 'approved' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'"
              :disabled="status === 'approved'"
              @click="decision = 'approved'"
            >
              <CheckCircle2 class="mb-1 h-5 w-5" :class="decision === 'approved' ? 'text-emerald-600' : 'text-gray-400'" />
              <p class="text-sm font-semibold text-gray-900">Approve</p>
              <p class="text-xs text-gray-500">Credit the wallet</p>
            </button>
            <button
              type="button"
              class="rounded-lg border-2 p-3 text-left transition"
              :class="decision === 'rejected' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300'"
              :disabled="status === 'rejected'"
              @click="decision = 'rejected'"
            >
              <XCircle class="mb-1 h-5 w-5" :class="decision === 'rejected' ? 'text-red-600' : 'text-gray-400'" />
              <p class="text-sm font-semibold text-gray-900">Reject</p>
              <p class="text-xs text-gray-500">Customer is notified</p>
            </button>
          </div>

          <div v-if="decision === 'approved' && balanceAfterApproval !== null" class="rounded-lg bg-gray-50 p-3 text-sm">
            <div class="flex justify-between text-gray-600">
              <span>Balance now</span><span dir="ltr">{{ aed(balanceBeforeDeposit) }}</span>
            </div>
            <div class="mt-1 flex justify-between font-semibold text-gray-900">
              <span>After approval</span><span dir="ltr">{{ aed(balanceAfterApproval) }}</span>
            </div>
          </div>

          <div v-if="decision === 'rejected'" class="space-y-1.5">
            <label class="text-sm font-medium text-gray-700">
              Reason <span class="font-normal text-gray-400">(shown to the customer)</span>
            </label>
            <Textarea v-model="rejectionReason" rows="3" maxlength="255" :placeholder="DEFAULT_REJECTION_REASON" />
          </div>

          <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        </div>

        <DialogFooter>
          <Button variant="outline" :disabled="isSubmitting" @click="isReviewOpen = false">Cancel</Button>
          <Button
            :disabled="isSubmitting"
            :class="decision === 'rejected' ? 'bg-red-600 hover:bg-red-700' : ''"
            @click="submitDecision"
          >
            <LoaderIcon v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
            {{ decision === "approved" ? "Approve deposit" : "Reject deposit" }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
