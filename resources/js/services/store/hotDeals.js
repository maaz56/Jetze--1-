// store/modules/hotDeals.js

import apiService from "./apiService";
import {
    FETCH_HOT_DEALS,
    FETCH_HOT_DEAL,
    SAVE_HOT_DEAL,
    UPDATE_HOT_DEAL,
    DELETE_HOT_DEAL,
    REORDER_HOT_DEALS,
    SEND_HOT_DEALS_MAIL,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_HOT_DEALS,
    SET_HOT_DEAL,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    hotDeals: { data: [], meta: null },
    hotDeal: {},
    isLoading: false,
    apiErrors: [],
};

const normalizePaginatedHotDeals = (payload) => {
    const paginator = payload?.data && !Array.isArray(payload.data)
        ? payload.data
        : payload;

    if (Array.isArray(paginator?.data)) {
        return {
            data: paginator.data,
            meta: {
                current_page: paginator.current_page || 1,
                total: paginator.total || paginator.data.length,
                per_page: paginator.per_page || paginator.data.length || 15,
                last_page: paginator.last_page,
            },
        };
    }

    if (Array.isArray(payload?.data)) {
        return {
            data: payload.data,
            meta: payload.meta || null,
        };
    }

    return { data: [], meta: null };
};

const getters = {
    hotDeals(state) {
        return state.hotDeals;
    },
    hotDeal: (state) => (id) => {
        const hotDeals = Array.isArray(state.hotDeals)
            ? state.hotDeals
            : state.hotDeals?.data;

        if (Array.isArray(hotDeals)) {
            var hotDeal = hotDeals.find((deal) => deal.id == id);
            if (hotDeal) {
                return hotDeal;
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
    async [FETCH_HOT_DEALS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getHotDeals(params);
            context.commit(SET_HOT_DEALS, response.data);
        } catch (error) {
            toast("Something went wrong while fetching hot deals.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            context.commit(SET_HOT_DEALS, { data: [], meta: null });
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_HOT_DEAL](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getHotDeal(id);
            context.commit(SET_HOT_DEAL, response.data);
            return response;
        } catch (error) {
            toast("Something went wrong while fetching hot deal.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_HOT_DEAL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveHotDeal(params);
            context.commit(SET_HOT_DEAL, response.data);
            toast("Hot deal saved successfully", {
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
                toast("Something went wrong while saving hot deal.", {
                    type: "error",
                });
            }
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [UPDATE_HOT_DEAL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateHotDeal(params.id, params.data);
            context.commit(SET_HOT_DEAL, response.data);
            toast("Hot deal updated successfully.", {
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
                toast("Something went wrong while updating hot deal.", {
                    type: "error",
                });
            }
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [DELETE_HOT_DEAL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteHotDeal(params);
            toast("Hot deal deleted successfully.", {
                type: "success",
            });
            return response;
        } catch (error) {
            toast("Something went wrong while deleting hot deal.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [REORDER_HOT_DEALS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.reorderHotDeals(params);
            toast("Display order updated successfully.", {
                type: "success",
            });
            return response;
        } catch (error) {
            toast("Something went wrong while updating order.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SEND_HOT_DEALS_MAIL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendHotDealsMail(params);
            const queuedCount = response?.data?.queued_count || 0;
            toast.success(`Hot deal email queued for ${queuedCount} recipient${queuedCount === 1 ? "" : "s"}`);
            return response.data;
        } catch (error) {
            toast(error?.response?.data?.message || "Failed to queue hot deal email", {
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
    [SET_HOT_DEALS](state, data) {
        state.hotDeals = normalizePaginatedHotDeals(data);
        state.isLoading = false;
    },
    [SET_HOT_DEAL](state, data) {
        state.hotDeal = data?.data || data;
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
