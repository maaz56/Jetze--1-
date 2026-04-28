<template>
  <aside
    class="w-64 h-screen bg-white border-r border-gray-200 shadow-sm flex flex-col">
    <!-- Logo and App Name -->
    <div class="p-4 border-b border-gray-100 bg-white/30">
      <router-link  class="flex items-center space-x-3">
        <img src="/public/assets/logo.png" alt="logo" class="w-auto h-10" />
      </router-link>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-grow overflow-y-auto py-4">
      <div class="space-y-1 px-3">
        <router-link :to="{ name: 'Dashboard' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'Dashboard',
            'text-gray-700 hover:bg-secondary/20 hover:text-gray-900': $route.name !== 'Dashboard'
          }">
          <LayoutDashboard class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'Dashboard' ? 'text-white' : 'text-gray-500'" />
          Dashboard
        </router-link>

        <router-link :to="{ name: 'AdminCustomerBookings' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'AdminCustomerBookings',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'AdminCustomerBookings'
          }">
          <Notebook class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'AdminCustomerBookings' ? 'text-white' : 'text-gray-500'" />
          Bookings
        </router-link>
        <router-link :to="{ name: 'VoidBookings' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'VoidBookings',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'VoidBookings'
          }">
          <Notebook class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'VoidBookings' ? 'text-white' : 'text-gray-500'" />
          Void Bookings
        </router-link>
        <router-link :to="{ name: 'ModifyRequests' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'ModifyRequests',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'ModifyRequests'
          }">
          <Notebook class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'ModifyRequests' ? 'text-white' : 'text-gray-500'" />
          Modify Requests
          </router-link>
        <!-- <router-link :to="{ name: 'OfflineBookings' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'OfflineBookings',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'OfflineBookings'
          }">
          <Notebook class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'OfflineBookings' ? 'text-white' : 'text-gray-500'" />
          Offline Bookings
        </router-link> -->
        <!-- <router-link :to="{ name: 'DirectBookings' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'DirectBookings',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'DirectBookings'
          }">
          <Notebook class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'DirectBookings' ? 'text-gray-600' : 'text-gray-500'" />
          Direct Bookings
        </router-link> -->
        <!-- <router-link :to="{ name: 'ImportPnr' }"
          class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
            'bg-secondary text-white font-medium': $route.name === 'ImportPnr',
            'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== 'ImportPnr'
          }">
          <Ticket class="w-5 h-5 mr-3 flex-shrink-0"
            :class="$route.name === 'ImportPnr' ? 'text-white' : 'text-gray-500'" />
          Import PNRs
        </router-link> -->
      </div>

      <div class="mt-6 space-y-2">
        <div v-for="(section, index) in menuSections" :key="index" class="mb-2">
          <button @click="toggleSection(index)"
            class="flex items-center justify-between w-full px-6 py-2 text-xs font-medium text-left text-gray-700 uppercase tracking-wider hover:text-gray-900 focus:outline-none">
            {{ section.title }} <span v-if="readAtCounter && section.title === 'Agent / Users'"
              class="ml-auto bg-white/50 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
              {{ readAtCounter }}
            </span>
            <ChevronDown :class="{ 'transform rotate-180': openSections[index] }"
              class="w-4 h-4 transition-transform duration-200" />
          </button>

          <div v-show="openSections[index]" class="mt-1 space-y-1 px-3">
            <router-link v-for="item in section.items" :key="item.name" :to="item.to"
              class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors duration-200" :class="{
                'bg-white/30 text-gray-700 font-medium': $route.name === item.to.name,
                'text-gray-700 hover:bg-white/20 hover:text-gray-900': $route.name !== item.to.name
              }">
              <component :is="item.icon" class="w-5 h-5 mr-3 flex-shrink-0"
                :class="$route.name === item.to.name ? 'text-gray-600' : 'text-gray-500'" />
              {{ item.name }}
            </router-link>
          </div>
        </div>
      </div>
    </nav>

    <!-- Settings Button -->
    <div class="p-4 border-t border-gray-100 bg-white">
      <router-link :to="{ name: 'ActivityLog' }"
        class="flex items-center w-full px-3 py-2 text-sm rounded-lg text-gray-700 hover:bg-white/20 hover:text-gray-900 transition-colors duration-200">
        <Settings class="w-5 h-5 mr-3 text-gray-500" />
        User Activities
      </router-link>
      <router-link :to="{ name: 'Setting' }"
        class="flex items-center w-full px-3 py-2 text-sm rounded-lg text-gray-700 hover:bg-white/20 hover:text-gray-900 transition-colors duration-200">
        <Settings class="w-5 h-5 mr-3 text-gray-500" />
        Settings
      </router-link>
    </div>
    <SocialLinks />

  </aside>
</template>

<script setup>
import {
  ArrowLeftRight,
  Banknote,
  ChevronDown,
  Coins,
  DollarSign,
  GalleryHorizontal,
  Landmark,
  LayoutDashboard,
  Notebook,
  Package,
  PencilRuler,
  Plane,
  PlaneIcon,
  Settings,
  ShoppingCart,
  Ticket,
  Truck,
  UserPlus,
  Users
} from "lucide-vue-next";
import { computed, ref } from "vue";
import { useStore } from 'vuex';
import SocialLinks from "../common/SocialLinks.vue";
import { GearIcon } from "@radix-icons/vue";
const store = useStore();
const menuSections = [
  // {
  //   title: "Agent / Users",
  //   items: [
  //     { name: "Agents", to: { name: "Users" }, icon: Users },
  //     { name: "New Agent", to: { name: "NewUser" }, icon: UserPlus },
  //   ],
  // },
   {
    title: "CMS",
    items: [
      { name: "Popular Routes", to: { name: "PopularRoutes" }, icon: PlaneIcon },
      { name: "Blogs", to: { name: "Blogs" }, icon: UserPlus },
    ],
  },
  {
    title: "Finance",
    items: [
      {
        name: "Top up Requests",
        to: { name: "TopUpRequest" },
        icon: Coins,
      },
      // { name: "Refund Requests", to: { name: "" }, icon: ArrowLeftRight },
      {
        name: "Other Charges",
        to: { name: "OtherCharges" },
        icon: Truck,
      },
      {
        name: "Banks",
        to: { name: "Banks" },
        icon: Banknote,
      },
      {
        name: "Currencies",
        to: { name: "Currencies" },
        icon: DollarSign,
      },
      {
        name: "Admin Ledger",
        to: { name: "AdminLedger" },
        icon: Banknote,
      },
      {
        name: "Profit Loss Report",
        to: { name: "ProfitLossReport" },
        icon: Banknote,
      },
    ],
  },
  // {
  //   title: "Travel",
  //   items: [
  //     { name: "Visas", to: { name: "Visas" }, icon: Landmark },
  //     { name: "Holidays", to: { name: "Holidays" }, icon: PencilRuler },
  //     // { name: "DubaiAttraction", to: { name: "DubaiAttraction" }, icon: PencilRuler },
  //     {
  //       name: "Umrah Packages",
  //       to: { name: "UmrahPackages" },
  //       icon: Package,
  //     },
  //   ],
  // },
  {
    title: "Marketing",
    items: [
      {
        name: "Banners",
        to: { name: "Banners" },
        icon: GalleryHorizontal,
      },
    ],
  },
  {
    title: "Markups/Discounts",
    items: [
      {
        name: "Airlines",
        to: { name: "Airlines" },
        icon: Plane,
      },
      {
        name: "Airport Markups",
        to: { name: "AirportMarkups" },
        icon: Plane,
      },
    ],
  },
  {
    title: "Office",
    items: [
      {
        name: "Staff",
        to: { name: "Staff" },
        icon: Users,
      },
    ],
  },
  {
    title: "Customers",
    items: [
      {
        name: "Customers",
        to: { name: "Customers" },
        icon: Users,
      },
      // {
      //   name: "Customer Bookings",
      //   to: { name: "AdminCustomerBookings" },
      //   icon: ShoppingCart,
      // },
      {
        name: "Customer Settings",
        to: { name: "CustomerMargin" },
        icon: GearIcon,
      },
    ],
  },
  
];
const fetchedNotifications = computed(() => store.getters['notification/notifications'] || []);
const readAtCounter = computed(() => {
  return fetchedNotifications.value.filter(n => !n.read_at).length;
});

// Initialize all sections as closed
const openSections = ref(menuSections.map(() => false));

// Toggle section open/closed state
const toggleSection = (index) => {
  openSections.value[index] = !openSections.value[index];
};
</script>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.overflow-y-auto::-webkit-scrollbar {
  display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.overflow-y-auto {
  -ms-overflow-style: none;
  /* IE and Edge */
  scrollbar-width: none;
  /* Firefox */
}
</style>