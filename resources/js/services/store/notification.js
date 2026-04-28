import {
    CLEAR_ALL_NOTIFICATIONS,
    DELETE_NOTIFICATION,
    FETCH_NOTIFICATIONS,
    READ_NOTIFICATION
} from "@/services/store/actions.type";
import { toast } from "vue3-toastify";
import apiService from "./apiService";
import {
    IS_LOADING,
    MARK_NOTIFICATION_READ,
    NOT_IS_LOADING,
    REMOVE_NOTIFICATION,
    SET_API_ERROR,
    SET_NOTIFICATIONS
} from "./mutations.type";

const state = {
    notifications: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    notifications(state) {
        return state.notifications;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_NOTIFICATIONS](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getAllNotifications();
            context.commit(SET_NOTIFICATIONS, response.data);
        } catch (error) {
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
    async [READ_NOTIFICATION](context, params) {
        context.commit(IS_LOADING);
        try {
            await apiService.readNotification(params);
            context.commit(MARK_NOTIFICATION_READ, params.id);
        } catch (error) {
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
    async [DELETE_NOTIFICATION](context, params) {
        context.commit(IS_LOADING);
        try {
            await apiService.deleteNotification(params);
            context.commit(REMOVE_NOTIFICATION, params.id);
            toast("Notification deleted", { type: "success" });
        } catch (error) {
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
    async [CLEAR_ALL_NOTIFICATIONS](context) {
        context.commit(IS_LOADING);
        try {
            await apiService.clearAllNotifications();
            context.commit(SET_NOTIFICATIONS, []); // Clear all notifications in the state
            toast("All notifications cleared", { type: "success" });
        } catch (error) {
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
};

const mutations = {
    [IS_LOADING](state) {
        state.isLoading = true;
    },
    [NOT_IS_LOADING](state) {
        state.isLoading = false;
    },
    [SET_API_ERROR](state, error) {
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_NOTIFICATIONS](state, data) {
        state.notifications = data;
        state.isLoading = false;
    },
    [REMOVE_NOTIFICATION](state, id) {
        state.notifications = state.notifications.filter(n => n.id !== id);
    },
    [MARK_NOTIFICATION_READ](state, id) {
        const notification = state.notifications.find(n => n.id === id);
        if (notification) {
            notification.read_at = new Date().toISOString();
        }
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};