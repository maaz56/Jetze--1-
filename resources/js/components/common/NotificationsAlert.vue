<script setup>
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuthStore } from "@/services/stores/auth";
import { Bell } from 'lucide-vue-next';
import Pusher from 'pusher-js';
import { computed, defineProps, onMounted, ref } from 'vue';
import { useStore } from 'vuex';

const props = defineProps({
    isAdmin: {
        type: Boolean,
        default: false
    }
});

const authStore = useAuthStore();
const store = useStore();
const user = computed(() => authStore.user);

// Notifications array for real-time updates
const notifications = ref([]);
const messages = ref([]); // For storing received messages

const fetchedNotifications = computed(() => store.getters['notification/notifications'] || []);
const isLoading = computed(() => store.getters['notification/isLoading']);

function fetchNotifications() {
    store.dispatch('notification/fetchNotifications');
}
function readNotification(id) {
    store.dispatch('notification/readNotification', { id });
    if (!props.isAdmin) {
        window.location.reload();
    }
}
// function deleteNotification(id) {
//     store.dispatch('notification/deleteNotification', { id });
// }
function clearAllNotifications() {
    store.dispatch('notification/clearAllNotifications');
}
const readAtCounter = computed(() => {
    return fetchedNotifications.value.filter(n => !n.read_at).length;
});

onMounted(() => {
    fetchNotifications();
    // Listen for notifications on user-specific channel
    const pusherKey = import.meta.env.VITE_APP_PUSHER_APP_KEY;
    const interval = setInterval(() => {
        const pusher = new Pusher(pusherKey, {
            cluster: 'ap2'
        });
        const userId = user.value?.id;
        if (userId || props.isAdmin) {
            const subscribe = props.isAdmin ? 'admin-notification' : `is-approved-${userId}`;
            const event = props.isAdmin ? 'admin-event' : `approval-event`;
            const channel = pusher.subscribe(subscribe);
            channel.bind(event, function (data) {
                // Make data reactive and commit to Vuex state
                const reactiveData = { ...data };
                const currentNotifications = store.getters['notification/notifications'] || [];
                store.commit('notification/setNotifications', [reactiveData, ...currentNotifications]);
            });
            clearInterval(interval);
        }
    }, 10000);
});
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="rounded-full h-10 w-10 sm:h-11 sm:w-11 hover:bg-white/20">
                    <div class="relative">
                        <Bell class="h-4 sm:h-5 w-4 sm:w-5" />
                        <span v-if="readAtCounter" class="absolute bottom-3 left-3 flex h-3 w-3">
                            <span
                                class="animate-bounce absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-400"></span>
                        </span>
                    </div>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-[250px]">
                <DropdownMenuLabel>
                    <div class="flex justify-between items-center">
                        <div class="">Notifications</div>
                        <Button v-if="fetchedNotifications.length" variant="link" class="p-0 m-0 h-min" @click="clearAllNotifications">Clear All</Button>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div v-if="isLoading" class="px-2 py-1 w-full">
                    <div v-for="i in 3" :key="i" class="flex items-center space-x-2 my-2 animate-pulse">
                        <div class="h-4 w-4 bg-gray-300 rounded-full"></div>
                        <div class="flex-1">
                            <div class="h-3 bg-gray-300 rounded w-2/3 mb-1"></div>
                            <div class="h-2 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
                <div v-else-if="fetchedNotifications.length" class="max-h-60  overflow-y-auto">
                    <DropdownMenuItem class="my-2" v-for="(n, i) in fetchedNotifications" :key="i"
                        @click="readNotification(n.id)" :class="{ 'bg-muted': !n.read_at }">
                        <div>
                            <p class="font-bold">{{ n?.data?.heading || n?.data?.data?.heading }}</p>
                            <p>{{ n?.data?.description || n?.data?.data?.description }}</p>
                           
                            <div class="mt-2" v-if="props.isAdmin">
                                <router-link target="_blank"
                                    :to="`/admin/user-details?user_id=${n?.data?.user_id|| n?.data?.data?.user_id}`">View Details</router-link>
                            </div>
                        </div>
                    </DropdownMenuItem>
                </div>
                <div v-else>
                    <p class="text-xs text-gray-500 px-2 py-1">No notifications</p>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
