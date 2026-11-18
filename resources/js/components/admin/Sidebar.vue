<template>
  <!-- Mobile Toggle Button -->
  <button
    type="button"
    @click="toggleSidebar"
    aria-label="Toggle navigation drawer"
    :class="[
      'fixed top-20 z-50 lg:hidden bg-white text-slate-700 shadow-md border border-slate-200 border-l-0 p-2 transition-all duration-300 hover:text-primary hover:bg-slate-50 focus:outline-none',
      isMobileOpen ? 'left-64 rounded-r' : 'left-0 rounded-r'
    ]"
  >
    <ChevronLeft v-if="isMobileOpen" class="w-5 h-5" />
    <ChevronRight v-else class="w-5 h-5" />
  </button>

  <!-- Mobile Backdrop Overlay -->
  <div
    v-if="isMobileOpen"
    @click="closeSidebar"
    class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 lg:hidden transition-opacity duration-300"
  ></div>

  <!-- Sidebar Aside Container -->
  <aside
    v-bind="attrs"
    :class="[
      'fixed lg:relative w-64 h-screen bg-white border-r border-slate-200 shadow-xs flex flex-col transition-transform duration-300 ease-in-out z-40 select-none',
      isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]"
  >
    <!-- Brand Header (Pixel-aligned with Nav.vue 64px h-16) -->
    <div class="h-16 px-4 border-b border-slate-200 bg-white flex items-center justify-between flex-shrink-0">
      <router-link :to="{ name: 'Dashboard' }" class="flex items-center gap-2.5 group min-w-0">
        <div class="h-9 w-9 rounded border border-slate-200 bg-slate-50 p-1 flex items-center justify-center group-hover:border-primary/40 transition-colors flex-shrink-0">
          <img src="/public/assets/logo.png" alt="Jetze" class="h-7 w-auto object-contain" />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-xs font-bold tracking-tight text-slate-900 group-hover:text-primary transition-colors leading-none truncate">
            JETZE
          </span>
          <span class="text-[11px] font-medium text-slate-500 mt-1 flex items-center gap-1.5 leading-none">
            <span class="h-1.5 w-1.5 rounded-sm bg-emerald-500 animate-pulse flex-shrink-0"></span>
            Admin Operations
          </span>
        </div>
      </router-link>

      <!-- Mobile Close Button -->
      <button
        type="button"
        @click="closeSidebar"
        class="lg:hidden p-1.5 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none"
        aria-label="Close sidebar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Quick Navigation Search Bar -->
    <div class="px-3 pt-2.5 pb-2 border-b border-slate-100 flex-shrink-0">
      <div class="relative">
        <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Quick jump / filter..."
          class="w-full pl-8 pr-7 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded text-slate-800 placeholder-slate-400 focus:outline-none focus:border-primary focus:bg-white transition-all"
        />
        <button
          v-if="searchQuery"
          type="button"
          @click="searchQuery = ''"
          class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded focus:outline-none"
          aria-label="Clear filter"
        >
          <X class="w-3 h-3" />
        </button>
      </div>
    </div>

    <!-- Navigation Scrollable Area -->
    <nav class="flex-grow overflow-y-auto px-3 py-2.5 space-y-3.5 custom-sidebar-scroll">
      <!-- Main Core Links -->
      <div v-if="coreLinks.length > 0" class="space-y-1">
        <div class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
          <span>Main Menu</span>
          <span v-if="searchQuery" class="text-[11px] lowercase font-normal text-slate-400">
            {{ coreLinks.length }} found
          </span>
        </div>

        <router-link
          v-for="link in coreLinks"
          :key="link.name"
          :to="link.to"
          class="group flex items-center justify-between px-2.5 py-1.5 text-[12.5px] font-semibold rounded transition-all duration-150"
          :class="[
            isLinkActive(link)
              ? 'bg-primary text-white shadow-xs'
              : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
          ]"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <component
              :is="link.icon"
              class="w-4 h-4 flex-shrink-0 transition-colors"
              :class="isLinkActive(link) ? 'text-white' : 'text-slate-400 group-hover:text-primary'"
            />
            <span class="truncate">{{ link.name }}</span>
          </div>

          <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
            <!-- Dynamic Badges -->
            <span
              v-if="link.badge > 0"
              class="px-1.5 py-0.5 text-[10px] font-bold rounded leading-none"
              :class="link.badgeColor"
            >
              {{ link.badge }}
            </span>
            <!-- Active Indicator Pill -->
            <span
              v-else-if="isLinkActive(link)"
              class="h-1.5 w-1.5 rounded-sm bg-white"
            ></span>
          </div>
        </router-link>
      </div>

      <!-- Collapsible Modules / Sections (One open at a time) -->
      <div v-if="filteredMenuSections.length > 0" class="space-y-1.5 pt-1 border-t border-slate-100">
        <div
          v-for="section in filteredMenuSections"
          :key="section.title"
          class="space-y-1"
        >
          <!-- Section Header Button -->
          <button
            type="button"
            @click="toggleSection(section.originalIndex)"
            class="group flex items-center justify-between w-full px-2 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-slate-900 rounded transition-colors focus:outline-none"
          >
            <span class="truncate">{{ section.title }}</span>

            <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
              <!-- Collapsed Section Unread Badge -->
              <span
                v-if="sectionUnreadCount(section) > 0"
                class="px-1.5 py-0.5 text-[10px] font-bold normal-case text-white bg-rose-500 rounded leading-none"
              >
                {{ sectionUnreadCount(section) }}
              </span>

              <ChevronDown
                class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180 text-slate-700': isSectionOpen(section) }"
              />
            </div>
          </button>

          <!-- Section Sub-items with Clean Vertical Guide Line -->
          <div
            v-show="isSectionOpen(section)"
            class="space-y-0.5 border-l-2 border-slate-200/90 ml-3 pl-2 py-0.5 transition-all"
          >
            <router-link
              v-for="item in section.items"
              :key="item.name"
              :to="item.to"
              class="group flex items-center justify-between px-2 py-1 text-[12px] rounded transition-all duration-150"
              :class="[
                isItemActive(item)
                  ? 'bg-primary/10 text-primary font-bold'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 font-medium'
              ]"
            >
              <div class="flex items-center gap-2 min-w-0">
                <component
                  :is="item.icon"
                  class="w-3.5 h-3.5 flex-shrink-0 transition-colors"
                  :class="isItemActive(item) ? 'text-primary' : 'text-slate-400 group-hover:text-primary'"
                />
                <span class="truncate">{{ item.name }}</span>
              </div>

              <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                <span
                  v-if="item.notify && unreadCount(item.notify) > 0"
                  class="px-1.5 py-0.5 text-[10px] font-bold text-white bg-rose-500 rounded leading-none"
                >
                  {{ unreadCount(item.notify) }}
                </span>
                <span
                  v-else-if="isItemActive(item)"
                  class="h-1.5 w-1.5 rounded-sm bg-primary"
                ></span>
              </div>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Search Empty State -->
      <div
        v-if="searchQuery && coreLinks.length === 0 && filteredMenuSections.length === 0"
        class="px-3 py-6 text-center text-xs text-slate-500"
      >
        <p class="font-medium text-slate-700">No navigation items found</p>
        <p class="mt-1 text-[11px] text-slate-400">Try a different search keyword</p>
        <button
          type="button"
          @click="searchQuery = ''"
          class="mt-2.5 inline-flex items-center px-2.5 py-1 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded transition"
        >
          Clear Filter
        </button>
      </div>
    </nav>

    <!-- Bottom Footer / User Activities -->
    <div class="p-3 border-t border-slate-200 bg-white space-y-2 flex-shrink-0">
      <router-link
        v-if="authStore.hasPermission('view-activity-logs')"
        :to="{ name: 'ActivityLog' }"
        class="group flex items-center justify-between px-2.5 py-1.5 text-[12.5px] font-semibold rounded transition-all duration-150"
        :class="[
          $route.name === 'ActivityLog'
            ? 'bg-primary text-white shadow-xs'
            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
        ]"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <Activity
            class="w-4 h-4 flex-shrink-0 transition-colors"
            :class="$route.name === 'ActivityLog' ? 'text-white' : 'text-slate-400 group-hover:text-primary'"
          />
          <span class="truncate">User Activities</span>
        </div>
        <span
          v-if="$route.name === 'ActivityLog'"
          class="h-1.5 w-1.5 rounded-sm bg-white"
        ></span>
      </router-link>

      <!-- System Status / Version Indicator -->
      <div class="flex items-center justify-between px-2 pt-1 text-xs text-slate-400">
        <span class="font-medium text-slate-500">Jetze Admin</span>
        <span class="inline-flex items-center gap-1.5 text-[11px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
          <span class="h-1.5 w-1.5 rounded-sm bg-emerald-500"></span>
          Live
        </span>
      </div>

      <SocialLinks />
    </div>
  </aside>
</template>

<script setup>
import {
  Activity,
  Banknote,
  CalendarDays,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Coins,
  DollarSign,
  GalleryHorizontal,
  Inbox,
  LandmarkIcon,
  LayoutDashboard,
  MailPlus,
  MessageSquare,
  Notebook,
  Plane,
  PlaneIcon,
  Search,
  Settings,
  Shield,
  TagIcon,
  TrendingUp,
  Truck,
  UserPlus,
  Users,
  X,
} from "lucide-vue-next";
import { GearIcon } from "@radix-icons/vue";
import { computed, onBeforeUnmount, onMounted, ref, useAttrs, watch } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";
import { ADMIN_NOTIFICATION_SECTIONS, notificationKind } from "@/lib/notificationKinds";
import SocialLinks from "../common/SocialLinks.vue";
import { useAuthStore } from "@/services/stores/auth";
import apiService from "@/services/store/apiService";

const store = useStore();
const authStore = useAuthStore();
const route = useRoute();

defineOptions({
  inheritAttrs: false,
});
const attrs = useAttrs();

const menuSections = [
  {
    title: "CMS",
    items: [
      { name: "Popular Routes", to: { name: "PopularRoutes" }, icon: PlaneIcon, permission: "manage-cms" },
      { name: "Hot Deals", to: { name: "HotDeals" }, icon: PlaneIcon, permission: "manage-cms" },
      { name: "Top Airlines", to: { name: "TopAirlines" }, icon: Plane, permission: "manage-cms" },
      { name: "Airports", to: { name: "Airports" }, icon: PlaneIcon, permission: "manage-cms" },
      { name: "Blogs", to: { name: "Blogs" }, icon: UserPlus, permission: "manage-cms" },
      { name: "SEO Settings", to: { name: "SeoSettings" }, icon: Settings, permission: "manage-cms" },
      { name: "Reviews", to: { name: "AdminReviews" }, icon: MessageSquare, permission: "manage-cms" },
      { name: "Subcribers", to: { name: "Subcribers" }, icon: MailPlus, permission: "manage-cms" },
    ],
  },
  {
    title: "Finance",
    items: [
      {
        name: "Top up Requests",
        to: { name: "TopUpRequest" },
        icon: Coins,
        notify: "deposit_submitted",
        permission: "manage-finance",
      },
      {
        name: "Other Charges",
        to: { name: "OtherCharges" },
        icon: Truck,
        permission: "manage-finance",
      },
      {
        name: "Banks",
        to: { name: "Banks" },
        icon: Banknote,
        permission: "manage-settings",
      },
      {
        name: "Currencies",
        to: { name: "Currencies" },
        icon: DollarSign,
        permission: "manage-settings",
      },
      {
        name: "Admin Ledger",
        to: { name: "AdminLedger" },
        icon: Banknote,
        permission: "view-ledger",
      },
    ],
  },
  {
    title: "Marketing",
    items: [
      {
        name: "Banners",
        to: { name: "Banners" },
        icon: GalleryHorizontal,
        permission: "manage-marketing",
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
        permission: "manage-airlines",
      },
      {
        name: "Promotions",
        to: { name: "Promotions" },
        icon: TagIcon,
        permission: ["airlines_view"],
      },
      {
        name: "Segment Margins",
        to: { name: "SegmentMargins" },
        icon: LandmarkIcon,
        permission: ["airlines_view"],
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
        permission: "manage-staff",
      },
      {
        name: "Roles",
        to: { name: "Roles" },
        icon: Shield,
        permission: "manage-roles",
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
        notify: "new_user",
        permission: "manage-customers",
      },
      {
        name: "Customer Settings",
        to: { name: "CustomerMargin" },
        icon: GearIcon,
        permission: "manage-settings",
      },
    ],
  },
];

// Interactive Quick Search State
const searchQuery = ref("");

// Mobile drawer controls
const isMobileOpen = ref(false);
function toggleSidebar() {
  if (window.innerWidth < 1024) {
    isMobileOpen.value = !isMobileOpen.value;
    if (isMobileOpen.value) {
      document.body.classList.add("overflow-hidden");
    } else {
      document.body.classList.remove("overflow-hidden");
    }
  }
}

function closeSidebar() {
  if (window.innerWidth < 1024) {
    isMobileOpen.value = false;
    document.body.classList.remove("overflow-hidden");
  }
}

function handleResize() {
  if (window.innerWidth > 1024) {
    isMobileOpen.value = false;
    document.body.classList.remove("overflow-hidden");
  }
}

// Pending Requests Count
const pendingRequestsCount = ref(0);

async function loadPendingRequestsCount() {
  if (!authStore.hasPermission("manage-bookings")) return;
  try {
    const res = await apiService.fetchBookingRequestCounts();
    pendingRequestsCount.value = res.data?.pending ?? 0;
  } catch (err) {
    // Non-blocking notification badge
  }
}

// Notifications Integration
const fetchedNotifications = computed(() => store.getters["notification/notifications"] || []);

const unreadByKind = computed(() => {
  const counts = {};
  for (const n of fetchedNotifications.value) {
    if (n.read_at) continue;
    const kind = notificationKind(n);
    if (kind) counts[kind] = (counts[kind] || 0) + 1;
  }
  return counts;
});

const unreadCount = (kind) => unreadByKind.value[kind] || 0;
const requestInboxCount = computed(() => pendingRequestsCount.value + unreadCount("modify_request_submitted"));

const sectionUnreadCount = (section) =>
  (section?.items || []).reduce((sum, item) => sum + (item.notify ? unreadCount(item.notify) : 0), 0);

watch(() => unreadCount("booking_request_submitted"), () => loadPendingRequestsCount());

function markSectionNotificationsRead(routeName) {
  for (const [kind, routes] of Object.entries(ADMIN_NOTIFICATION_SECTIONS)) {
    if (!routes.includes(routeName)) continue;
    fetchedNotifications.value
      .filter((n) => !n.read_at && notificationKind(n) === kind)
      .forEach((n) => store.dispatch("notification/readNotification", { id: n.id }));
  }
}

// Accordion single-open state: Only one dropdown can be open at a time
const openSectionIndex = ref(null);

const toggleSection = (index) => {
  if (openSectionIndex.value === index) {
    // If currently open section is clicked, close it
    openSectionIndex.value = null;
  } else {
    // Otherwise open this section and close any previously open section
    openSectionIndex.value = index;
  }
};

function isSectionOpen(section) {
  if (searchQuery.value.trim()) return true;
  return openSectionIndex.value === section.originalIndex;
}

// Active Route Helpers
function isLinkActive(link) {
  if (link.activeRoutes && link.activeRoutes.length) {
    return link.activeRoutes.includes(route.name);
  }
  return route.name === link.to.name;
}

function isItemActive(item) {
  if (route.name === item.to.name) return true;
  const relatedRoutes = {
    TopUpRequest: ["TopUpRequest", "DepositDetails"],
    Staff: ["Staff", "NewStaff", "UpdateStaff"],
    Promotions: ["Promotions", "NewPromotion", "UpdatePromotion"],
    SegmentMargins: ["SegmentMargins", "NewSegmentMargin", "UpdateSegmentMargin"],
    Customers: ["Customers", "CustomerDetails", "UpdateCustomer"],
  };
  return relatedRoutes[item.to.name]?.includes(route.name) ?? false;
}

// Auto-expand active section on load or route change (single section open)
function autoExpandActiveSection() {
  const currentName = route.name;
  if (!currentName) return;

  for (let index = 0; index < menuSections.length; index++) {
    const section = menuSections[index];
    const hasActiveItem = section.items.some((item) => isItemActive(item));
    if (hasActiveItem) {
      openSectionIndex.value = index;
      break;
    }
  }
}

// Core Links definition with dynamic filter
const coreLinks = computed(() => {
  const links = [
    {
      name: "Dashboard",
      to: { name: "Dashboard" },
      icon: LayoutDashboard,
      visible: authStore.user?.role === "admin",
      activeRoutes: ["Dashboard"],
      badge: 0,
      badgeColor: "",
    },
    {
      name: "Bookings",
      to: { name: "AdminCustomerBookings" },
      icon: Notebook,
      visible: authStore.hasPermission("view-bookings"),
      activeRoutes: [
        "AdminCustomerBookings",
        "Bookings",
        "AdminHotelBookings",
        "AdminHotelBookingDetails",
        "CustomerBookingDetails",
        "BookingDetails",
      ],
      badge: unreadCount("booking_created"),
      badgeColor: "bg-rose-500 text-white",
    },
    {
      name: "Travel Calendar",
      to: { name: "TravelCalendar" },
      icon: CalendarDays,
      visible: authStore.hasPermission("view-bookings"),
      activeRoutes: ["TravelCalendar"],
      badge: 0,
      badgeColor: "",
    },
    {
      name: "Insights",
      to: { name: "MarketingInsights" },
      icon: TrendingUp,
      visible: authStore.user?.role === "admin",
      activeRoutes: ["MarketingInsights"],
      badge: 0,
      badgeColor: "",
    },
    {
      name: "Request Inbox",
      to: { name: "BookingRequests" },
      icon: Inbox,
      visible: authStore.hasPermission("manage-bookings"),
      activeRoutes: ["BookingRequests", "VoidBookings"],
      badge: requestInboxCount.value,
      badgeColor: "bg-amber-500 text-white",
    },
  ];

  const query = searchQuery.value.trim().toLowerCase();
  return links.filter((link) => {
    if (!link.visible) return false;
    if (!query) return true;
    return link.name.toLowerCase().includes(query);
  });
});

// Menu Sections filtered by permissions and search query
const filteredMenuSections = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  return menuSections
    .map((section, sectionIndex) => {
      const authorizedItems = section.items.filter(
        (item) => !item.permission || authStore.hasPermission(item.permission)
      );

      if (!query) {
        return {
          ...section,
          originalIndex: sectionIndex,
          items: authorizedItems,
          matchesQuery: authorizedItems.length > 0,
        };
      }

      const titleMatches = section.title.toLowerCase().includes(query);
      const matchingItems = authorizedItems.filter((item) =>
        item.name.toLowerCase().includes(query)
      );

      return {
        ...section,
        originalIndex: sectionIndex,
        items: titleMatches ? authorizedItems : matchingItems,
        matchesQuery: titleMatches || matchingItems.length > 0,
      };
    })
    .filter((section) => section.matchesQuery && section.items.length > 0);
});

// Lifecycle Hooks & Route Watchers
onMounted(() => {
  window.addEventListener("resize", handleResize);
  loadPendingRequestsCount();
  autoExpandActiveSection();
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
});

watch(
  () => route.name,
  (name) => {
    markSectionNotificationsRead(name);
    autoExpandActiveSection();
    if (window.innerWidth < 1024) {
      closeSidebar();
    }
  }
);
</script>

<style scoped>
/* Custom slim scrollbar */
.custom-sidebar-scroll {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}

.custom-sidebar-scroll::-webkit-scrollbar {
  width: 4px;
}

.custom-sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.custom-sidebar-scroll::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 2px;
}
</style>
