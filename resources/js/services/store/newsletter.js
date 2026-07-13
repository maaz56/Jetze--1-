// store/modules/newsletter.js

import apiService from "./apiService";
import {
    FETCH_SUBSCRIBERS,
    SAVE_SUBSCRIBER,
    UPDATE_SUBSCRIBER,
    DELETE_SUBSCRIBER,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_SUBSCRIBERS,
    SET_SUBSCRIBER,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    subscribers: { data: [], meta: null },
    subscriber: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    subscribers(state) {
        return state.subscribers;
    },
    subscriber: (state) => (id) => {
        const subscribers = Array.isArray(state.subscribers)
            ? state.subscribers
            : state.subscribers?.data;

        if (Array.isArray(subscribers)) {
            var subscriber = subscribers.find((sub) => sub.id == id);
            if (subscriber) {
                return subscriber;
            }
        }
        return null;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_SUBSCRIBERS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getSubscribers(params);
            context.commit(SET_SUBSCRIBERS, response.data);
        } catch (error) {
            toast("Something went wrong while fetching subscribers.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            context.commit(SET_SUBSCRIBERS, { data: [], meta: null });
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_SUBSCRIBER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveSubscriber(params);
            context.commit(SET_SUBSCRIBER, response.data);
            toast("Subscriber saved successfully", {
                type: "success",
            });
            return response;
        } catch (error) {
            if (error.response && error.response.data && error.response.data.errors) {
                context.commit(SET_API_ERROR, error);
                toast(error.response.data.message || "Validation failed", {
                    type: "error",
                });
            } else {
                toast("Something went wrong while saving.", {
                    type: "error",
                });
            }
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [UPDATE_SUBSCRIBER](context, params) {
        context.commit(IS_LOADING);
        try {
            const { id, ...data } = params;
            const response = await apiService.updateSubscriber(id, data);
            context.commit(SET_SUBSCRIBER, response.data);
            toast("Subscriber updated successfully.", {
                type: "success",
            });
            return response;
        } catch (error) {
            if (error.response && error.response.data && error.response.data.errors) {
                context.commit(SET_API_ERROR, error);
                toast(error.response.data.message || "Validation failed", {
                    type: "error",
                });
            } else {
                toast("Something went wrong while updating.", {
                    type: "error",
                });
            }
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [DELETE_SUBSCRIBER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteSubscriber(params);
            toast("Subscriber deleted successfully.", {
                type: "success",
            });
            return response;
        } catch (error) {
            toast("Something went wrong while deleting.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
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
        if (error.response && error.response.data && error.response.data.errors) {
            state.apiErrors = error.response.data.errors;
        } else {
            state.apiErrors = [];
        }
        state.isLoading = false;
    },
    [SET_SUBSCRIBERS](state, data) {
        state.subscribers = data;
        state.isLoading = false;
    },
    [SET_SUBSCRIBER](state, data) {
        state.subscriber = data;
        state.isLoading = false;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
