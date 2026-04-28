import apiService from "./apiService";
import {
    FETCH_POPULAR_ROUTES,
    SAVE_POPULAR_ROUTE,
    UPDATE_POPULAR_ROUTE,
    DELETE_POPULAR_ROUTE,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_POPULAR_ROUTES,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    routes: [],
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
        try {
            const response = await apiService.getPopularRoutes(params);
            console.log(response);
            context.commit(SET_POPULAR_ROUTES, response.data);
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.savePopularRoute(params);
            // context.dispatch(FETCH_POPULAR_ROUTES);
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updatePopularRoute(params);
            context.dispatch(FETCH_POPULAR_ROUTES);
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_POPULAR_ROUTE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deletePopularRoute(params);
            context.dispatch(FETCH_POPULAR_ROUTES);
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
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
    [SET_POPULAR_ROUTES](state, data) {
        state.routes = data; // assuming API returns { routes: [...] }
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
