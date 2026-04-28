<template>
    <div class="min-h-screen bg-gray-50 p-6">
        <div class=" mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <Button @click="router.push({ name: 'Dashboard' })" variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-2" />
                        Back
                    </Button>
                    <h1 class="text-3xl font-bold text-gray-900">Currencies</h1>
                </div>

                <!-- Add Currency Dialog -->
                <Dialog v-model:open="showAddCurrencyDialog">
                    <DialogTrigger as-child>
                        <Button class="bg-primary hover:bg-primary/90 shadow-md">
                            <Plus class="w-4 h-4 mr-2" />
                            Add Currency
                        </Button>
                    </DialogTrigger>

                    <DialogContent class="sm:max-w-lg bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
                        <DialogHeader class="space-y-1 pb-4 border-b border-gray-100">
                            <DialogTitle class="text-xl font-semibold text-gray-800">
                                Add New Currency
                            </DialogTitle>
                            <DialogDescription class="text-sm text-gray-500">
                                Define a new currency. Base currency is <strong>USD</strong>.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="py-6 space-y-5">
                            <!-- Currency Code -->
                            <div>
                                <Label for="code" class="font-medium text-gray-700">Code</Label>
                                <Input id="code" v-model="newCurrency.code" placeholder="USD"
                                    class="mt-1 uppercase font-mono focus:ring-primary focus:border-primary"
                                    maxlength="3" @input="newCurrency.code = newCurrency.code.toUpperCase()" />
                            </div>

                            <!-- Currency Name -->
                            <div>
                                <Label for="name" class="font-medium text-gray-700">Name</Label>
                                <Input id="name" v-model="newCurrency.name" placeholder="US Dollar"
                                    class="mt-1 capitalize focus:ring-primary focus:border-primary" />
                            </div>

                            <!-- Symbol -->
                            <div>
                                <Label for="symbol" class="font-medium text-gray-700">Symbol</Label>
                                <Input id="symbol" v-model="newCurrency.symbol" placeholder="$"
                                    class="mt-1 text-xl text-left focus:ring-primary focus:border-primary"
                                    maxlength="5" />
                            </div>

                            <!-- Exchange Rate -->
                            <div>
                                <Label for="exchange_rate" class="font-medium text-gray-700">Rate</Label>
                                <Input id="exchange_rate" v-model.number="newCurrency.exchange_rate" type="number"
                                    step="0.000001" placeholder="278.50"
                                    class="mt-1 font-mono focus:ring-primary focus:border-primary" />
                            </div>
                        </div>

                        <DialogFooter class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <Button variant="outline" @click="resetAndClose"
                                class="border-gray-300 text-gray-600 hover:bg-gray-100">
                                Cancel
                            </Button>

                            <Button @click="addNewCurrency"
                                class="bg-primary hover:bg-primary/90 text-white shadow-sm disabled:opacity-50">
                                Add Currency
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>



            </div>

            <!-- Currencies Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-8 border-b">
                    <h2 class="text-2xl font-bold text-gray-900">Manage Currencies</h2>
                    <p class="text-sm text-gray-600 mt-1">Configure currency details and live exchange rates</p>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="p-32 flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-4 border-b-4 border-primary"></div>
                </div>

                <!-- Empty State -->
                <div v-else-if="currencies?.length === 0" class="p-24 text-center">
                    <div class="w-24 h-24 mx-auto mb-6 bg-primary/10 rounded-full flex items-center justify-center">
                        <Currency class="w-12 h-12 text-primary" />
                    </div>
                    <p class="text-xl font-semibold text-gray-900">No currencies configured</p>
                    <p class="text-gray-500 mt-2">Add your first currency to get started</p>
                </div>
               
                <!-- Table -->
                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/50">
                                <TableHead class="w-12 text-center">#</TableHead>
                                <TableHead>Currency</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Symbol</TableHead>
                                <TableHead>Exchange Rate</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(currency, index) in currencies" :key="currency.code"
                                class="hover:bg-gray-50 transition-all duration-200 border-b">
                                <TableCell class="text-center font-medium text-gray-600">
                                    {{ index + 1 }}
                                </TableCell>

                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <!-- Optional: Add flag later -->

                                        <div>
                                            <p class="font-semibold text-gray-900">{{ currency.name }}</p>

                                        </div>
                                    </div>
                                </TableCell>

                                <TableCell>
                                    <Badge variant="outline" class=" text-sm px-3 py-1">
                                        {{ currency.code }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="text-start text-base font-light">
                                    {{ currency.symbol }}
                                </TableCell>

                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Input v-model.number="currency.exchange_rate" type="number" step="0.000001"
                                            class="w-32 font-mono text-sm border rounded-lg px-3 py-1"
                                            @blur="updateRate(currency.code, currency.exchange_rate)" />
                                        <span class="text-gray-600 font-medium">{{ currency.code }}</span>
                                    </div>
                                </TableCell>


                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">

                                        <Dialog v-model:open="showUpdateCurrencyDialog">
                                            <DialogTrigger as-child>
                                                <Button size="sm" variant="outline"
                                                    @click="updateCurrencyDialog(currency.code)">
                                                    <PenBox class="w-4 h-4" />
                                                </Button>
                                            </DialogTrigger>

                                            <DialogContent
                                                class="sm:max-w-lg bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
                                                <DialogHeader class="space-y-1 pb-4 border-b border-gray-100">
                                                    <DialogTitle class="text-xl font-semibold text-gray-800">
                                                        Update Currency
                                                    </DialogTitle>
                                                    <DialogDescription class="text-sm text-gray-500">
                                                        Update an existing currency.
                                                    </DialogDescription>
                                                </DialogHeader>

                                                <div class="py-6 space-y-5">
                                                    <!-- Currency Code -->
                                                    <div>
                                                        <Label for="code" class="font-medium text-gray-700">Code</Label>
                                                        <Input id="code" v-model="newCurrency.code" placeholder="USD"
                                                            class="mt-1 uppercase font-mono focus:ring-primary focus:border-primary"
                                                            maxlength="3"
                                                            @input="newCurrency.code = newCurrency.code.toUpperCase()" />
                                                    </div>

                                                    <!-- Currency Name -->
                                                    <div>
                                                        <Label for="name" class="font-medium text-gray-700">Name</Label>
                                                        <Input id="name" v-model="newCurrency.name"
                                                            placeholder="US Dollar"
                                                            class="mt-1 capitalize focus:ring-primary focus:border-primary" />
                                                    </div>

                                                    <!-- Symbol -->
                                                    <div>
                                                        <Label for="symbol"
                                                            class="font-medium text-gray-700">Symbol</Label>
                                                        <Input id="symbol" v-model="newCurrency.symbol" placeholder="$"
                                                            class="mt-1 text-xl text-left focus:ring-primary focus:border-primary"
                                                            maxlength="5" />
                                                    </div>

                                                    <!-- Exchange Rate -->
                                                    <div>
                                                        <Label for="exchange_rate"
                                                            class="font-medium text-gray-700">Rate</Label>
                                                        <Input id="exchange_rate"
                                                            v-model.number="newCurrency.exchange_rate" type="number"
                                                            step="0.000001" placeholder="278.50"
                                                            class="mt-1 font-mono focus:ring-primary focus:border-primary" />
                                                    </div>
                                                </div>

                                                <DialogFooter
                                                    class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                                    <Button variant="outline" @click="resetAndClose"
                                                        class="border-gray-300 text-gray-600 hover:bg-gray-100">
                                                        Cancel
                                                    </Button>

                                                    <Button @click="updateCurrency"
                                                        class="bg-primary hover:bg-primary/90 text-white shadow-sm disabled:opacity-50">
                                                        Update Currency
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                        <Button size="sm" variant="destructive"
                                            @click="confirmDeleteCurrency(currency.code)">
                                            <Trash2 class="w-4 h-4" />
                                        </Button>

                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <AlertDialog v-model:open="showDeleteCurrencyDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Currency</AlertDialogTitle>
                    <AlertDialogDescription>
                        Remove <strong class="text-primary">{{ currencyToDelete }}</strong> permanently?
                        <br />
                        <span class="text-sm text-orange-600 block mt-3 font-medium">
                            This action cannot be undone and may affect pricing calculations.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDeleteCurrencyDialog = false">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction @click="deleteCurrency" class="bg-destructive">
                        Delete Permanently
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
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
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { ArrowLeft, Plus, Trash2, Currency, PenBox } from "lucide-vue-next";
import { toast } from "vue3-toastify";
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { DELETE_CURRENCY, FETCH_CURRENCIES, SAVE_CURRENCY, UPDATE_CURRENCY } from "@/services/store/actions.type";

const router = useRouter();
const store = useStore();


const isLoading = ref(false);
const showAddCurrencyDialog = ref(false);
const showUpdateCurrencyDialog = ref(false);
const showDeleteCurrencyDialog = ref(false);
const currencyToDelete = ref("");
const currencies = computed(() => store.getters["currency/currencies"]);
const newCurrency = ref({
    code: "",
    name: "",
    symbol: "",
    exchange_rate: null,
});
const selectedCurrency = ref(null);


function resetAndClose() {
    newCurrency.value = { code: "", name: "", symbol: "", exchange_rate: null };
    showAddCurrencyDialog.value = false;
    showUpdateCurrencyDialog.value = false;
}

function fetchCurrencies() {
    store.dispatch("currency/" + FETCH_CURRENCIES);
}

function addNewCurrency() {
    const currency = {
        code: newCurrency.value.code.toUpperCase(),
        name: newCurrency.value.name.trim(),
        symbol: newCurrency.value.symbol.trim(),
        exchange_rate: parseFloat(newCurrency.value.exchange_rate),
    };
    store.dispatch("currency/" + SAVE_CURRENCY, currency);
    currencies.value.push(currency);
    resetAndClose();
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
        };
    }

}

function updateCurrency() {

    console.log("Updating currency:", selectedCurrency.value);
    if (selectedCurrency) {
        const currency = {
            code: newCurrency.value.code.toUpperCase(),
            name: newCurrency.value.name.trim(),
            symbol: newCurrency.value.symbol.trim(),
            exchange_rate: parseFloat(newCurrency.value.exchange_rate),
        };
        store.dispatch("currency/" + UPDATE_CURRENCY, currency).then(() => {
            fetchCurrencies();
            toast.success("Currency updated successfully");
        }).catch((error) => {
            toast.error("Failed to update currency");
        });
    }
    resetAndClose();
}

function updateRate(code, newRate) {
    const currency = currencies.value.find(c => c.code === code);
    if (currency && newRate > 0) {
        currency.rate = parseFloat(newRate);
    }
    store.dispatch("currency/" + UPDATE_CURRENCY, currency);
}

function confirmDeleteCurrency(code) {
    store.dispatch("currency/" + DELETE_CURRENCY, {
        code: code,
    }).then(() => {
        fetchCurrencies();
    });
}





onMounted(() => {
    fetchCurrencies();
});
</script>