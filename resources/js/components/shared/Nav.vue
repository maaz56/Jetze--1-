<script setup>
import { initFlowbite } from "flowbite";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { computed, onMounted, onUnmounted, watch } from "vue";
import { ref } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { FETCH_AGENT_LEDGER, FETCH_CURRENCIES } from "@/services/store/actions.type";
import Button from "../ui/button/Button.vue";
import { 
    BookCheck, 
    ChevronDown, 
    CircleUser, 
    Coins, 
    LogIn, 
    LogOut, 
    Menu, 
    Wallet,
    Plane,
    Briefcase,
    Building2,
} from "lucide-vue-next";
import {
    getSelectedCurrencyCode,
    setSelectedCurrencyCode,
} from "@/lib/utils";
import { useAuthStore } from "@/services/stores/auth";
import { useStore } from "vuex";

const props = defineProps({
    isNavTransparent: {
        type: Boolean,
        default: false,
    },
});

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();
const store = useStore();

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const isAuthenticated = computed(() => authStore.isAuthenticated);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const currencies = computed(() => store.getters["currency/currencies"] || []);
const selectedCurrencyCode = ref(getSelectedCurrencyCode());
const currencyOptions = computed(() => {
    if (currencies.value.length > 0) {
        return currencies.value.filter((currency) => currency?.code);
    }

    return [
        {
            code: selectedCurrencyCode.value,
            name: selectedCurrencyCode.value,
        },
    ];
});
const selectedCurrency = computed(() =>
    currencies.value.find((currency) => currency.code === selectedCurrencyCode.value),
);
const selectedCurrencySymbol = computed(
    () => selectedCurrency.value?.symbol || selectedCurrencyCode.value,
);
const loading = ref(true);
const error = ref(null);
const isScrolled = ref(false);
const isSearchResultsPage = computed(() =>
    ["FlightSearch", "HotelSearch"].includes(String(route.name)),
);
const isHomePage = computed(() => String(route.name) === 'Home');
const isTransparent = computed(() => {
    if (isSearchResultsPage.value) return false;
    return (props.isNavTransparent || isHomePage.value) && !isScrolled.value;
});

const handleScroll = () => {
    if (isHomePage.value) {
        const tabsEl = document.getElementById("hero-service-tabs");
        if (tabsEl && tabsEl.offsetParent !== null) {
            const rect = tabsEl.getBoundingClientRect();
            // Only trigger white navbar when the floating service tabs have scrolled under the navbar
            isScrolled.value = rect.bottom <= 70;
            return;
        }

        const cardEl = document.getElementById("main-search-card");
        if (cardEl) {
            const rect = cardEl.getBoundingClientRect();
            isScrolled.value = rect.top <= 70;
            return;
        }

        isScrolled.value = window.scrollY > 340;
    } else {
        isScrolled.value = window.scrollY > 80;
    }
};

function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
                userId: user_id.value,
                currency_code: selectedCurrencyCode.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    }
}

function fetchCurrencies() {
    store.dispatch(`currency/${FETCH_CURRENCIES}`);
}

function handleLogout() {
    authStore.logout();
}

function handleLogin() {
    authStore.openDialog();
}

function goToDashboard(tab) {
    if (user.value.role === 'admin' || user.value.role === 'super_admin') {
        router.push({ name: 'Dashboard'});
    } else if (user.value.role === 'agent') {
        router.push({ name: 'AgentDashboard' });
    } else if (user.value.role === 'customer') {
        router.push({ name: 'CustomerProfile' , query: { tab: tab } });
    } else {
        router.push({ name: 'Home' });
    }
}

function formatBalanceAmount(amount) {
    const numericAmount =
        typeof amount === "string" ? Number(amount.replace(/,/g, "")) : Number(amount);

    return new Intl.NumberFormat("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
}

// Nav links featuring core travel booking services (Flights, Hotels, Holidays, Visas, Umrah)
const navLinks = [
    { routeName: "Home", text: "Flights", subText: "Book Flights", icon: Plane },
    { routeName: "HotelSearch", text: "Hotels", subText: "Luxury Stays", icon: Building2 },
    { routeName: "HolidayPackages", text: "Holidays", subText: "Tour Packages", icon: Briefcase },
];

const getLinkProps = (link) => link.routeName
    ? { to: { name: link.routeName } }
    : { href: link.href };

const isLinkActive = (link) => {
    if (link.routeName) {
        return route.name === link.routeName
            || (link.routeName === "Home" && route.name === "FlightSearch");
    }

    return window.location.pathname.replace(/\/$/, "") === link.href.replace(/\/$/, "");
};

watch(user, (newUser) => {
    if (!newUser) return;

    if (newUser.role === 'admin' || newUser.role === 'super_admin') {
        selectedCurrencyCode.value = setSelectedCurrencyCode('AED');
    }

    fetchAgentLedger();
}, { immediate: true });

watch(
    currencies,
    (list) => {
        if (!list.length) return;

        const currentExists = list.some(
            (currency) => currency.code === selectedCurrencyCode.value,
        );

        if (!currentExists) {
            selectedCurrencyCode.value = setSelectedCurrencyCode(list[0].code);
        }
    },
    { immediate: true },
);

watch(selectedCurrencyCode, (value) => {
    const domainCurrency = setSelectedCurrencyCode(value);

    if (domainCurrency !== value) {
        selectedCurrencyCode.value = domainCurrency;
        return;
    }

    fetchAgentLedger();
});

watch(route, () => {
    handleScroll();
});

onMounted(() => {
    if (user.value?.id) fetchAgentLedger();
    fetchCurrencies();
    initFlowbite();
    handleScroll();
    window.addEventListener("scroll", handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener("scroll", handleScroll);
});
</script>

<template>
    <div>
        <nav
            :class="[
                isSearchResultsPage
                    ? 'relative z-40 text-slate-900'
                    : 'fixed top-0 inset-x-0 z-40',
                isTransparent
                    ? 'bg-transparent text-white'
                    : 'bg-white text-slate-900 shadow-[0_2px_16px_rgba(15,23,42,0.12)] animate-slide-down-nav',
            ]"
        >
            <div class="container shared-nav-content mx-auto px-3 sm:px-4">
                <div :class="['flex items-center justify-between gap-4', isTransparent ? 'h-[70px]' : 'h-[68px]']">
                    <router-link :to="{ name: 'Home' }" class="flex items-center shrink-0">
                        <img 
                            src="/public/assets/logo.png" 
                            alt="Logo" 
                            :class="[
                                'w-auto object-contain transition-transform duration-300 hover:scale-[1.03]',
                                isTransparent ? 'h-10 drop-shadow-[0_2px_10px_rgba(0,0,0,0.28)]' : 'h-9'
                            ]" 
                        />
                    </router-link>

                    <div v-if="!isTransparent" class="hidden min-w-0 flex-1 items-center gap-9 pl-5 lg:flex">
                        <component
                            :is="link.routeName ? RouterLink : 'a'"
                            v-for="(link, index) in navLinks"
                            :key="index"
                            v-bind="getLinkProps(link)"
                            :aria-current="isLinkActive(link) ? 'page' : undefined"
                            :class="[
                                'group relative flex h-[68px] items-center gap-2.5 text-[13px] font-semibold transition-colors',
                                isLinkActive(link) ? 'text-primary' : 'text-slate-700 hover:text-primary'
                            ]"
                        >
                            <component :is="link.icon" class="h-5 w-5 stroke-[1.8]" />
                            <span>{{ link.text }}</span>
                            <span v-if="isLinkActive(link)" aria-hidden="true" class="absolute inset-x-0 bottom-0 h-[3px] bg-primary"></span>
                        </component>
                    </div>

                    <div class="flex min-w-0 items-center justify-end gap-3">
                        <button
                            v-if="isAuthenticated"
                            @click="goToDashboard('bookings')"
                            :class="[
                                'hidden items-center gap-2 text-left lg:flex',
                                isTransparent ? 'text-white' : 'text-slate-900'
                            ]"
                        >
                            <span :class="['flex h-9 w-9 items-center justify-center rounded-full', isTransparent ? 'bg-amber-400/90 text-slate-950' : 'bg-primary text-white']">
                                <BookCheck class="h-4 w-4" />
                            </span>
                            <span class="leading-none">
                                <span :class="['block text-[13px] font-bold', isTransparent ? 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.35)]' : '']">My Trips</span>
                                <span :class="['block text-[11px] font-medium', isTransparent ? 'text-white/70' : 'text-slate-500']">Manage bookings</span>
                            </span>
                        </button>

                        <div
                            v-if="user"
                            :class="[
                                'hidden items-center gap-3 lg:flex',
                                isTransparent ? 'text-white' : 'text-slate-900'
                            ]"
                        >
                            <div :class="['hidden h-8 items-center gap-2 border-l pl-4 xl:flex', isTransparent ? 'border-white/25' : 'border-slate-200']">
                                <Wallet class="h-4 w-4 opacity-80" />
                                <span :class="['text-[12px] font-semibold', isTransparent ? 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.35)]' : 'text-primary']">{{ selectedCurrencySymbol }} {{ formatBalanceAmount(agentLedger?.balance_money?.amount) }}</span>
                                <button
                                    @click="goToDashboard('deposits')"
                                    :class="['border-l pl-3 text-[12px] font-semibold transition-colors', isTransparent ? 'border-white/25 text-white/80 hover:text-white' : 'border-slate-200 text-primary hover:text-primary/75']"
                                >
                                    Top Up
                                </button>
                            </div>
                        </div>

                        <button
                            v-if="!isAuthenticated"
                            @click="handleLogin"
                            :class="[
                                'flex h-11 items-center gap-2 rounded px-4 text-[12px] font-bold transition-colors',
                                isTransparent
                                    ? 'bg-primary text-white shadow-[0_8px_20px_rgba(79,70,229,0.35)] hover:bg-primary/90'
                                    : 'bg-transparent text-slate-900 hover:text-primary'
                            ]"
                        >
                            <span :class="['flex h-6 w-6 items-center justify-center rounded-full', isTransparent ? 'bg-white text-primary' : 'bg-primary text-white']">
                                <LogIn class="h-3.5 w-3.5" />
                            </span>
                            <span class="hidden sm:inline">Login or Create Account</span>
                            <ChevronDown class="h-4 w-4" />
                        </button>

                        <DropdownMenu v-if="isAuthenticated">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    :class="[
                                        'h-10 w-10 overflow-hidden rounded-full border p-0 transition-colors',
                                        isTransparent
                                            ? 'border-white/70 bg-white/10 text-white hover:bg-white/15'
                                            : 'border-slate-300 bg-white text-primary hover:border-primary'
                                    ]"
                                >
                                    <img v-if="user?.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                                    <div v-else class="flex h-full w-full items-center justify-center text-[14px] font-bold">
                                        {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                                    </div>
                                </Button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent align="end" class="w-56 rounded-xl border-slate-200 bg-white text-slate-900 shadow-2xl">
                                <DropdownMenuLabel class="text-xs text-slate-500">My Account</DropdownMenuLabel>
                                <DropdownMenuSeparator class="bg-slate-200" />
                                <DropdownMenuItem @click="goToDashboard('profile')" class="cursor-pointer focus:bg-slate-100 focus:text-slate-900">
                                    <CircleUser class="mr-2 h-4 w-4 text-blue-500" /> Dashboard
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="goToDashboard('bookings')" class="cursor-pointer focus:bg-slate-100 focus:text-slate-900">
                                    <BookCheck class="mr-2 h-4 w-4 text-green-500" /> Bookings
                                </DropdownMenuItem>
                                <DropdownMenuSeparator class="bg-slate-200" />
                                <DropdownMenuItem @click="handleLogout" class="text-red-600 focus:bg-red-50 focus:text-red-600">
                                    <LogOut class="mr-2 h-4 w-4" /> Logout
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div :class="['hidden items-center gap-2 border-l pl-4 lg:flex', isTransparent ? 'border-white/25' : 'border-slate-200']">
                            <span :class="['hidden text-[11px] font-semibold leading-none xl:block', isTransparent ? 'text-white/70' : 'text-slate-500']">Currency</span>
                            <Select v-model="selectedCurrencyCode">
                                <SelectTrigger :class="['h-9 w-[76px] border-0 bg-transparent px-0 text-[12px] font-bold shadow-none focus:ring-0', isTransparent ? 'text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.35)]' : 'text-slate-900']">
                                    <SelectValue placeholder="Currency" />
                                </SelectTrigger>
                                <SelectContent :body-lock="false" class="rounded-lg border border-slate-200 bg-white shadow-xl">
                                    <SelectItem
                                        v-for="currency in currencyOptions"
                                        :key="currency.code"
                                        :value="currency.code"
                                        class="cursor-pointer text-xs font-medium"
                                    >
                                        {{ currency.code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Sheet>
                            <SheetTrigger as-child>
                                <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    :class="[
                                        'h-10 w-10 rounded-full border xl:hidden',
                                        isTransparent ? 'border-white/50 bg-transparent text-white hover:border-white' : 'border-slate-300 bg-transparent text-slate-900 hover:border-primary hover:text-primary'
                                    ]"
                                >
                                    <Menu class="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" class="w-[300px] border-r border-slate-200 bg-white text-slate-900">
                                <div class="py-6">
                                    <img class="mb-8 h-10" src="/public/assets/logo.png" alt="Logo" />
                                    <nav class="space-y-1">
                                        <component :is="link.routeName ? RouterLink : 'a'" v-for="link in navLinks"
                                            :key="link.routeName || link.href" v-bind="getLinkProps(link)"
                                            :aria-current="isLinkActive(link) ? 'page' : undefined"
                                            :class="[
                                                'relative flex items-center gap-3 py-3 text-[13px] font-semibold transition-colors',
                                                isLinkActive(link) ? 'text-primary' : 'text-slate-700 hover:text-primary',
                                            ]">
                                            <component :is="link.icon" class="h-5 w-5 stroke-[1.8]" />
                                            <div>
                                                <p>{{ link.text }}</p>
                                                <p :class="['text-[11px] font-medium', isLinkActive(link) ? 'text-primary/75' : 'text-slate-500']">{{ link.subText }}</p>
                                            </div>
                                            <span v-if="isLinkActive(link)" aria-hidden="true" class="absolute inset-x-0 bottom-0 h-[2px] bg-primary"></span>
                                        </component>
                                    </nav>
                                    <div class="mt-8 border-t border-slate-200 pt-6">
                                        <Button v-if="!isAuthenticated" @click="handleLogin" variant="ghost" class="w-full justify-start px-0 text-primary hover:text-primary">Login</Button>
                                        <Button v-else @click="handleLogout" variant="ghost" class="w-full justify-start px-0 text-red-600 hover:text-red-600">Logout</Button>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </div>

        </nav>
        <div v-if="!isSearchResultsPage && !isHomePage" aria-hidden="true" class="h-20"></div>
    </div>
</template>

<style scoped>
@keyframes slideDownFromTop {
    0% {
        transform: translateY(-100%);
    }
    100% {
        transform: translateY(0);
    }
}

.animate-slide-down-nav {
    animation: slideDownFromTop 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Align the shared navigation with the reduced desktop search/results width. */
.shared-nav-content {
    width: 80%;
}

@media (max-width: 1024px) {
    .shared-nav-content {
        width: 100%;
    }
}
</style>
