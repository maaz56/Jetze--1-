import { toast } from "vue3-toastify";
import { FETCH_AIRLINES, FETCH_AIRPORTS, SAVE_AIRLINE, UPDATE_AIRLINE } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_AIRLINES,
    SET_DESTINATIONS,
    SET_ORIGINS,
    SET_API_ERROR,
} from "./mutations.type";

const state = {
    airlines: null,
    airline:null,
    isLoading: false,
    apiErrors: [],
    airlinesDate: null,

};

const getters = {
    airlines(state) {
        return state.airlines;
    },
    airline(state) {
        return state.airline;
    },
    airlinesdata(state) {
        return state.airlinesdata;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_AIRLINES]({ commit }, params) {
        commit(IS_LOADING);
        try {
            const response = await apiService.getAirlines(params);
            commit(SET_AIRLINES, response.data);
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
    async [SAVE_AIRLINE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAirLine(params);
            toast("Airline saved successfully.", {
                theme: "dark",
                type: "success",
                dangerouslyHTMLString: true,
            });
        } catch (error) {
             //console.log(error);
            toast(error.response.data.message, {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
        }
    },
    async deleteAirline(context, airlineId) {
        context.commit(IS_LOADING);
        try {
            await apiService.deleteAirline(airlineId);
            toast("Airline deleted successfully.", {
                theme: "dark",
                type: "success",
                dangerouslyHTMLString: true,
            });
            // Optionally, refetch airlines list
            // context.dispatch(FETCH_AIRLINES);
        } catch (error) {
            toast(error.response?.data?.message || "Failed to delete airline.", {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
    async [UPDATE_AIRLINE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateAirline(params);
            context.commit(SET_AIRLINES, response.data);
            
        } catch (error) {
            console.log(error);
            toast(error, {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
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
            //console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_AIRLINES](state, data) {
        state.airlines = data;
        state.airlinesDate = data;
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
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
