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
import { useI18n } from "vue-i18n";
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
    FileText,
    Phone,
    Heart,
    Briefcase,
    Building2,
    BadgeDollarSignIcon
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

const { locale } = useI18n();
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
const isLoginMode = ref(true)
let activeLedgerRequestKey = null;
let activeLedgerRequest = null;
let queuedLedgerRequestKey = null;
const emit = defineEmits(['login-click', 'search-click'])
const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
const isSearchResultsPage = computed(() =>
    ["FlightSearch", "HotelSearch"].includes(String(route.name)),
);
const isHomePage = computed(() => String(route.name) === 'Home');
const isLockedPriceRoute = computed(() => {
    const lockedPriceRoutes = new Set([
        'Checkout',
        'FlightCheckout',
        'CustomerPaymentView',
        'PaymentView',
    ]);

    return lockedPriceRoutes.has(String(route.name))
        && Boolean(route.query.quote_id || route.query.booking_id);
});
const isTransparent = computed(() => {
    if (isSearchResultsPage.value) return false;
    return (props.isNavTransparent || isHomePage.value) && !isScrolled.value;
});

const handleScroll = () => {
    isScrolled.value = window.scrollY > 140;
};

async function fetchAgentLedger() {
    if (!user_id.value) {
        return;
    }

    const requestKey = `${user_id.value}:${selectedCurrencyCode.value}`;

    if (activeLedgerRequestKey === requestKey) {
        return activeLedgerRequest;
    }

    if (activeLedgerRequest) {
        queuedLedgerRequestKey = requestKey;
        return activeLedgerRequest;
    }

    activeLedgerRequestKey = requestKey;
    activeLedgerRequest = store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
        userId: user_id.value,
        currency_code: selectedCurrencyCode.value,
    });

    try {
        await activeLedgerRequest;
    } catch (err) {
        error.value = "Failed to load user data. Please try again.";
    } finally {
        const queuedRequestKey = queuedLedgerRequestKey;
        activeLedgerRequestKey = null;
        activeLedgerRequest = null;
        queuedLedgerRequestKey = null;
        loading.value = false;

        if (queuedRequestKey && queuedRequestKey !== requestKey) {
            fetchAgentLedger();
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
    } else if (user.value.role === 'customer') {
        router.push({ name: 'CustomerProfile', params: { section: tab } });
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
    { routeName: "Home", text: "Flights", subText: "Book Flights", icon: "/plane.png" },
    { routeName: "HotelSearch", text: "Hotels", subText: "Luxury Stays", icon: "/residential.png" },
    { routeName: "HolidayPackages", text: "Holidays", subText: "Tour Packages", icon: "/holidays.png" },
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
                    ? 'bg-gradient-to-b from-slate-950/45 via-slate-950/16 to-transparent text-white py-1'
                    : 'bg-white shadow-md border-b border-slate-200/80 text-slate-900 py-0 animate-slide-down-nav',
            ]"
        >
            <div class="container shared-nav-content mx-auto px-3 sm:px-4">
                <div class="flex items-center justify-between h-16 sm:h-20 px-2 sm:px-4 lg:px-6">
                
                    <!-- Over the hero, a soft blurred light fades into the sky behind the logo so its colours stay legible -->
                    <router-link :to="{ name: 'Home' }" class="relative flex items-center shrink-0">
                        <span
                            v-if="isTransparent"
                            aria-hidden="true"
                            class="pointer-events-none absolute -inset-x-6 -inset-y-3 rounded-full bg-[radial-gradient(ellipse_at_center,rgba(255,255,255,0.75)_0%,rgba(255,255,255,0.4)_45%,rgba(255,255,255,0)_72%)] blur-md"
                        ></span>
                        <img 
                            src="/public/assets/logo.png" 
                            alt="Logo" 
                            :class="[
                                'relative h-8 sm:h-10 w-auto object-contain transition-transform duration-300 hover:scale-105 lg:h-11',
                                isHomePage && isTransparent ? 'lg:h-12' : '',
                            ]" 
                        />
                    </router-link>

                    <div class="flex items-center">
                        
                        <!-- Nav Links Pills -->
                        <nav v-if="!isTransparent" aria-label="Travel services" class="hidden lg:flex items-center gap-2">
                            <component
                                :is="link.routeName ? RouterLink : 'a'"
                                v-for="(link, index) in navLinks" 
                                :key="index" 
                                v-bind="getLinkProps(link)"
                                :aria-current="isLinkActive(link) ? 'page' : undefined"
                                :class="[
                                    'group relative flex items-center rounded-xl px-3 py-2 transition-all duration-200 bg-transparent',
                                    isTransparent
                                        ? (isLinkActive(link) ? 'text-sky-400 font-black' : 'text-white hover:text-sky-300')
                                        : (isLinkActive(link) ? 'text-primary font-black' : 'text-slate-700 hover:text-primary'),
                                ]"
                            >
                                <!-- Badge -->
                                <span 
                                    v-if="link.badge" 
                                    class="absolute -top-1.5 right-1.5 bg-rose-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded shadow-xs uppercase tracking-tight"
                                >
                                    {{ link.badge }}
                                </span>

                                <!-- Larger Icon without Circle Wrapper -->
                                <img
                                    :src="link.icon"
                                    :alt="link.text"
                                    :class="[
                                        'w-6 h-6 object-contain mr-2.5 shrink-0 transition-all duration-300',
                                        isLinkActive(link) ? 'scale-110' : 'opacity-90',
                                        isTransparent ? 'brightness-125 filter drop-shadow' : '',
                                    ]"
                                />

                                <div class="flex flex-col">
                                    <span 
                                        :class="[
                                            'text-xs sm:text-sm font-bold leading-tight transition-colors',
                                            isTransparent 
                                                ? (isLinkActive(link) ? 'text-sky-400 font-black drop-shadow-xs' : 'text-white group-hover:text-sky-300')
                                                : (isLinkActive(link) ? 'text-primary font-black' : 'text-slate-800 group-hover:text-primary')
                                        ]"
                                    >
                                        {{ link.text }}
                                    </span>
                                    <span
                                        :class="[
                                            'text-[9.5px] font-medium transition-colors',
                                            isTransparent
                                                ? (isLinkActive(link) ? 'text-sky-300 font-semibold' : 'text-slate-200 group-hover:text-sky-200')
                                                : (isLinkActive(link) ? 'text-primary/80 font-bold' : 'text-slate-500 group-hover:text-primary/75'),
                                        ]"
                                    >
                                        {{ link.subText }}
                                    </span>
                                </div>
                                <span 
                                    v-if="isLinkActive(link)" 
                                    aria-hidden="true" 
                                    :class="[
                                        'absolute inset-x-2 bottom-0 h-[2.5px] rounded',
                                        isTransparent ? 'bg-sky-400 shadow-sky-400/50 shadow-xs' : 'bg-primary'
                                    ]"
                                ></span>
                            </component>
                        </nav>

                        <!-- Balance controls remain available only to logged-in users. -->
                        <div v-if="user" :class="['hidden md:flex items-center px-4 gap-4 shrink-0 whitespace-nowrap border-l', isTransparent ? 'border-white/20' : 'border-slate-200']">
                            <button
                                @click="goToDashboard('bookings')"
                                :class="[
                                    'hidden lg:flex items-center gap-2 border-r pr-4 text-left transition-colors',
                                    isTransparent ? 'border-white/20 text-white hover:text-sky-200' : 'border-slate-200 text-slate-800 hover:text-primary',
                                ]"
                            >
                                <span :class="['flex h-8 w-8 items-center justify-center rounded-full', isTransparent ? 'bg-amber-300 text-slate-900' : 'bg-primary/10 text-primary']">
                                    <BookCheck class="h-4 w-4" />
                                </span>
                                <span class="flex flex-col leading-tight">
                                    <span class="text-xs font-bold">My Trips</span>
                                    <span :class="['text-[10px] font-medium', isTransparent ? 'text-white/90' : 'text-slate-500']">Manage Bookings</span>
                                </span>
                            </button>
                            <div class="flex items-center gap-2">
                                <span :class="['text-[11px] tracking-wider font-bold', isTransparent ? 'text-white' : 'text-slate-500']">Balance:</span>
                                <div :class="['flex items-center font-bold text-xs sm:text-sm', isTransparent ? 'text-white' : 'text-emerald-400']">
                                    <Wallet class="h-3.5 w-3.5 mr-1 shrink-0" />
                                    <span>{{ selectedCurrencySymbol }} {{ formatBalanceAmount(agentLedger?.balance_money?.amount) }}</span>
                                </div>
                                <button
                                    @click="goToDashboard('deposits')"
                                    :class="['group ml-1.5 inline-flex items-center gap-1 border-l pl-2 text-xs font-bold transition-colors focus:outline-none', isTransparent ? 'border-white/40 text-white hover:text-white/80' : 'border-slate-200 text-primary hover:text-primary/75']"
                                >
                                    <Coins class="h-3.5 w-3.5 transition-transform group-hover:rotate-12" />
                                    Top Up
                                </button>
                            </div>

                        </div>

                        <!-- Guests can choose their search display currency; quotes lock it after Book Now. -->
                        <div
                            v-if="!isLockedPriceRoute"
                            class="flex items-center gap-2 shrink-0 whitespace-nowrap md:border-l md:px-4"
                            :class="isTransparent ? 'border-white/20' : 'border-slate-200'"
                        >
                            <span :class="['hidden md:inline text-[11px] tracking-wider font-bold', isTransparent ? 'text-white' : 'text-slate-500']">Currency:</span>
                            <Select v-model="selectedCurrencyCode">
                                <SelectTrigger aria-label="Display currency" :class="['h-8 w-[72px] md:h-8.5 md:w-[84px] rounded-lg border px-2 md:px-2.5 text-xs font-bold shadow-xs focus:ring-0', isTransparent ? 'border-white/60 bg-white/10 text-white backdrop-blur-md' : 'border-slate-200 bg-white text-slate-700']">
                                    <SelectValue placeholder="Currency" />
                                </SelectTrigger>
                                <SelectContent :body-lock="false" class="rounded-lg border border-slate-200 bg-white shadow-xl">
                                    <SelectItem
                                        v-for="currency in currencyOptions"
                                        :key="currency.code"
                                        :value="currency.code"
                                        class="text-xs font-medium cursor-pointer"
                                    >
                                        {{ currency.code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Login Button / User Dropdown -->
                        <div class="flex items-center ml-2 sm:ml-4 space-x-2 sm:space-x-3">
                            <button 
                                v-if="!isAuthenticated" 
                                @click="handleLogin"
                                :class="[
                                    'px-2.5 py-1.5 sm:px-4 sm:py-2 rounded-xl shadow-xs sm:shadow-sm flex items-center space-x-1.5 sm:space-x-3 transition-all duration-300 border text-xs sm:text-sm',
                                    isTransparent
                                        ? 'bg-white/15 hover:bg-white/25 text-white border-white/30 backdrop-blur-md shadow-md'
                                        : 'bg-white border-slate-300 hover:bg-slate-50 text-slate-900'
                                ]"
                            >
                                <div :class="['p-1 rounded-full', isTransparent ? 'bg-white/20' : 'bg-primary/10']">
                                    <LogIn :class="['w-3.5 h-3.5 sm:w-4 sm:h-4', isTransparent ? 'text-sky-300' : 'text-primary']" />
                                </div>
                                <span class="sm:hidden font-bold">Sign In</span>
                                <div class="hidden sm:flex flex-col items-start leading-none">
                                    <span :class="['text-[11px] font-medium', isTransparent ? 'text-slate-200' : 'text-slate-500']">Login or</span>
                                    <span :class="['text-sm font-bold', isTransparent ? 'text-white' : 'text-slate-900']">Create Account</span>
                                </div>
                                <ChevronDown :class="['hidden sm:block w-4 h-4', isTransparent ? 'text-slate-200' : 'text-slate-400']" />
                            </button>

                            <DropdownMenu v-if="isAuthenticated">
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" class="h-9 w-9 sm:h-11 sm:w-11 p-0 rounded-full ring-2 ring-white/50 overflow-hidden hover:ring-primary transition-all">
                                        <img v-if="user?.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                                        <div v-else class="h-full w-full bg-primary text-white flex items-center justify-center font-bold">
                                            {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                                        </div>
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-56 bg-white border-slate-200 text-slate-900 rounded-xl shadow-2xl">
                                    <DropdownMenuLabel class="text-slate-500 text-xs">My Account</DropdownMenuLabel>
                                    <DropdownMenuSeparator class="bg-slate-200" />
                                    <DropdownMenuItem @click="goToDashboard('bookings')" class="cursor-pointer focus:bg-slate-100 focus:text-slate-900">
                                        <CircleUser class="h-4 w-4 mr-2 text-blue-500" /> Dashboard
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="goToDashboard('bookings')" class="cursor-pointer focus:bg-slate-100 focus:text-slate-900">
                                        <BookCheck class="h-4 w-4 mr-2 text-green-500" /> Bookings
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator class="bg-slate-200" />
                                    <DropdownMenuItem @click="handleLogout" class="text-red-600 focus:bg-red-50 focus:text-red-600">
                                        <LogOut class="h-4 w-4 mr-2" /> Logout
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Sheet v-model:open="mobileMenuOpen">
                                <SheetTrigger as-child>
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        :class="[
                                            'xl:hidden',
                                            isTransparent ? 'text-white hover:bg-white/20' : 'text-slate-900 hover:bg-slate-100'
                                        ]"
                                    >
                                        <Menu class="h-6 w-6" />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="left" class="w-[310px] max-w-[85vw] bg-white text-slate-800 border-r border-slate-200 p-0 flex flex-col h-full shadow-2xl overflow-hidden">
                                    <!-- Drawer Top Header: Logo -->
                                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-white pr-12">
                                        <router-link :to="{ name: 'Home' }" @click="mobileMenuOpen = false" class="flex items-center">
                                            <img class="h-8 sm:h-9 w-auto object-contain" src="/public/assets/logo.png" alt="Jetze Logo" />
                                        </router-link>
                                    </div>

                                    <!-- Scrollable Drawer Body -->
                                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5 no-scrollbar">

                                        <!-- User Profile Card (if Authenticated) -->
                                        <div v-if="isAuthenticated && user" class="rounded-2xl bg-gradient-to-br from-slate-50 to-blue-50/50 p-3.5 border border-slate-200/80 shadow-2xs">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full ring-2 ring-primary/20 overflow-hidden bg-primary text-white flex items-center justify-center font-bold text-sm shrink-0">
                                                    <img v-if="user.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                                                    <span v-else>{{ user.name?.charAt(0).toUpperCase() || 'U' }}</span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-bold text-sm text-slate-900 truncate">{{ user.name }}</p>
                                                    <p class="text-xs text-slate-500 truncate">{{ user.email || user.phone }}</p>
                                                </div>
                                            </div>

                                            <!-- Agent Balance (if available) -->
                                            <div v-if="agentLedger?.balance_money" class="mt-2.5 pt-2.5 border-t border-slate-200/60 flex items-center justify-between text-xs">
                                                <span class="font-medium text-slate-500">Balance:</span>
                                                <span class="font-extrabold text-emerald-600">{{ selectedCurrencySymbol }} {{ formatBalanceAmount(agentLedger.balance_money.amount) }}</span>
                                            </div>

                                            <!-- Quick Action Links -->
                                            <div class="mt-2.5 grid grid-cols-2 gap-2 pt-0.5">
                                                <button
                                                    type="button"
                                                    @click="mobileMenuOpen = false; goToDashboard('bookings')"
                                                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg bg-white border border-slate-200/90 text-xs font-bold text-slate-700 hover:text-primary hover:border-primary/50 shadow-2xs transition-colors"
                                                >
                                                    <BookCheck class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                                    <span>My Bookings</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="mobileMenuOpen = false; goToDashboard('dashboard')"
                                                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg bg-white border border-slate-200/90 text-xs font-bold text-slate-700 hover:text-primary hover:border-primary/50 shadow-2xs transition-colors"
                                                >
                                                    <CircleUser class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                                                    <span>Dashboard</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Guest Welcome Card (if Not Authenticated) -->
                                        <div v-else class="rounded-2xl bg-gradient-to-br from-primary/5 via-blue-50/40 to-slate-50 p-4 border border-primary/15 shadow-2xs">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/15 text-primary">
                                                    <LogIn class="w-3.5 h-3.5" />
                                                </span>
                                                <h4 class="font-extrabold text-sm text-slate-900">Welcome to Jetze</h4>
                                            </div>
                                            <p class="text-xs text-slate-500 leading-relaxed mb-3">Sign in to manage flights, save travelers & unlock exclusive fares.</p>
                                            <Button @click="mobileMenuOpen = false; handleLogin()" class="w-full h-9 bg-primary text-white font-bold text-xs rounded-xl shadow-xs hover:bg-primary/90 flex items-center justify-center gap-1.5">
                                                <LogIn class="w-3.5 h-3.5" />
                                                <span>Sign In / Register</span>
                                            </Button>
                                        </div>

                                        <!-- Main Travel Navigation Section -->
                                        <div>
                                            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2 px-1">
                                                Travel Services
                                            </span>
                                            <nav class="space-y-1.5">
                                                <component 
                                                    :is="link.routeName ? RouterLink : 'a'" 
                                                    v-for="link in navLinks"
                                                    :key="link.routeName || link.href" 
                                                    v-bind="getLinkProps(link)"
                                                    @click="mobileMenuOpen = false"
                                                    :aria-current="isLinkActive(link) ? 'page' : undefined"
                                                    :class="[
                                                        'relative flex items-center rounded-xl p-2.5 transition-all duration-200 group border',
                                                        isLinkActive(link) 
                                                            ? 'bg-primary/10 border-primary/30 text-primary font-bold shadow-2xs' 
                                                            : 'bg-white border-transparent text-slate-700 hover:bg-slate-50 hover:text-slate-900',
                                                    ]"
                                                >
                                                    <div :class="['mr-3 rounded-xl p-2 shrink-0 transition-colors', isLinkActive(link) ? 'bg-primary/20' : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200']">
                                                        <img :src="link.icon" :alt="link.text" class="w-5 h-5 object-contain" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="font-bold text-sm leading-tight">{{ link.text }}</p>
                                                        <p :class="['text-[11px]', isLinkActive(link) ? 'text-primary/80 font-semibold' : 'text-slate-400']">{{ link.subText }}</p>
                                                    </div>
                                                    <span v-if="isLinkActive(link)" aria-hidden="true" class="h-2 w-2 rounded-full bg-primary shrink-0 mr-1"></span>
                                                </component>
                                            </nav>
                                        </div>

                                        <!-- Company & Information Section -->
                                        <div>
                                            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2 px-1">
                                                More Information
                                            </span>
                                            <div class="space-y-1">
                                                <RouterLink 
                                                    to="/about/us" 
                                                    @click="mobileMenuOpen = false"
                                                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors"
                                                >
                                                    <FileText class="w-4 h-4 text-slate-400" />
                                                    <span>About Jetze</span>
                                                </RouterLink>
                                                <RouterLink 
                                                    to="/contact/us" 
                                                    @click="mobileMenuOpen = false"
                                                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors"
                                                >
                                                    <Phone class="w-4 h-4 text-slate-400" />
                                                    <span>Contact Us</span>
                                                </RouterLink>
                                                <RouterLink 
                                                    to="/how-to-use-abhi-pay-bank-transfer" 
                                                    @click="mobileMenuOpen = false"
                                                    class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-primary transition-colors"
                                                >
                                                    <Coins class="w-4 h-4 text-slate-400" />
                                                    <span>Payment Methods</span>
                                                </RouterLink>
                                            </div>
                                        </div>

                                        <!-- 24/7 Live Support Assistance -->
                                        <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-200/70">
                                            <div class="flex items-center gap-2 text-xs font-bold text-slate-800 mb-1">
                                                <Phone class="w-3.5 h-3.5 text-primary" />
                                                <span>24/7 Live Support</span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 mb-2">Our travel specialists are always on standby.</p>
                                            <a 
                                                href="tel:+923007690691" 
                                                class="inline-flex items-center gap-1.5 text-xs font-extrabold text-primary hover:underline"
                                            >
                                                <span>Call: (+92) 300 7690691</span>
                                            </a>
                                        </div>

                                    </div>

                                    <!-- Drawer Footer (Logout / Copyright) -->
                                    <div class="p-4 border-t border-slate-100 bg-slate-50/60">
                                        <Button 
                                            v-if="isAuthenticated" 
                                            @click="mobileMenuOpen = false; handleLogout()" 
                                            variant="outline" 
                                            class="w-full h-9 rounded-xl border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700 text-xs font-bold flex items-center justify-center gap-1.5"
                                        >
                                            <LogOut class="w-3.5 h-3.5" />
                                            <span>Sign Out</span>
                                        </Button>
                                        <p class="text-center text-[10px] text-slate-400 mt-2">© {{ new Date().getFullYear() }} Jetze Travel Services</p>
                                    </div>
                                </SheetContent>
                            </Sheet>
                        </div>
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

.glass-nav {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.06));
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.22);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    border-radius: 1rem;
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
