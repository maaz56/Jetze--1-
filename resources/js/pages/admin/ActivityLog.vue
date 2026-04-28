<script setup>
import { useStore } from "vuex";
import { computed, onMounted, ref, watch } from "vue";
import moment from "moment";
import { DELETE_ACTIVITY_LOG, FETCH_ACTIVITY_LOGS } from "@/services/store/actions.type";
import Pagination from "@/components/Pagination.vue";
import DateRangePicker from "@/components/common/DateRangePicker.vue";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { Check, ChevronsUpDown, Trash } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import Button from "@/components/ui/button/Button.vue";

const store = useStore();
const userStore = useUserStore();
const searchQuery = ref("")
const isOpenUserDropdown = ref(false)
const activityLogs = computed(() => store.getters["activityLog/activityLogs"] || { data: [] });
const users = computed(() => userStore.users?.data?.filter(user => ["agent", "user"].includes(user.role)) || []);
const meta = computed(() => ({
  current_page: activityLogs.value?.current_page || 1,
  total: activityLogs.value?.total || 0,
  per_page: activityLogs.value?.per_page || 10,
  last_page: activityLogs.value?.last_page || 1,
}));
const todayDate = moment().format("YYYY-MM-DD");
const dateRange = ref({
  start: null,
  end: null,
});
const userFilter = ref("all");
const showSearchHistory = ref(false);

const fetchActivityLogs = (page = 1) => {
  const params = {
    page,
    route: showSearchHistory.value ? "api/flight-providers" : undefined
  };
  if (userFilter.value !== "all") {
    params.user_id = userFilter.value;
  }
  if (dateRange.value.start && dateRange.value.end) {
    params.start_date = moment(dateRange.value.start).format("YYYY-MM-DD");
    params.end_date = moment(dateRange.value.end).format("YYYY-MM-DD");
  }
  store.dispatch(`activityLog/${FETCH_ACTIVITY_LOGS}`, params);
};

const fetchUsers = debounce(() => {
  userStore.fetchUsers({ role: ["agent", "user"] });
}, 300);

const handlePagination = ({ page }) => {
  fetchActivityLogs(page);
};

const deleteLogs = () => {
  store.dispatch(`activityLog/${DELETE_ACTIVITY_LOG}`, {
    route: showSearchHistory.value ? "api/flight-providers" : undefined
  }).then(() => {
    fetchActivityLogs(1); // Refresh logs after deletion
  });
};

const toggleTable = () => {
  showSearchHistory.value = !showSearchHistory.value;
  fetchActivityLogs(1); // Reset to page 1 when toggling
};
function isJson(str) {
  try {
    JSON.parse(str);
  } catch (e) {
    return false;
  }
  return true;
}

function fetchUsersSearch(event) {
  searchQuery.value = event.target.value
}

// Computed filtered users
const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value
  return users.value.filter(user =>
    user.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    user.role.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})
// Watch for changes in filters to refetch logs
watch([userFilter, dateRange], () => {
  fetchActivityLogs(1); // Reset to page 1 on filter change
});

onMounted(() => {
  fetchActivityLogs();
  fetchUsers();
});
</script>

<template>
  <section class="p-4">
    <div class="mb-6 flex justify-between items-center">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
        {{ showSearchHistory ? 'Search History' : 'Activity Logs' }}
      </h2>

    </div>
    <div class="grid grid-cols-1 md:grid-cols-2  mb-6 items-center">
      <!-- Left side: Buttons -->
      <div class="flex space-x-3">
        <button @click="deleteLogs"
          class="flex items-center px-3 py-2 bg-red-600 text-white font-medium rounded-lg shadow hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
          <Trash class="w-4 h-4 mr-2" /> Delete
        </button>

        <button @click="toggleTable"
          class="px-3 py-2 bg-primary text-white font-medium rounded-lg shadow hover:bg-primary/50 focus:outline-none transition">
          {{ showSearchHistory ? 'View Activity Logs' : 'View Search History' }}
        </button>
      </div>

      <!-- Right side: Filters -->
      <div class="flex flex-col md:flex-row md:space-x-4 space-y-3 md:space-y-0 justify-end">
        <DateRangePicker v-model="dateRange" heading="Select Activity Date Range"
          class="w-full md:w-60 rounded-md   dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-primary"
          placeholder="Select date range" />

        <Popover v-model:open="isOpenUserDropdown">
          <PopoverTrigger as-child>
            <Button variant="outline" role="combobox"
              class="w-full md:w-60 h-10 justify-between py-3 rounded-md border border-primary/50 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary">
              {{
                userFilter !== "all"
                  ? users.find((user) => user.id === userFilter)?.email || "Select a user..."
                  : "Select a user..."
              }}
              <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-full md:w-60 p-0">
            <Command>
              <CommandInput class="h-9" @input="fetchUsersSearch" placeholder="Search user..." />
              <CommandEmpty>No users found.</CommandEmpty>
              <CommandList>
                <CommandGroup>
                  <CommandItem 
                    :value="'all'"
                    @select="() => {
                      userFilter = 'all';
                      isOpenUserDropdown = false;
                    }"
                  >
                    All Users
                    <Check :class="cn(
                      'ml-auto h-4 w-4',
                      userFilter === 'all' ? 'opacity-100' : 'opacity-0'
                    )" />
                  </CommandItem>
                  <CommandItem 
                    v-for="user in filteredUsers" 
                    :key="user.id" 
                    :value="user.email"
                    @select="() => {
                      userFilter = user.id;
                      isOpenUserDropdown = false;
                    }"
                  >
                    {{ user.email }} ({{ user.role }})
                    <Check :class="cn(
                      'ml-auto h-4 w-4',
                      userFilter === user.id ? 'opacity-100' : 'opacity-0'
                    )" />
                  </CommandItem>
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <!-- Activity Logs Table -->
        <table v-if="!showSearchHistory && activityLogs?.data?.length > 0"
          class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" class="px-4 py-3">Date</th>
              <th scope="col" class="px-4 py-3">Log ID</th>
              <th scope="col" class="px-4 py-3">User Email</th>
              <th scope="col" class="px-4 py-3">Action</th>
              <th scope="col" class="px-4 py-3">Route</th>
              <th scope="col" class="px-4 py-3">Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in activityLogs?.data" :key="log.id"
              class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
              <td class="px-4 py-3">
                {{ moment(log.created_at).format("MM-DD-YYYY hh:mm A") }}
              </td>
              <td class="px-4 py-3">{{ log.id }}</td>
              <td class="px-4 py-3">{{ log.user.email }}</td>
              <td class="px-4 py-3">{{ log.action }}</td>
              <td class="px-4 py-3">{{ log.route }}</td>
              <td class="px-4 py-3">{{ log.details }}</td>
            </tr>
          </tbody>
        </table>
        <!-- Search History Table -->
        <table v-else-if="showSearchHistory && activityLogs?.data?.length > 0"
          class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" class="px-4 py-3">Date</th>
              <th scope="col" class="px-4 py-3">Log ID</th>
              <th scope="col" class="px-4 py-3">User Email</th>
              <th scope="col" class="px-4 py-3">Action</th>
              <th scope="col" class="px-4 py-3">Route</th>
              <th scope="col" class="px-4 py-3">Passengers</th>
              <th scope="col" class="px-4 py-3">Route</th>
              <th scope="col" class="px-4 py-3">Flight Type</th>
              <th scope="col" class="px-4 py-3">Cabin Class</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in activityLogs?.data" :key="log.id"
              class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
              <!-- Date -->
              <td class="px-4 py-3 text-left">
                {{ moment(log.created_at).format("MM-DD-YYYY hh:mm A") }}
              </td>
              <!-- Log ID -->
              <td class="px-4 py-3 text-left">{{ log.id }}</td>
              <!-- User Email -->
              <td class="px-4 py-3 text-left">{{ log.user.email }}</td>
              <!-- Action -->
              <td class="px-4 py-3 text-left">{{ log.action }}</td>
              <!-- Route -->
              <td class="px-4 py-3 text-left">{{ log.route }}</td>
              <!-- Passengers -->
              <td class="px-4 py-3 text-left">
                {{ isJson(log.details) ? JSON.parse(log.details)?.details?.searchParams?.adults : "" }} + {{
                  isJson(log.details) ? JSON.parse(log.details)?.details?.searchParams?.infants : "" }} + {{
                  isJson(log.details) ? JSON.parse(log.details)?.details?.searchParams?.infants : "" }}
              </td>
              <!-- Route -->
              <td v-if="isJson(log.details)" class="px-4 py-3 text-left">
                <template v-if="JSON.parse(log.details)?.details?.searchParams?.flightType === 'multi-city'">
                  <span v-for="(trip, idx) in JSON.parse(log.details)?.details?.searchParams?.trips" :key="idx">
                    {{ trip.origin }} → {{ trip.destination }}
                    <span v-if="idx < JSON.parse(log.details)?.details?.searchParams?.trips.length - 1"> , </span>
                  </span>
                </template>
                <template v-else>
                  {{ JSON.parse(log.details)?.details?.searchParams?.origin }} → {{
                    JSON.parse(log.details)?.details?.searchParams?.destination }}
                </template>
              </td>
              <!-- Flight Type -->
              <td class="px-4 py-3 text-left">
                {{ isJson(log.details) ? JSON.parse(log.details)?.details?.searchParams?.flightType : "" }}
              </td>
              <!-- Cabin Class -->
              <td class="px-4 py-3 text-left">
                {{ isJson(log.details) ? JSON.parse(log.details)?.details?.searchParams?.cabin_class : "" }}
              </td>
            </tr>
          </tbody>
        </table>
        <!-- No Data Message -->
        <div v-else class="flex flex-col items-center justify-center py-12 px-4 w-full">
          <img class="h-64 md:h-80 max-w-md w-full object-contain mx-auto" src="/public/assets/No data.png"
            alt="No data available" />
          <p
            class="text-gray-600 text-lg mt-6 text-center font-medium bg-gray-50 px-6 py-3 rounded-full shadow-sm border border-gray-100 max-w-md mx-auto dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
            <span class="inline-block mr-2">🔍</span>
            No {{ showSearchHistory ? 'search history' : 'activity logs' }} found
            <span class="block text-sm text-gray-400 mt-1 font-normal dark:text-gray-500">
              Try adjusting filters or check back later
            </span>
          </p>
        </div>
      </div>
      <Pagination v-if="meta.total > 0" :meta="meta" @change="handlePagination"
        class="p-4 bg-gray-50 dark:bg-gray-700" />
    </div>
  </section>
</template>