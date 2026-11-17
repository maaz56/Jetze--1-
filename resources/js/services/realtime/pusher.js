import Pusher from "pusher-js";
import apiClient from "@/config/axios";

let pusher = null;

function getPusher() {
    if (pusher) return pusher;

    const key = import.meta.env.VITE_APP_PUSHER_APP_KEY;
    if (!key) return null;

    pusher = new Pusher(key, {
        cluster: import.meta.env.VITE_APP_PUSHER_APP_CLUSTER || "ap2",
        forceTLS: true,
        channelAuthorization: {
            customHandler: async ({ socketId, channelName }, callback) => {
                try {
                    const response = await apiClient.post("broadcasting/auth", {
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

    return pusher;
}

export function subscribeToPrivateChannel(channelName, eventName, handler) {
    const client = getPusher();
    if (!client) return () => {};

    const fullChannelName = `private-${channelName}`;
    const channel = client.subscribe(fullChannelName);
    const eventNames = Array.isArray(eventName) ? eventName : [eventName];
    eventNames.forEach((name) => channel.bind(name, handler));

    return () => {
        eventNames.forEach((name) => channel.unbind(name, handler));
        client.unsubscribe(fullChannelName);
    };
}
