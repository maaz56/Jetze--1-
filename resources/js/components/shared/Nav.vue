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
const emit = defineEmits(['login-click', 'search-click'])
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
    isScrolled.value = window.scrollY > 140;
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
                    ? 'bg-gradient-to-b from-slate-950/80 via-slate-950/35 to-transparent text-white py-1'
                    : 'bg-white shadow-md border-b border-slate-200/80 text-slate-900 py-0 animate-slide-down-nav',
            ]"
        >
            <div class="container shared-nav-content mx-auto px-4">
                <div class="flex items-center justify-between h-20 px-4 lg:px-6">
                
                    <!-- Logo without box, using ambient glow when transparent -->
                    <router-link :to="{ name: 'Home' }" class="flex items-center shrink-0">
                        <img 
                            src="/public/assets/logo.png" 
                            alt="Logo" 
                            :class="[
                                'h-10 lg:h-11 w-auto object-contain transition-all duration-300 hover:scale-105',
                                isTransparent ? 'drop-shadow-[0_2px_12px_rgba(255,255,255,0.95)] filter' : ''
                            ]" 
                        />
                    </router-link>

                    <div class="flex items-center">
                        
                        <!-- Nav Links Pills -->
                        <div class="hidden lg:flex items-center gap-2">
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
                        </div>

                        <!-- Balance & Currency for Logged-in User -->
                        <div v-if="user" :class="['hidden md:flex items-center px-4 gap-4 shrink-0 whitespace-nowrap border-l', isTransparent ? 'border-white/20' : 'border-slate-200']">
                            <div class="flex items-center gap-2">
                                <span :class="['text-[11px] uppercase tracking-wider font-bold', isTransparent ? 'text-slate-300' : 'text-slate-500']">Balance:</span>
                                <div class="flex items-center font-bold text-emerald-400 text-xs sm:text-sm">
                                    <Wallet class="h-3.5 w-3.5 mr-1 shrink-0" />
                                    <span>{{ selectedCurrencySymbol }} {{ formatBalanceAmount(agentLedger?.balance_money?.amount) }}</span>
                                </div>
                                <button
                                    @click="goToDashboard('deposits')"
                                    :class="['group ml-1.5 inline-flex items-center gap-1 border-l pl-2 text-xs font-bold transition-colors focus:outline-none', isTransparent ? 'border-white/20 text-sky-300 hover:text-sky-200' : 'border-slate-200 text-primary hover:text-primary/75']"
                                >
                                    <Coins class="h-3.5 w-3.5 transition-transform group-hover:rotate-12" />
                                    Top Up
                                </button>
                            </div>

                            <div class="flex items-center gap-2 border-l pl-4" :class="isTransparent ? 'border-white/20' : 'border-slate-200'">
                                <span :class="['text-[11px] uppercase tracking-wider font-bold', isTransparent ? 'text-slate-300' : 'text-slate-500']">Currency:</span>
                                <Select v-model="selectedCurrencyCode">
                                    <SelectTrigger :class="['h-8.5 w-[84px] rounded-lg border px-2.5 text-xs font-bold shadow-xs focus:ring-0', isTransparent ? 'border-white/30 bg-slate-900/60 text-white backdrop-blur-md' : 'border-slate-200 bg-white text-slate-700']">
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
                        </div>

                        <!-- Login Button / User Dropdown -->
                        <div class="flex items-center ml-4 space-x-3">
                            <button 
                                v-if="!isAuthenticated" 
                                @click="handleLogin"
                                :class="[
                                    'px-4 py-2 rounded-xl shadow-sm flex items-center space-x-3 transition-all duration-300 border',
                                    isTransparent
                                        ? 'bg-white/15 hover:bg-white/25 text-white border-white/30 backdrop-blur-md shadow-md'
                                        : 'bg-white border-slate-300 hover:bg-slate-50 text-slate-900'
                                ]"
                            >
                                <div :class="['p-1 rounded-full', isTransparent ? 'bg-white/20' : 'bg-primary/10']">
                                    <LogIn :class="['w-4 h-4', isTransparent ? 'text-sky-300' : 'text-primary']" />
                                </div>
                                <div class="flex flex-col items-start leading-none">
                                    <span :class="['text-[11px] font-medium', isTransparent ? 'text-slate-200' : 'text-slate-500']">Login or</span>
                                    <span :class="['text-sm font-bold', isTransparent ? 'text-white' : 'text-slate-900']">Create Account</span>
                                </div>
                                <ChevronDown :class="['w-4 h-4', isTransparent ? 'text-slate-200' : 'text-slate-400']" />
                            </button>

                            <DropdownMenu v-if="isAuthenticated">
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" class="h-11 w-11 p-0 rounded-full ring-2 ring-white/50 overflow-hidden hover:ring-primary transition-all">
                                        <img v-if="user?.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                                        <div v-else class="h-full w-full bg-primary text-white flex items-center justify-center font-bold">
                                            {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                                        </div>
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-56 bg-white border-slate-200 text-slate-900 rounded-xl shadow-2xl">
                                    <DropdownMenuLabel class="text-slate-500 text-xs">My Account</DropdownMenuLabel>
                                    <DropdownMenuSeparator class="bg-slate-200" />
                                    <DropdownMenuItem @click="goToDashboard('profile')" class="cursor-pointer focus:bg-slate-100 focus:text-slate-900">
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

                            <Sheet>
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
                                <SheetContent side="left" class="w-[300px] bg-slate-900 text-white border-r-slate-800">
                                    <div class="py-6">
                                        <img class="h-10 mb-8" src="/public/assets/logo.png" alt="Logo" />
                                        <nav class="space-y-4">
                                            <component :is="link.routeName ? RouterLink : 'a'" v-for="link in navLinks"
                                                :key="link.routeName || link.href" v-bind="getLinkProps(link)"
                                                :aria-current="isLinkActive(link) ? 'page' : undefined"
                                                :class="[
                                                    'relative flex items-center rounded-xl p-3 transition-colors',
                                                    isLinkActive(link) ? 'text-sky-300' : 'hover:bg-white/10',
                                                ]">
                                                <div :class="['mr-3 rounded-lg p-2', isLinkActive(link) ? 'bg-sky-400/20' : 'bg-sky-400/15']">
                                                    <img :src="link.icon" :alt="link.text" class="w-5 h-5 object-contain" />
                                                </div>
                                                <div>
                                                    <p class="font-bold text-sm">{{ link.text }}</p>
                                                    <p :class="['text-[10px]', isLinkActive(link) ? 'text-sky-300/75' : 'text-slate-400']">{{ link.subText }}</p>
                                                </div>
                                                <span v-if="isLinkActive(link)" aria-hidden="true" class="absolute inset-x-3 bottom-0 h-[3px] rounded-sm bg-primary"></span>
                                            </component>
                                        </nav>
                                        <div class="mt-8 pt-8 border-t border-white/10">
                                            <Button v-if="!isAuthenticated" @click="handleLogin" class="w-full bg-blue-600">Login</Button>
                                            <Button v-else @click="handleLogout" variant="outline" class="w-full border-white/20 text-white hover:bg-white/10">Logout</Button>
                                        </div>
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
