<script setup>
import {
  BadgeDollarSign,
  Boxes,
  Landmark,
  LayoutDashboard,
  PencilRuler,
  Truck,
  Users,
  Package,
  Activity,
  ArrowLeftRight,
  ArrowUpFromDot,
  Coins,
  Notebook,
  CoinsIcon,
  Wallet,
  Settings,
  PlaneTakeoffIcon,
  ShoppingCartIcon,
  SettingsIcon,
  MenuIcon,
  ChevronRightIcon,
  HomeIcon,
  ChevronLeftIcon,UserPlus,
  UserIcon
} from "lucide-vue-next";
import { computed, onMounted, ref, watch, inject } from "vue";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";
import { useRoute } from 'vue-router';
import SocialLinks from "../common/SocialLinks.vue";

const authStore = useAuthStore();
const store = useStore();

const user = computed(() => authStore.user);
const agentData = computed(() => store.getters["user/agentData"]);
const user_id = computed(() => user.value?.id);
const error = ref(null);
const loading = ref(true);

// Get sidebar state from parent component if available
const sidebarState = inject('sidebarState', null);
const isExpanded = ref(sidebarState ? sidebarState.isExpanded.value : true);

// Watch for changes from parent
if (sidebarState) {
  watch(() => sidebarState.isExpanded.value, (newValue) => {
    isExpanded.value = newValue;
  });
}

const route = useRoute();

const menuItems = [
{ name: "Profile", icon: UserIcon, route: { name: "CustomerProfile" } },
{ name: "Wallet", icon: Wallet, route: { name: "CustomerWallet" } },
{ name: "Bookings", icon: ShoppingCartIcon, route: { name: "CustomerBookings" } },
 
];

const toggleSidebar = () => {
  isExpanded.value = !isExpanded.value;
  
  // Emit event to parent component
  emit('toggle-sidebar', isExpanded.value);
  
  // Also update the injected state if available
  if (sidebarState) {
    sidebarState.toggle(isExpanded.value);
  }
};

const isActive = (routeName) => {
  return route.name === routeName;
};

function fetchUser() {
  authStore.fetchUser();
}

function fetchAgent() {
  if (user_id.value) {
    try {
      store.dispatch("user/" + FETCH_AGENT_DATA, {
        userId: user_id.value,
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

const logout = () => {
    authStore.logout();
};

// Define emits
const emit = defineEmits(['toggle-sidebar']);

onMounted(() => {
  fetchUser();
  fetchAgent();
});
</script>

<template>
  <div
    class="flex flex-col h-screen transition-all duration-300 ease-in-out bg-white"
    :class="{ 'w-[280px]': isExpanded, 'w-[70px]': !isExpanded }"
  >
    <!-- Header -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-100 bg-white/30">
      <router-link :to="{ name: 'Home' }" class="flex items-center space-x-3">
        
        <div v-if="isExpanded" class="flex flex-col">
          <span class="font-semibold text-gray-800 text-sm">{{ user?.name || 'Customer Profile' }}</span>
          <span class="text-xs text-gray-500  max-w-[140px]">{{ user?.email || 'agent@example.com' }}</span>
        </div>
      </router-link>
      <button 
        @click="toggleSidebar" 
        class="text-gray-500 hover:text-gray-800 hover:bg-gray-100/50 rounded-md p-1 transition-colors duration-200 focus:outline-none"
      >
        <ChevronLeftIcon v-if="isExpanded" class="w-5 h-5" />
        <ChevronRightIcon v-else class="w-5 h-5" />
      </button>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-6 px-3">
      <div v-if="isExpanded" class="mb-4 px-3">
        <h3 class="text-xs font-medium uppercase text-gray-700 tracking-wider">Main Menu</h3>
      </div>
      <ul class="space-y-1">
        <li v-for="(item, index) in menuItems" :key="index">
          <router-link 
            :to="item.route"
            class="flex items-center rounded-lg transition-colors duration-200 group relative"
            :class="[
              isActive(item.route.name) 
                ? 'bg-secondary text-white font-medium' 
                : 'text-gray-700 hover:bg-white/30 hover:text-gray-900',
              isExpanded ? 'px-3 py-2' : 'justify-center py-3'
            ]"
          >
            <!-- Add a left border indicator for active items -->
            
            
            <component 
              :is="item.icon" 
              :class="[
                'flex-shrink-0',
                isActive(item.route.name) 
                  ? 'text-white' 
                  : 'text-gray-700 group-hover:text-gray-900',
                isExpanded ? 'w-5 h-5 mr-3' : 'w-6 h-6'
              ]" 
            />
            <span v-if="isExpanded" class="text-sm font-medium">{{ item.name }}</span>
            
            <!-- Badge for notifications (example) -->
            <span 
              v-if="isExpanded && item.name === 'Bookings'" 
              class="ml-auto bg-white/50 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full"
            >
              New
            </span>
          </router-link>
        </li>
      </ul>
      
      
     
    </nav>
    
    <!-- Footer -->
    <div class="p-4 border-t border-gray-100/50">
      <router-link 
        :to="{ name: 'Settings' }"
        class="flex items-center rounded-lg transition-colors duration-200 group relative mb-2"
        :class="[
          isActive('Settings') 
            ? 'bg-white/50 text-gray-800 font-medium' 
            : 'text-gray-700 hover:bg-white/30 hover:text-gray-900',
          isExpanded ? 'px-3 py-2' : 'justify-center py-3'
        ]"
      >
        <!-- Add a left border indicator for active items -->
        <div 
          v-if="isActive('Settings')" 
          class="absolute left-0 top-0 bottom-0 w-1 bg-gray-600 rounded-l-lg"
        ></div>
        
        <SettingsIcon 
          :class="[
            'flex-shrink-0',
            isActive('Settings') 
              ? 'text-gray-600' 
              : 'text-gray-700 group-hover:text-gray-900',
            isExpanded ? 'w-5 h-5 mr-3' : 'w-6 h-6'
          ]" 
        />
        <span v-if="isExpanded" class="text-sm font-medium">Settings</span>
      </router-link>
      
      <!-- Logout Button -->
      <button 
        @click="logout()" 
        class="w-full flex items-center rounded-lg transition-colors duration-200 group relative text-gray-700 hover:bg-white/30 hover:text-red-700"
        :class="[
          isExpanded ? 'px-3 py-2 justify-start' : 'justify-center py-3'
        ]"
      >
        <svg 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 24 24" 
          fill="none" 
          stroke="currentColor" 
          stroke-width="2" 
          stroke-linecap="round" 
          stroke-linejoin="round" 
          :class="[
            'flex-shrink-0 text-gray-700 group-hover:text-red-600',
            isExpanded ? 'w-5 h-5 mr-3' : 'w-6 h-6'
          ]"
        >
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span v-if="isExpanded" class="text-sm font-medium">Logout</span>
      </button>
      <SocialLinks />
    </div>
  </div>
</template>