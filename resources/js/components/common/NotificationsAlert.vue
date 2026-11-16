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
import apiClient from '@/config/axios';
import { computed, defineProps, onBeforeUnmount, watch } from 'vue';
import { useStore } from 'vuex';

const props = defineProps({
    isAdmin: {
        type: Boolean,
        default: false
    },
    disableAnimation: {
        type: Boolean,
        default: false
    },
    buttonClass: {
        type: String,
        default: ""
    }
});

const authStore = useAuthStore();
const store = useStore();
const user = computed(() => authStore.user);

let pusher = null;
let channel = null;

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
const notificationButtonClass = computed(() => {
    return props.buttonClass || "rounded-full h-10 w-10 sm:h-11 sm:w-11 hover:bg-white/20";
});

function disconnectPusher() {
    if (channel) {
        pusher?.unsubscribe(channel.name);
        channel = null;
    }

    if (pusher) {
        pusher.disconnect();
        pusher = null;
    }
}

function connectPusher(userId) {
    disconnectPusher();

    const pusherKey = import.meta.env.VITE_APP_PUSHER_APP_KEY;
    if (!pusherKey || !userId) return;

    pusher = new Pusher(pusherKey, {
        cluster: import.meta.env.VITE_APP_PUSHER_APP_CLUSTER || 'ap2',
        forceTLS: true,
        channelAuthorization: {
            customHandler: async ({ socketId, channelName }, callback) => {
                try {
                    const response = await apiClient.post('broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channelName,
                    });
                    callback(null, response.data);
                } catch (error) {
                    callback(error, null);
                }
            },
        },
    });

    channel = pusher.subscribe(`private-users.${userId}`);
    channel.bind('user-approved', handleNotification);
    channel.bind('new-user-registered', handleNotification);
}

function handleNotification(data) {
    const currentNotifications = store.getters['notification/notifications'] || [];
    // Laravel broadcasts notification fields at the top level, whereas the
    // notifications API returns them inside `data`. Keep both sources in one
    // shape so the title, description, and linked user ID render consistently.
    const notification = {
        id: data.id,
        type: data.type,
        data: data.data || data,
        read_at: data.read_at || null,
        created_at: data.created_at || new Date().toISOString(),
    };

    if (!currentNotifications.some((current) => current.id === notification.id)) {
        store.commit('notification/setNotifications', [notification, ...currentNotifications]);
    }
}

watch(user, (currentUser) => {
    fetchNotifications();
    connectPusher(currentUser?.id);
}, { immediate: true });

onBeforeUnmount(() => {
    disconnectPusher();
});
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" :class="notificationButtonClass">
                    <div class="relative">
                        <Bell class="h-4 sm:h-5 w-4 sm:w-5" />
                        <span v-if="readAtCounter" class="absolute bottom-3 left-3 flex h-3 w-3">
                            <span
                                v-if="!disableAnimation"
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
