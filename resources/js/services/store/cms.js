import apiService from "./apiService";
import {
    FETCH_POPULAR_ROUTES,
    SAVE_POPULAR_ROUTE,
    UPDATE_POPULAR_ROUTE,
    DELETE_POPULAR_ROUTE,
    SEND_POPULAR_ROUTES_MAIL,
    FETCH_TOP_AIRLINES,
    SAVE_TOP_AIRLINE,
    UPDATE_TOP_AIRLINE,
    DELETE_TOP_AIRLINE,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_POPULAR_ROUTES,
    SET_TOP_AIRLINES,
    CLEAR_API_ERRORS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    routes: [],
    topAirlines: [],
    route: {},
    isLoading: false,
    validationErrors: [],
};

const getters = {
    popularRoutes(state) {
        return state.routes;
    },
    popularRoute: (state) => (id) => {
        if (state.routes != null) {
            const route = state.routes.find((route) => route.id == id);
            if (route) return route;
        }
        return null;
    },
    topAirlines(state) {
        return state.topAirlines;
    },
    isLoading(state) {
        return state.isLoading;
    },
    validationErrors(state) {
        return state.validationErrors;
    },
};

const actions = {
    async [FETCH_POPULAR_ROUTES](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.getPopularRoutes(params || {});
            context.commit(SET_POPULAR_ROUTES, response.data);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [SAVE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.savePopularRoute(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [UPDATE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.updatePopularRoute(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [DELETE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.deletePopularRoute(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [SEND_POPULAR_ROUTES_MAIL](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.sendPopularRoutesMail(params);
            const queuedCount = response?.data?.queued_count || 0;
            toast.success(`Popular route email queued for ${queuedCount} recipient${queuedCount === 1 ? "" : "s"}`);
            return response.data;
        } catch (error) {
            toast.error(error?.response?.data?.message || "Failed to queue popular route email");
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_TOP_AIRLINES](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.getTopAirlines(params || {});
            context.commit(SET_TOP_AIRLINES, response.data);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [SAVE_TOP_AIRLINE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.saveTopAirline(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [UPDATE_TOP_AIRLINE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.updateTopAirline(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [DELETE_TOP_AIRLINE](context, params) {
        context.commit(IS_LOADING);
        context.commit(CLEAR_API_ERRORS);
        try {
            const response = await apiService.deleteTopAirline(params);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
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
            state.validationErrors = error.response.data.errors;
        }
    },
    [CLEAR_API_ERRORS](state) {
        state.validationErrors = [];
    },
    [SET_POPULAR_ROUTES](state, data) {
        state.routes = data; // assuming API returns { data: [...] }
        state.isLoading = false;
    },
    [SET_TOP_AIRLINES](state, data) {
        state.topAirlines = data;
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
