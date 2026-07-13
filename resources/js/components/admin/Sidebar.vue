<template>
    <button
        @click="toggleSidebar"
        :class="[
            'fixed top-1/2 -translate-y-1/2 z-50 lg:hidden bg-white shadow-lg border p-2 transition-all duration-300',
            isMobileOpen ? 'left-64 rounded-r-lg' : 'left-0 rounded-r-lg'
        ]"
        >
        <ChevronLeft
            v-if="isMobileOpen"
            class="w-6 h-6"
        />
        <ChevronRight
            v-else
            class="w-6 h-6"
        />
    </button>
    <!-- Overlay for mobile -->
    <div
      v-if="isMobileOpen"
      @click="closeSidebar"
      class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden duration-300"
    ></div>


  <aside :class="[
    'fixed lg:relative w-64 h-screen bg-white border-r border-gray-200 shadow-sm flex flex-col transition-transform duration-300 z-40',
    isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
]">


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
          class="admin-sidebar-link" :class="{ 'admin-sidebar-link-active': $route.name === 'Dashboard' }"
          v-if="authStore.user.role === 'admin'">
          <LayoutDashboard class="admin-sidebar-icon"
            :class="{ 'admin-sidebar-icon-active': $route.name === 'Dashboard' }" />
          Dashboard
        </router-link>

        <router-link :to="{ name: 'AdminCustomerBookings' }"
          class="admin-sidebar-link" :class="{ 'admin-sidebar-link-active': $route.name === 'AdminCustomerBookings' }"
          v-if="authStore.hasPermission('view-bookings')">
          <Notebook class="admin-sidebar-icon"
            :class="{ 'admin-sidebar-icon-active': $route.name === 'AdminCustomerBookings' }" />
          Bookings
        </router-link>
        <router-link :to="{ name: 'VoidBookings' }"
          class="admin-sidebar-link" :class="{ 'admin-sidebar-link-active': $route.name === 'VoidBookings' }"
          v-if="authStore.hasPermission('manage-bookings')">
          <Notebook class="admin-sidebar-icon"
            :class="{ 'admin-sidebar-icon-active': $route.name === 'VoidBookings' }" />
          Void Bookings
        </router-link>
        <router-link :to="{ name: 'ModifyRequests' }"
          class="admin-sidebar-link" :class="{ 'admin-sidebar-link-active': $route.name === 'ModifyRequests' }"
          v-if="authStore.hasPermission('manage-bookings')">
          <Notebook class="admin-sidebar-icon"
            :class="{ 'admin-sidebar-icon-active': $route.name === 'ModifyRequests' }" />
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
  <div
    v-for="(section, index) in menuSections"
    :key="index"
    class="mb-2"

  >
    <button
          v-if="section?.items.some(item => !item.permission || authStore.hasPermission(item.permission))"

      @click="toggleSection(index)"
      class="flex items-center justify-between w-full px-6 py-2 text-xs font-medium text-left text-gray-700 uppercase tracking-wider hover:text-gray-900 focus:outline-none"
    >
      {{ section.title }}

      <span
        v-if="readAtCounter && section.title === 'Agent / Users'"
        class="ml-auto bg-white/50 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full"
      >
        {{ readAtCounter }}
      </span>

      <ChevronDown
        :class="{ 'transform rotate-180': openSections[index] }"
        class="w-4 h-4 transition-transform duration-200"
      />
    </button>

    <div v-show="openSections[index]" class="mt-1 space-y-1 px-3">
      <template v-for="item in section?.items" :key="item.name">

        <router-link
          v-if="!item.permission || authStore.hasPermission(item.permission)"
          :to="item.to"
          class="admin-sidebar-link"
          :class="{ 'admin-sidebar-link-active': $route.name === item.to.name }"
        >
          <component
            :is="item.icon"
            class="admin-sidebar-icon"
            :class="{ 'admin-sidebar-icon-active': $route.name === item.to.name }"
          />

          {{ item.name }}
        </router-link>

      </template>
    </div>

  </div>
</div>
    </nav>

    <!-- Settings Button -->
    <div class="p-4 border-t border-gray-100 bg-white">
      <router-link v-if="authStore.hasPermission('view-activity-logs')" :to="{ name: 'ActivityLog' }"
        class="admin-sidebar-link w-full" :class="{ 'admin-sidebar-link-active': $route.name === 'ActivityLog' }">
        <Settings class="admin-sidebar-icon" :class="{ 'admin-sidebar-icon-active': $route.name === 'ActivityLog' }" />
        User Activities
      </router-link>
      <router-link v-if="authStore.hasPermission('manage-settings')" :to="{ name: 'Setting' }"
        class="admin-sidebar-link w-full" :class="{ 'admin-sidebar-link-active': $route.name === 'Setting' }">
        <Settings class="admin-sidebar-icon" :class="{ 'admin-sidebar-icon-active': $route.name === 'Setting' }" />
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
  Shield,
  Ticket,
  Truck,
  UserPlus,
  Users,
  TagIcon,
  LandmarkIcon,
  MessageSquare,
  MailPlus,
  ChevronLeft,
  ChevronRight,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useStore } from 'vuex';
import SocialLinks from "../common/SocialLinks.vue";
import { GearIcon } from "@radix-icons/vue";
import { useAuthStore } from "@/services/stores/auth";
const store = useStore();
const authStore = useAuthStore();
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
      { name: "Popular Routes", to: { name: "PopularRoutes" }, icon: PlaneIcon, permission: 'manage-cms' },
      { name: "Hot Deals", to: { name: "HotDeals" }, icon: PlaneIcon, permission: 'manage-cms' },
      { name: "Top Airlines", to: { name: "TopAirlines" }, icon: Plane, permission: 'manage-cms' },
      { name: "Airports", to: { name: "Airports" }, icon: PlaneIcon, permission: 'manage-cms' },
      { name: "Blogs", to: { name: "Blogs" }, icon: UserPlus, permission: 'manage-cms' },
      { name: "SEO Settings", to: { name: "SeoSettings" }, icon: Settings, permission: 'manage-cms' },
      { name: "Reviews", to: { name: "AdminReviews" }, icon: MessageSquare, permission: 'manage-cms' },
      { name: "Subcribers", to: { name: "Subcribers" }, icon: MailPlus, permission: 'manage-cms' },

    ],
  },
  {
    title: "Finance",
    items: [
      {
        name: "Top up Requests",
        to: { name: "TopUpRequest" },
        icon: Coins,
        permission: 'manage-finance'
      },
      // { name: "Refund Requests", to: { name: "" }, icon: ArrowLeftRight },
      {
        name: "Other Charges",
        to: { name: "OtherCharges" },
        icon: Truck,
        permission: 'manage-finance'
      },
      {
        name: "Banks",
        to: { name: "Banks" },
        icon: Banknote,
        permission: 'manage-settings'
      },
      {
        name: "Currencies",
        to: { name: "Currencies" },
        icon: DollarSign,
        permission: 'manage-settings'
      },
      {
        name: "Admin Ledger",
        to: { name: "AdminLedger" },
        icon: Banknote,
        permission: 'view-ledger'
      },
      {
        name: "Profit Loss Report",
        to: { name: "ProfitLossReport" },
        icon: Banknote,
        permission: 'view-ledger'
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
        permission: 'manage-marketing'
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
        permission: 'manage-airlines'
      },
      // add
      {
        name: "Promotions",
        to: { name: "Promotions" },
        icon: TagIcon,
        permission: ["airlines_view"]
      },
      {
        name: "Airport Markups",
        to: { name: "AirportMarkups" },
        icon: Plane,
        permission: 'manage-airports'
      },
      // add
      {
        name: "Segment Margins",
        to: { name: "SegmentMargins" },
        icon: LandmarkIcon,
        permission: ["airlines_view"]
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
        permission: 'manage-staff'
      },
      {
        name: "Roles",
        to: { name: "Roles" },
        icon: Shield,
        permission: 'manage-roles'
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
        permission: 'manage-customers'

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
        permission: 'manage-settings'
      },
    ],
  },

];

const isMobileOpen = ref(false);
function toggleSidebar(){
    if(window.innerWidth < 1024){
        isMobileOpen.value = !isMobileOpen.value;
        if(isMobileOpen.value){
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    }
}

function closeSidebar(){
    if(window.innerWidth < 1024){
        isMobileOpen.value = false;
        document.body.classList.remove('overflow-hidden');
    }
}
function handleResize() {
    // When resizing from mobile to desktop, ensure sidebar is visible
    if (window.innerWidth > 1024) {
        // On desktop, close mobile state and reset body overflow
        isMobileOpen.value = false;
        document.body.style.overflow = '';
    }
}
onMounted(() => {
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
})
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
.overflow-y-auto::-webkit-scrollbar {
  display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.overflow-y-auto {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

</style>
