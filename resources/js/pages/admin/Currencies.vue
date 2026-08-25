<template>
    <div class="min-h-screen bg-gray-50 p-4 md:p-6">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="rounded border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-gray-600">
                            <Sparkles class="h-3.5 w-3.5" />
                            Finance Settings
                        </div>
                        <h1 class="text-3xl font-bold text-gray-900">Currencies</h1>
                        <p class="max-w-2xl text-sm text-gray-600">
                            Base currency is AED. Each rate shows the AED value of one currency unit.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="outline" @click="router.push({ name: 'Dashboard' })" class="h-11 border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>

                        <Dialog v-model:open="showAddCurrencyDialog">
                            <DialogTrigger as-child>
                            <Button class="h-11 rounded bg-primary text-white hover:bg-primary/90">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Currency
                            </Button>
                        </DialogTrigger>

                        <DialogContent class="sm:max-w-xl border-0 bg-white p-0 shadow-2xl">
                                <div class="rounded border border-gray-200 bg-white p-6">
                                    <DialogHeader class="space-y-2 pb-5">
                                        <DialogTitle class="text-2xl font-semibold text-gray-900">
                                            Add New Currency
                                        </DialogTitle>
                                        <DialogDescription class="text-sm leading-6 text-gray-500">
                                            Create a new currency entry. AED remains the fixed base currency.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div class="grid gap-5 py-2">
                                        <div class="grid gap-2">
                                            <Label for="code" class="text-sm font-medium text-gray-700">Code</Label>
                                            <Input id="code" v-model="newCurrency.code" placeholder="USD"
                                                class="uppercase font-mono tracking-wider" maxlength="3"
                                                @input="newCurrency.code = newCurrency.code.toUpperCase()" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="name" class="text-sm font-medium text-gray-700">Name</Label>
                                            <Input id="name" v-model="newCurrency.name" placeholder="US Dollar"
                                                class="capitalize" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="symbol" class="text-sm font-medium text-gray-700">Symbol</Label>
                                            <Input id="symbol" v-model="newCurrency.symbol" placeholder="$"
                                                class="text-lg" maxlength="5" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="exchange_rate" class="text-sm font-medium text-gray-700">1 currency = AED</Label>
                                            <Input id="exchange_rate" v-model.number="newCurrency.exchange_rate" type="number"
                                                step="0.000001" placeholder="0.013000" class="font-mono" />
                                        </div>

                                        <div v-if="newCurrency.code !== 'AED'" class="grid gap-2">
                                            <Label for="rate-change-reason" class="text-sm font-medium text-gray-700">Rate reason</Label>
                                            <Input id="rate-change-reason" v-model="newCurrency.rate_change_reason"
                                                placeholder="e.g. Updated supplier conversion rate" />
                                        </div>
                                    </div>

                                    <DialogFooter class="mt-6 gap-3 border-t border-gray-100 pt-5">
                                        <Button variant="outline" @click="resetAndClose"
                                            class="border-gray-300 text-gray-700 hover:bg-gray-50">
                                            Cancel
                                        </Button>

                                        <Button @click="addNewCurrency"
                                            class="bg-primary text-white hover:bg-primary/90 disabled:opacity-50">
                                            <Plus class="mr-2 h-4 w-4" />
                                            Save Currency
                                        </Button>
                                    </DialogFooter>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </div>

            <div class="rounded border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Manage Currencies</h2>
                        <p class="mt-1 text-sm text-gray-600">Configure currency details and exchange rates</p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative w-full sm:w-80">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input v-model="searchTerm" placeholder="Search by name, code, or symbol..."
                            class="h-11 rounded border-gray-200 bg-white pl-10 shadow-none focus:bg-white" />
                        </div>

                        <Button variant="outline" @click="fetchCurrencies"
                            class="h-11 rounded border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                            <RefreshCcw class="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                    </div>
                </div>

                <div v-if="isLoading" class="flex justify-center py-24">
                    <div class="flex items-center gap-3 rounded border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-600">
                        <div class="h-4 w-4 animate-spin rounded border-2 border-gray-300 border-t-gray-900"></div>
                        Loading currencies...
                    </div>
                </div>

                <div v-else-if="filteredCurrencies.length === 0" class="px-6 py-20">
                    <div class="mx-auto max-w-md rounded border border-dashed border-gray-300 bg-gray-50 px-8 py-12 text-center">
                        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded bg-gray-100 text-gray-600">
                            <DollarSign class="h-8 w-8" />
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">
                            {{ searchTerm ? 'No currencies match your search' : 'No currencies configured' }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-gray-500">
                            {{ searchTerm ? 'Try a different code, symbol, or currency name.' : 'Add your first currency to start managing exchange rates.' }}
                        </p>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="border-gray-100 bg-gray-50">
                                <TableHead class="w-14 text-center text-gray-700">#</TableHead>
                                <TableHead class="text-gray-700">Currency</TableHead>
                                <TableHead class="text-gray-700">Code</TableHead>
                                <TableHead class="text-gray-700">Symbol</TableHead>
                                <TableHead class="text-gray-700">Exchange Rate</TableHead>
                                <TableHead class="text-gray-700">Status</TableHead>
                                <TableHead class="text-right text-gray-700">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(currency, index) in filteredCurrencies" :key="currency.code"
                                class="group border-b border-gray-100 transition-colors hover:bg-gray-50/80">
                                <TableCell class="text-center font-medium text-gray-500">
                                    {{ index + 1 }}
                                </TableCell>

                                <TableCell class="py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center rounded bg-gray-900 text-sm font-semibold text-white shadow-sm">
                                            {{ currency.code?.slice(0, 2) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ currency.name }}</p>
                                            <p class="text-xs text-gray-500">Exchangeable in admin settings</p>
                                        </div>
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <Badge variant="outline"
                                        class="rounded border-gray-200 bg-white px-3 py-1 text-xs font-semibold tracking-[0.18em] text-gray-700">
                                        {{ currency.code }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="text-sm font-medium text-gray-700">
                                    {{ currency.symbol || '—' }}
                                </TableCell>

                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-700">{{ formatRate(currency.exchange_rate) }}</span>
                                            <span class="text-xs text-gray-500">1 {{ currency.code }} = AED</span>
                                        </div>
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Switch
                                            :checked="Boolean(currency.is_enabled)"
                                            :disabled="currency.is_base"
                                            @update:checked="(value) => updateCurrencyStatus(currency, value)"
                                        />
                                        <Badge
                                            variant="outline"
                                            :class="currency.is_enabled
                                                ? 'border-green-200 bg-green-50 text-green-700'
                                                : 'border-gray-200 bg-gray-50 text-gray-600'"
                                        >
                                            {{ currency.is_base ? 'Base' : (currency.is_enabled ? 'Enabled' : 'Disabled') }}
                                        </Badge>
                                    </div>
                                </TableCell>

                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Dialog v-model:open="showUpdateCurrencyDialog">
                                            <DialogTrigger as-child>
                                                <Button size="sm" variant="outline"
                                                    @click="updateCurrencyDialog(currency.code)"
                                                    class="h-10 rounded border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                                                    <PenBox class="h-4 w-4" />
                                                </Button>
                                            </DialogTrigger>

                                            <DialogContent class="sm:max-w-xl border-0 bg-white p-0 shadow-2xl">
                                                <div class="rounded border border-gray-200 bg-white p-6">
                                                    <DialogHeader class="space-y-2 pb-5">
                                                        <DialogTitle class="text-2xl font-semibold text-gray-900">
                                                            Update Currency
                                                        </DialogTitle>
                                                        <DialogDescription class="text-sm leading-6 text-gray-500">
                                                            Update the label, symbol, or AED rate for this currency.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <div class="grid gap-5 py-2">
                                                        <div class="grid gap-2">
                                                            <Label for="update-code" class="text-sm font-medium text-gray-700">Code</Label>
                                                            <Input id="update-code" v-model="newCurrency.code"
                                                class="uppercase font-mono tracking-wider bg-gray-50"
                                                                maxlength="3" readonly />
                                                            <p class="text-xs text-gray-500">
                                                                Currency code is the unique identifier and cannot be changed.
                                                            </p>
                                                        </div>

                                                        <div class="grid gap-2">
                                                            <Label for="update-name" class="text-sm font-medium text-gray-700">Name</Label>
                                                            <Input id="update-name" v-model="newCurrency.name"
                                                                placeholder="US Dollar" class="capitalize" />
                                                        </div>

                                                        <div class="grid gap-2">
                                                            <Label for="update-symbol" class="text-sm font-medium text-gray-700">Symbol</Label>
                                                            <Input id="update-symbol" v-model="newCurrency.symbol"
                                                                placeholder="$" class="text-lg" maxlength="5" />
                                                        </div>

                                                        <div class="grid gap-2">
                                                            <Label for="update-exchange_rate" class="text-sm font-medium text-gray-700">1 currency = AED</Label>
                                                            <Input id="update-exchange_rate" v-model.number="newCurrency.exchange_rate"
                                                                type="number" step="0.000001" placeholder="0.013000"
                                                                :disabled="selectedCurrency?.is_base"
                                                                class="font-mono" />
                                                        </div>

                                                        <div v-if="!selectedCurrency?.is_base" class="grid gap-2">
                                                            <Label for="update-rate-change-reason" class="text-sm font-medium text-gray-700">Rate reason</Label>
                                                            <Input id="update-rate-change-reason" v-model="newCurrency.rate_change_reason"
                                                                placeholder="Required only when changing the rate" />
                                                        </div>
                                                    </div>

                                                    <DialogFooter class="mt-6 gap-3 border-t border-gray-100 pt-5">
                                                        <Button variant="outline" @click="resetAndClose"
                                                            class="border-gray-300 text-gray-700 hover:bg-gray-50">
                                                            Cancel
                                                        </Button>

                                                        <Button @click="updateCurrency"
                                                            class="bg-primary text-white hover:bg-primary/90 disabled:opacity-50">
                                                            Save Changes
                                                        </Button>
                                                    </DialogFooter>
                                                </div>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { ArrowLeft, DollarSign, PenBox, Plus, RefreshCcw, Search, Sparkles } from "lucide-vue-next";
import { toast } from "vue3-toastify";
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { FETCH_CURRENCIES, SAVE_CURRENCY, UPDATE_CURRENCY } from "@/services/store/actions.type";

const router = useRouter();
const store = useStore();


const isLoading = computed(() => store.getters["currency/isLoading"]);
const showAddCurrencyDialog = ref(false);
const showUpdateCurrencyDialog = ref(false);
const currencies = computed(() => store.getters["currency/currencies"] || []);
const searchTerm = ref("");
const newCurrency = ref({
    code: "",
    name: "",
    symbol: "",
    exchange_rate: null,
    rate_change_reason: "",
});
const selectedCurrency = ref(null);
const filteredCurrencies = computed(() => {
    const list = currencies.value || [];
    const query = searchTerm.value.trim().toLowerCase();

    if (!query) {
        return list;
    }

    return list.filter((currency) => {
        const haystack = [
            currency.name,
            currency.code,
            currency.symbol,
            currency.exchange_rate,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(query);
    });
});

function formatRate(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return "—";
    }

    return numericValue.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    });
}


function resetAndClose() {
    newCurrency.value = {
        code: "",
        name: "",
        symbol: "",
        exchange_rate: null,
        rate_change_reason: "",
    };
    showAddCurrencyDialog.value = false;
    showUpdateCurrencyDialog.value = false;
}

function currencyPayload(currency, overrides = {}) {
    const exchangeRate = Number(currency.exchange_rate);

    return {
        code: String(currency.code || "").toUpperCase(),
        name: String(currency.name || "").trim(),
        symbol: String(currency.symbol || "").trim(),
        exchange_rate: Number.isFinite(exchangeRate) && exchangeRate > 0 ? exchangeRate : null,
        decimal_places: Number(currency.decimal_places ?? 2),
        is_enabled: Boolean(currency.is_enabled),
        rate_change_reason: "",
        ...overrides,
    };
}

function fetchCurrencies() {
    store.dispatch("currency/" + FETCH_CURRENCIES);
}

function addNewCurrency() {
    const currency = {
        code: newCurrency.value.code.toUpperCase(),
        name: newCurrency.value.name.trim(),
        symbol: newCurrency.value.symbol.trim(),
        exchange_rate: Number(newCurrency.value.exchange_rate),
        is_enabled: true,
        rate_change_reason: newCurrency.value.rate_change_reason.trim(),
    };

    if (currency.code !== "AED" && !currency.rate_change_reason) {
        toast.error("Please add a reason for the initial rate.");
        return;
    }

    store.dispatch("currency/" + SAVE_CURRENCY, currency).then((response) => {
        if (response) {
            fetchCurrencies();
            resetAndClose();
        }
    });
}

function updateCurrencyDialog(code) {
    showUpdateCurrencyDialog.value = true;
    selectedCurrency.value = currencies.value.find(c => c.code === code);
    if (selectedCurrency.value) {
        newCurrency.value = {
            code: selectedCurrency.value.code,
            name: selectedCurrency.value.name,
            symbol: selectedCurrency.value.symbol,
            exchange_rate: selectedCurrency.value.exchange_rate,
            rate_change_reason: "",
        };
    }

}

function updateCurrency() {
    if (selectedCurrency.value) {
        const currency = currencyPayload(selectedCurrency.value, {
            code: newCurrency.value.code.toUpperCase(),
            name: newCurrency.value.name.trim(),
            symbol: newCurrency.value.symbol.trim(),
            exchange_rate: Number(newCurrency.value.exchange_rate),
            rate_change_reason: newCurrency.value.rate_change_reason.trim(),
        });
        store.dispatch("currency/" + UPDATE_CURRENCY, currency).then((response) => {
            if (response) {
                fetchCurrencies();
                toast.success("Currency updated successfully");
                resetAndClose();
            }
        });
    }
}

function updateCurrencyStatus(currency, isEnabled) {
    if (currency.is_base) {
        return;
    }

    store.dispatch("currency/" + UPDATE_CURRENCY, currencyPayload(currency, {
        is_enabled: isEnabled,
    })).then(fetchCurrencies);
}





onMounted(() => {
    fetchCurrencies();
});
</script>
