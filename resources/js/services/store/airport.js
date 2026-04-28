import { toast } from "vue3-toastify";
import {
    FETCH_AIRPORT_MARGINS,
    FETCH_AIRPORTS,
    SAVE_AIRPORT_MARGINS,
} from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_AIRPORTS,
    SET_DESTINATIONS,
    SET_ORIGINS,
    SET_API_ERROR,
    SET_AIRPORT_MARGIN,
} from "./mutations.type";

const state = {
    airports: [],
    origins: [],
    destinations: [],
    airportMargin: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    airports(state) {
        return state.airports;
    },
    origins(state) {
        return state.origins;
    },
    destinations(state) {
        return state.destinations;
    },
    airportMargin(state) {
        return state.airportMargin;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_AIRPORTS]({ commit }, params) {
        commit(IS_LOADING);
        try {
            const response = await apiService.getAirports(params);
            commit(SET_AIRPORTS, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            commit(SET_API_ERROR, error);
        } finally {
            commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_AIRPORT_MARGINS]({ commit }, params) {
        commit(IS_LOADING);
        try {
            const response = await apiService.saveAirportMargins(params);
            toast("Airport margin values saved successfully", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            commit(SET_API_ERROR, error);
        } finally {
            commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_AIRPORT_MARGINS]({ commit }) {
        commit(IS_LOADING);
        try {
            const response = await apiService.getAirportMargins();
            // console.log("airport margins", response.data);
            commit(SET_AIRPORT_MARGIN, response.data);
        } catch (error) {
            // console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            commit(SET_API_ERROR, error);
        } finally {
            commit(NOT_IS_LOADING);
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
            //console.log(error.response.data.errors)
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_AIRPORTS](state, data) {
        state.airports = data;
        state.isLoading = false;
    },
    [SET_ORIGINS](state, origins) {
        state.origins = origins;
        state.isLoading = false;
    },
    [SET_DESTINATIONS](state, destinations) {
        state.destinations = destinations;
        state.isLoading = false;
    },
    [SET_AIRPORT_MARGIN](state, margin) {
        state.airportMargin = margin;
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
