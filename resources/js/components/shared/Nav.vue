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

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
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

// Updated navLinks to match the "Title + Subtitle" style of the image
const navLinks = [
    { routeName: "Home", text: "Flights", subText: "Book Cheapest", icon: "/plane.png" },
    { href: "/about/us", text: "About Us", subText: "Who we are", icon: "/info.png" },
    { href: "/blogs", text: "Blogs", subText: "Travel Stories", icon: "/blog.png" },
    { href: "/contact/us", text: "Contact Us", subText: "UAN (+92) 300 7690691", icon: "/info.png" },
];

const getLinkProps = (link) => link.routeName
    ? { to: { name: link.routeName } }
    : { href: link.href };

const isLinkActive = (link) => {
    if (link.routeName) return route.name === link.routeName;

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
            'fixed top-0 inset-x-0 z-40 text-slate-900 transition-all duration-300',
            isScrolled
                ? 'bg-white/95 backdrop-blur-md shadow-2xl border-b border-slate-200'
                : 'bg-white/92 backdrop-blur-md border-b border-slate-200',
        ]"
    >
            <div class="container mx-auto px-4">
                <div class=" flex items-center justify-between h-20 px-4 lg:px-6">
                
                    <router-link :to="{ name: 'Home' }" class="flex items-center shrink-0">
                        <img src="/public/assets/logo.png" alt="Logo" class="h-10 lg:h-12" />
                    </router-link>

                <div class="flex items-center">
                    
                    <div class="hidden xl:flex items-center">
                        <component
                            :is="link.routeName ? RouterLink : 'a'"
                            v-for="(link, index) in navLinks" 
                            :key="index" 
                            v-bind="getLinkProps(link)"
                            :class="[
                                'group flex items-center px-5 py-2 border-r border-slate-200 last:border-r-0 transition-all',
                                isLinkActive(link) ? 'text-primary' : 'hover:bg-slate-100 text-slate-700',
                            ]"
                        >
                            <div class="p-2 bg-slate-100 rounded-full mr-3 group-hover:bg-primary/15 transition-transform">
                                <img
                                    :src="link.icon"
                                    :alt="link.text"
                                    :class="[
                                        'w-5 h-5 object-contain transition-all duration-300',
                                        isLinkActive(link) ? 'scale-110' : 'grayscale opacity-60',
                                    ]"
                                />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[13px] font-bold leading-tight">{{ link.text }}</span>
                                <span
                                    :class="[
                                        'text-[10px] transition-colors',
                                        isLinkActive(link) ? 'text-primary/80' : 'text-slate-500 group-hover:text-slate-700',
                                    ]"
                                >
                                    {{ link.subText }}
                                </span>
                            </div>
                        </component>
                    </div>

                    <div v-if="user" class="hidden md:flex items-center px-4 border-l border-slate-200 gap-4 shrink-0 whitespace-nowrap">
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Balance</span>
                            <div class="flex items-center font-bold text-green-600">
                                <Wallet class="h-3 w-3 mr-1" />
                                <span class="text-sm">{{ selectedCurrencySymbol }}</span>
                                <span class="mx-1 text-slate-300">|</span>
                                <span class="text-sm">{{ formatBalanceAmount(agentLedger?.balance_money?.amount) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Currency</span>
                            <Select v-model="selectedCurrencyCode">
                                <SelectTrigger class="h-9 w-[92px] rounded border border-slate-200 bg-white px-2 text-sm font-medium text-slate-700 shadow-sm focus:ring-0">
                                    <SelectValue placeholder="Currency" />
                                </SelectTrigger>
                                <SelectContent :body-lock="false" class="rounded border border-slate-200 bg-white shadow-xl">
                                    <SelectItem
                                        v-for="currency in currencyOptions"
                                        :key="currency.code"
                                        :value="currency.code"
                                    >
                                        {{ currency.code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <button @click="goToDashboard('deposits')" class="bg-green-600 hover:bg-green-500 p-1.5 rounded-full transition-colors flex-none">
                            <Coins class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex items-center ml-4 space-x-3">
                        
                        <button v-if="!isAuthenticated" @click="handleLogin"
                            class="bg-white border border-slate-300 hover:bg-slate-100 px-4 py-2 rounded-md shadow-sm flex items-center space-x-3 transition-all text-slate-900">
                            <div class="bg-primary/10 p-1 rounded-full">
                                <LogIn class="w-4 h-4 text-primary" />
                            </div>
                            <div class="flex flex-col items-start leading-none">
                                <span class="text-[11px] font-medium text-slate-500">Login or</span>
                                <span class="text-sm font-bold">Create Account</span>
                            </div>
                            <ChevronDown class="w-4 h-4 text-slate-400" />
                        </button>

                        <DropdownMenu v-if="isAuthenticated">
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" class="h-12 w-12 p-0 rounded-full ring-2 ring-slate-300 overflow-hidden hover:ring-primary transition-all">
                                    <img v-if="user?.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full bg-primary flex items-center justify-center font-bold">
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
                                <Button variant="ghost" size="icon" class="xl:hidden text-slate-900 hover:bg-slate-100">
                                    <Menu class="h-6 w-6" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" class="w-[300px] bg-slate-900 text-white border-r-slate-800">
                                <div class="py-6">
                                    <img class="h-10 mb-8" src="/public/assets/logo.png" alt="Logo" />
                                    <nav class="space-y-4">
                                        <component :is="link.routeName ? RouterLink : 'a'" v-for="link in navLinks"
                                            :key="link.routeName || link.href" v-bind="getLinkProps(link)"
                                            class="flex items-center p-3 rounded-lg hover:bg-white/5">
                                            <img :src="link.icon" :alt="link.text" class="w-5 h-5 mr-3 object-contain" />
                                            <div>
                                                <p class="font-bold text-sm">{{ link.text }}</p>
                                                <p class="text-[10px] opacity-60">{{ link.subText }}</p>
                                            </div>
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
        <div aria-hidden="true" class="h-20"></div>
    </div>
</template>

<style scoped>
.glass-nav {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.06));
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.22);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    border-radius: 1rem;
}

.group:hover {
    box-shadow: inset 0 -2px 0 0 #008cff;
}
</style>
