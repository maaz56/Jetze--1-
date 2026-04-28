<script setup>
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import Input from "@/components/ui/input/Input.vue";
import Pagination from "@/components/Pagination.vue";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";

import {
  Calendar as CalendarIcon,
  ArrowLeft,
  Search,
  RefreshCcw,
  Ellipsis,
  Pencil,
  Trash2,
  Plus,
  UserPlus,
  Filter,
  X,
  Eye,
  Download,
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useUserStore } from "@/services/stores/user";
import Nav from "@/components/admin/Nav.vue";
import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { FETCH_CUSTOMERS, UPDATE_CUSTOMER_TYPE } from "@/services/store/actions.type";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

const userStore = useUserStore();
const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);
const rawCustomers = computed(() => store.getters["customer/customers"]); // { data: [...], meta: {...} }
const isLoading = computed(() => userStore.isLoading);
const showDeleteDialog = ref(false);
const customerToDelete = ref(null);

// ---------- FILTERS ----------
const approvalFilter = ref('all');   // 'all' | '1' | '0'
const roleFilter = ref('all');       // 'all' | 'agent' | 'user' | 'customer'

const availableRoles = ref(['agent', 'user', 'customer']);

function fetchCustomers() {
  store.dispatch('customer/' + FETCH_CUSTOMERS, {
    search_query: route.query.search_query || '',
    approval_status: approvalFilter.value === 'all' ? '' : approvalFilter.value,
    role: roleFilter.value === 'all' ? '' : roleFilter.value,
    page: route.query.page || 1,
  });
}
function updateMode(cust) {
  // Toggle value locally
  cust.user.mode = cust.user.mode === 'B2B' ? 'B2C' : 'B2B';
  store.dispatch("customer/" + UPDATE_CUSTOMER_TYPE, {
    user_id: cust.user.id,
    mode: cust.user.mode,
  });
  console.log("Updating mode for customer:", cust.user.email, "to", cust.user.mode);
}


// ---------- COMPUTED LIST ----------
const customers = computed(() => {
  if (!rawCustomers.value?.data) return { data: [], meta: null };

  let list = rawCustomers.value.data;

  // Client-side role filter (only needed if backend returns more roles)
  if (roleFilter.value !== 'all') {
    list = list.filter(c => c.user.role === roleFilter.value);
  }

  // Client-side approval filter
  if (approvalFilter.value !== 'all') {
    list = list.filter(c => String(c.user.is_approved) === approvalFilter.value);
  }

  return { data: list, meta: rawCustomers.value.meta };
});

function confirmDelete(customer) {
  customerToDelete.value = customer;
  showDeleteDialog.value = true;
}

function deleteCustomer(id) {
  // Assuming userStore has a deleteCustomer method (or reuse deleteUser)
  userStore.deleteUser({ id }).then(() => {
    showDeleteDialog.value = false;
    fetchCustomers();
  });
}

function resetFilters() {
  approvalFilter.value = 'all';
  roleFilter.value = 'all';
  fetchCustomers();
}

function exportCustomers() {
  const list = customers.value?.data || [];
  if (!list.length) {
    toast.info("No customer data available to export.");
    return;
  }

  const currentPage = customers.value?.meta?.current_page || 1;
  const perPage = customers.value?.meta?.per_page || list.length;
  const startIndex = (currentPage - 1) * perPage;

  const escapeCsv = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
  const headers = ["Sr", "Name", "Email", "Phone"];

  const rows = list.map((cust, index) => [
    startIndex + index + 1,
    `${cust.name || ""} ${cust.last_name || ""}`.trim(),
    cust.user?.email || "",
    cust.phone || "",
  ]);

  const csvContent = [headers, ...rows]
    .map((row) => row.map(escapeCsv).join(","))
    .join("\n");

  const blob = new Blob(["\uFEFF" + csvContent], {
    type: "text/csv;charset=utf-8;",
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  const date = new Date().toISOString().split("T")[0];

  link.href = url;
  link.setAttribute("download", `customers_${date}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

onMounted(() => {
  fetchCustomers();
});
</script>

<template>
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-4 mb-3">
      <Button @click="
        authUser?.role === 'salesman'
          ? $router.push({ name: 'SalesmanUsers' })
          : authUser?.role === 'reservation'
            ? $router.push({ name: 'ReservationUsers' })
            : authUser?.role === 'admin'
              ? $router.push({ name: 'Dashboard' })
              : null
        " variant="outline" size="sm">
        <ArrowLeft class="w-4 h-4 mr-1" />
        Back
      </Button>
      <span class="block text-3xl font-medium leading-none tracking-tight text-gray-900">
        Customers
      </span>
    </div>
  </div>

  <div>
    <div class="bg-white p-8 rounded-lg border">
      <!-- SEARCH & FILTERS -->
      <div
        class="flex flex-col py-4 space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4">
        <div class="flex flex-wrap items-center flex-1 space-x-4 gap-y-2">
          <!-- Search -->
          <div class="relative w-full max-w-sm items-center">
            <Input @input="
              (event) => {
                $router.push({
                  path: $route.path,
                  query: {
                    ...Object.fromEntries(
                      Object.entries($route.query).filter(
                        ([k]) => k !== 'search_query'
                      )
                    ),
                    ...(event.target.value ? { search_query: event.target.value } : {}),
                  },
                });
                fetchCustomers();
              }
            " id="search" type="text" placeholder="Search..." class="pl-10" />
            <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
              <Search class="size-4 text-muted-foreground" />
            </span>
          </div>

          <!-- FILTERS -->
          <div class="flex flex-wrap items-center gap-2 mt-2 sm:mt-0">
            <!-- Approval Status -->
            <Popover>
              <PopoverTrigger as-child>
                <Button variant="outline" size="sm" class="h-9 flex items-center gap-1">
                  <Filter class="w-4 h-4" />
                  Status:
                  {{ approvalFilter === 'all' ? 'All' : approvalFilter === '1' ? 'Approved' : 'Pending' }}
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-56 p-2">
                <div class="space-y-1">
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': approvalFilter === 'all' }" @click="approvalFilter = 'all'; fetchCustomers()">
                    All
                  </Button>
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': approvalFilter === '1' }" @click="approvalFilter = '1'; fetchCustomers()">
                    Approved
                  </Button>
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': approvalFilter === '0' }" @click="approvalFilter = '0'; fetchCustomers()">
                    Pending
                  </Button>
                </div>
              </PopoverContent>
            </Popover>

            <!-- Role Filter -->
            <Popover>
              <PopoverTrigger as-child>
                <Button variant="outline" size="sm" class="h-9 flex items-center gap-1">
                  <Filter class="w-4 h-4" />
                  Role: {{ roleFilter === 'all' ? 'All' : roleFilter }}
                </Button>
              </PopoverTrigger>
              <PopoverContent class="w-56 p-2">
                <div class="space-y-1">
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': roleFilter === 'all' }" @click="roleFilter = 'all'; fetchCustomers()">
                    All Roles
                  </Button>
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': roleFilter === 'agent' }" @click="roleFilter = 'agent'; fetchCustomers()">
                    Agent
                  </Button>
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': roleFilter === 'user' }" @click="roleFilter = 'user'; fetchCustomers()">
                    User
                  </Button>
                  <Button variant="ghost" size="sm" class="w-full justify-start"
                    :class="{ 'bg-muted': roleFilter === 'customer' }"
                    @click="roleFilter = 'customer'; fetchCustomers()">
                    Customer
                  </Button>
                </div>
              </PopoverContent>
            </Popover>

            <!-- Reset -->
            <Button v-if="approvalFilter !== 'all' || roleFilter !== 'all'" variant="ghost" size="sm" class="h-9"
              @click="resetFilters">
              <X class="w-4 h-4 mr-1" />
              Clear Filters
            </Button>

            <Button variant="outline" size="sm" class="h-9" @click="exportCustomers">
              <Download class="w-4 h-4 mr-1" />
              Export
            </Button>
          </div>
        </div>

        <!-- ADD BUTTON -->
        <!-- <div
          v-if="authUser.role === 'admin' || authUser.role === 'salesman'"
          class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3"
        >
          <Button
            variant="outline"
            @click="
              () => {
                $router.push({
                  name: authUser.role === 'admin' ? 'NewCustomer' : 'NewSalesmanCustomer',
                });
              }
            "
            class="flex items-center"
          >
            <UserPlus class="w-4 h-4 mr-2" /> Add Customer
          </Button>
        </div> -->
      </div>



      <!-- LOADING / EMPTY -->
      <section v-if="isLoading" class="p-24 flex items-center justify-center">
        <Spinner />
      </section>
      <section v-else-if="customers.data.length === 0">
        <NothingFound />
      </section>

      <!-- TABLE -->
      <section v-else>
        <div class="relative overflow-hidden">
          <div class="overflow-x-auto">
            <Table>
              <TableHeader class="bg-muted">
                <TableRow>
                  <TableHead class="px-4 py-3">Sr no</TableHead>
                  <TableHead class="px-4 py-3">Name</TableHead>
                  <TableHead class="px-4 py-3">Email</TableHead>
                  <TableHead class="px-4 py-3">Phone</TableHead>
                  <TableHead class="px-4 py-3">Role</TableHead>
                  <TableHead class="px-4 py-3">Email Verification</TableHead>
                  <TableHead class="px-4 py-3">Status</TableHead>
                  <!-- <TableHead class="px-4 py-3">Type</TableHead> -->
                  <TableHead class="px-4 py-3">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="(cust, index) in customers.data" :key="cust.id" class="border-b hover:bg-gray-100">
                  <TableCell class="px-4 py-2">
                    <span class="text-xs font-medium">
                      {{
                        (((customers.meta?.current_page || 1) - 1) *
                          (customers.meta?.per_page || customers.data.length)) +
                        index +
                        1
                      }}
                    </span>
                  </TableCell>
                  <!-- Name (first + last) -->
                  <TableCell class="px-4 py-2">
                    <span class="text-xs font-medium">
                      {{ cust.name }} {{ cust.last_name }}
                    </span>
                  </TableCell>

                  <!-- Email -->
                  <TableCell class="px-4 py-2">
                    <span class="text-xs font-medium">
                      {{ cust.user.email || '_' }}
                    </span>
                  </TableCell>

                  <!-- Phone -->
                  <TableCell class="px-4 py-2">
                    <span class="text-xs font-medium">
                      {{ cust.phone || 'N/A' }}
                    </span>
                  </TableCell>

                  <!-- Role -->
                  <TableCell class="px-4 py-2">
                    <span class="text-xs font-medium px-2 py-0.5 uppercase">
                      {{ cust.user.role || '_' }}
                    </span>
                  </TableCell>

                  <!-- Email Verification -->
                  <TableCell class="px-4 py-2">
                    <Badge v-if="cust.user.email_verified_at"
                      class="uppercase bg-green-300 hover:bg-green-200 text-gray-800 border-green-300 cursor-default">
                      Verified
                    </Badge>
                    <Badge v-else
                      class="uppercase bg-destructive/20 hover:bg-destructive/20 text-red-800 border-destructive/50 cursor-default">
                      Unverified
                    </Badge>
                  </TableCell>

                  <!-- Approval Status -->
                  <TableCell class="px-4 py-2">
                    <Badge v-if="cust.user.is_approved"
                      class="uppercase bg-green-300 hover:bg-green-200 text-gray-800 border-green-300 cursor-default">
                      Approved
                    </Badge>
                    <Badge v-else
                      class="uppercase bg-destructive/20 hover:bg-destructive/20 text-red-800 border-destructive/50 cursor-default">
                      Pending
                    </Badge>
                  </TableCell>
                  <!-- <TableCell class="px-4 py-2">
                    <div class="flex items-center justify-between w-20">
                     
                      <span :class="cust.user.mode === 'B2B' ? 'text-primary font-semibold' : 'text-gray-400'"
                        class="text-sm">
                        B2B&nbsp;
                      </span>

                      
                      <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" :checked="cust.user.mode === 'B2C'"
                          @change="updateMode(cust)" />
                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full 
                  peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full 
                  peer-checked:after:border-white after:content-[''] after:absolute 
                  after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 
                  after:border after:rounded-full after:h-5 after:w-5 after:transition-all 
                  dark:border-gray-600 peer-checked:bg-primary">
                        </div>
                      </label>

                    
                      <span :class="cust.user.mode === 'B2C' ? 'text-primary font-semibold' : 'text-gray-400'"
                        class="text-sm">
                        &nbsp;B2C
                      </span>
                    </div>
                  </TableCell> -->



                  <!-- Actions -->
                  <TableCell class="px-4 py-2 space-x-2 whitespace-nowrap">
                    <!-- Show -->
                    <button @click="
                      $router.push({
                        name:
                          authUser.role === 'admin'
                            ? 'CustomerDetails'
                            : authUser.role === 'salesman'
                              ? 'SalesmanCustomerDetails'
                              : authUser.role === 'reservation'
                                ? 'ReservationCustomerDetails'
                                : '',
                        query: { customer_id: cust.user_id },
                      })
                      "
                      class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-primary/10 hover:text-primary h-8 px-3">
                      <Eye class="w-4 h-4 me-2" />
                      Show
                    </button>

                    <!-- Edit (admin only) -->
                    <button
                      v-if="authUser.role === 'admin'"
                      @click="
                        $router.push({
                          name: 'UpdateCustomer',
                          query: { customer_id: cust.user_id },
                        })
                      "
                      class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-primary h-8 px-3 text-black hover:bg-primary/10"
                    >
                      <Pencil class="w-4 h-4 me-2" />
                      Edit
                    </button>

                    <!-- Delete (admin only) -->
                    <!-- <button
                      v-if="authUser.role === 'admin'"
                      @click="confirmDelete(cust)"
                      class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-destructive h-8 px-3 text-destructive hover:bg-destructive/10"
                    >
                      <Trash2 class="w-4 h-4 me-2" />
                      Delete
                    </button> -->
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Pagination -->
          <nav v-if="customers.meta" class="flex items-center justify-end pt-4">
            <Pagination @change="fetchCustomers()" :meta="customers.meta" />
          </nav>
        </div>
      </section>
    </div>
  </div>

  <!-- DELETE CONFIRMATION DIALOG -->
  <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Are you sure you want to delete this customer?</AlertDialogTitle>
        <AlertDialogDescription>
          This action cannot be undone. This will permanently delete the customer
          {{ customerToDelete?.user?.email }} and remove all associated data.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="showDeleteDialog = false">Cancel</AlertDialogCancel>
        <AlertDialogAction @click="deleteCustomer(customerToDelete?.id)"
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
          Delete
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
