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
    X
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { debounce } from "lodash";
import { useUserStore } from "@/services/stores/user";
import Nav from "@/components/admin/Nav.vue";
import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { formatDate } from "@/lib/utils";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Eye } from "lucide-vue-next";
import {
    FETCH_AGENT_DATA,
    FETCH_TOTAL_APPROVED_DEPOSIT,
    SAVE_AGENT_MARGIN,
    FETCH_AGENT_LEDGER,
    FETCH_BOOKING_DATA
} from "@/services/store/actions.type";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";
import auth from "@/services/store/auth";
import axios from "axios";


const userStore = useUserStore();
const store = useStore();
const route = useRoute();

const users = computed(() => userStore.users);
const agentData = computed(() => store.getters["user/agentData"]);
const isLoading = computed(() => userStore.isLoading);
const showDeleteDialog = ref(false);
const userToDelete = ref(null);
const authStore = useAuthStore();
const authUser = computed(() => authStore.user);


// Filter states
const approvalFilter = ref('all'); // 'all', 'approved', 'pending'
const roleFilter = ref('all'); // 'all', 'agent', 'user'

// Modified to fetch roles from DB
const availableRoles = ref([]);

const fetchAvailableRoles = async () => {
    try {
        const response = await axios.get('/api/roles');
        availableRoles.value = response.data.data;
    } catch (error) {
        console.error("Failed to fetch roles", error);
    }
};

onMounted(() => {
    fetchAvailableRoles();
});

const fetchUsers = debounce(() => {
    // When "all" is selected, we still only want to fetch agents and users
    const effectiveRole = roleFilter.value !== 'all' 
        ? roleFilter.value 
        : undefined; // Let the backend handle filtering to only agents and users
    
    userStore.fetchUsers({
        search_query: route.query.search_query,
        page: route.query.page,
        approval_status: approvalFilter.value !== 'all' ? approvalFilter.value : undefined,
        role: effectiveRole,
        type: 'staff'
    });
}, 300);

// Filtered users based on selected filters
const filteredUsers = computed(() => {
    if (!users.value?.data) return [];
    
    // Exclude regular customers/agents from the staff module
    return users.value.data.filter(user => 
        user.role !== 'agent' && user.role !== 'user'
    );
});

function confirmDelete(user) {
    userToDelete.value = user;
    showDeleteDialog.value = true;
}

function deleteUser(id) {
    userStore.deleteUser({
        id: id,
    });
}

function resetFilters() {
    approvalFilter.value = 'all';
    roleFilter.value = 'all';
    fetchUsers();
}

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-4 mb-3">
            <Button @click="$router.push({ name: 'Dashboard' })" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <span class="block text-3xl font-medium leading-none tracking-tight text-gray-900">
                Staff
            </span>
        </div>
    </div>
    <div>
        <div class="bg-white p-8 rounded-lg border">
            <div
                class="flex flex-col py-4 space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4">
                <div class="flex flex-wrap items-center flex-1 space-x-4 gap-y-2">
                    <div class="relative w-full max-w-sm items-center">
                        <Input @input="
                            (event) => {
                                $router.push({
                                    path: $route.path,
                                    query: {
                                        ...Object.fromEntries(
                                            Object.entries(
                                                $route.query,
                                            ).filter(
                                                ([key]) =>
                                                    key !== 'search_query',
                                            ),
                                        ),
                                        ...(event.target.value
                                            ? {
                                                search_query:
                                                    event.target.value,
                                            }
                                            : {}),
                                    },
                                });
                                fetchUsers();
                            }
                        " id="search" type="text" placeholder="Search..." class="pl-10" />
                        <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                    </div>

                    <!-- Filter Section -->
                    <div class="flex flex-wrap items-center gap-2 mt-2 sm:mt-0">
                        <!-- Approval Status Filter -->
                        

                        <!-- Role Filter - Modified to only show Agent and User -->
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
                                        :class="{ 'bg-muted': roleFilter === 'all' }"
                                        @click="roleFilter = 'all'; fetchUsers()">
                                        All Roles
                                    </Button>
                                    <!-- Only show Agent and User roles -->
                                    <Button v-for="role in availableRoles" :key="role.id"
                                        variant="ghost" size="sm" class="w-full justify-start capitalize"
                                        :class="{ 'bg-muted': roleFilter === role.name }"
                                        @click="roleFilter = role.name; fetchUsers()">
                                        {{ role.name }}
                                    </Button>
                                </div>
                            </PopoverContent>
                        </Popover>
                        <!-- Reset Filters Button -->
                        <Button v-if="approvalFilter !== 'all' || roleFilter !== 'all'" variant="ghost" size="sm"
                            class="h-9" @click="resetFilters">
                            <X class="w-4 h-4 mr-1" />
                            Clear Filters
                        </Button>
                    </div>
                </div>
                <div
                    class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3">
                    <Button variant="outline" @click="
                        () => {
                            $router.push({ name: 'NewStaff' });
                        }
                    " class="flex items-center">
                        <UserPlus class="w-4 h-4 mr-2" /> Add Staff
                    </Button>
                </div>
            </div>
           
            <section v-if="users?.data.length === 0">
                <NothingFound />
            </section>
            <section v-if="isLoading" class="p-24 flex items-center justify-center">
                <Spinner />
            </section>

            <section v-if="users?.data.length > 0 && !isLoading">
                <div class="relative overflow-hidden">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader class="bg-primary">
                                <TableRow>
                                    <TableHead scope="col" class="px-4 py-3">Role</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Name</TableHead>

                                   
                                    <TableHead scope="col" class="px-4 py-3">Email</TableHead>
                                   
                                   
                                    <TableHead scope="col" class="px-4 py-3">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(user, index) in filteredUsers" :key="index" class="border-b hover:bg-gray-100">
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5 uppercase">
                                            {{ user.role || "_" }}
                                        </span>
                                       
                                    </TableCell>
                                     <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5 uppercase">
                                            {{ user.name || "_" }}
                                        </span>
                                    </TableCell>
                                    
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5">
                                            {{ user?.email || "_" }}
                                        </span>
                                    </TableCell>
                                   
                                    
                                  
                                   

                                    <TableCell class="px-4 py-2 space-x-2 whitespace-nowrap">
                                        <button v-if="authUser && authUser.role == 'admin'" @click="
                                            $router.push({
                                                name: 'UpdateStaff',
                                                query: {
                                                    user_id: user.id,
                                                }
                                            })
                                            "
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-gray-200 hover:text-primary h-8 px-3">
                                             <Pencil class="w-4 h-4 me-2" />
                                            Edit
                                        </button>

                                        <button v-if="authUser && authUser.role == 'admin'" @click="confirmDelete(user)"
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-8 px-3 text-destructive hover:bg-destructive/10">
                                            <Trash2 class="w-4 h-4 me-2" />
                                            Delete
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <nav v-if="users.meta" class="flex items-center justify-end pt-4">
                        <Pagination @change="fetchUsers()" :meta="users.meta" />
                    </nav>
                </div>
            </section>
        </div>
    </div>
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Are you sure you want to delete this user?</AlertDialogTitle>
                <AlertDialogDescription>
                    This action cannot be undone. This will permanently delete the user
                    {{ userToDelete?.email }} and remove their data from our servers.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="showDeleteDialog = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="deleteUser(userToDelete?.id)"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>