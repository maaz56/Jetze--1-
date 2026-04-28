import apiService from "./apiService";
import {
    ASSIGN_TICKET_NUMBER,
    DELETE_TRAVELLER,
    FETCH_TRAVELLERS,
    SAVE_TRAVELLER,
    UPDATE_TRAVELLER,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_TRAVELLERS,
    SET_TRAVELLER,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    travellers: [],
    traveller: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    travellers(state) {
        return state.travellers;
    },
    traveller: (state) => (id) => {
        if (state.travellers != null) {
            var traveller = state.travellers.find((t) => t.id == id);
            if (traveller) {
                return traveller;
            }
        }
        return null;
    },
    travellerByNumber: (state) => (number) => {
        if (state.travellers != null) {
            var traveller = state.travellers.find((t) => t.number == number);
            if (traveller) {
                return traveller;
            }
        }
        return null;
    },
    travellersByType: (state) => (type) => {
        if (state.travellers != null) {
            return state.travellers.filter((t) => t.type === type);
        }
        return [];
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_TRAVELLERS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getTravellers(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_TRAVELLERS, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_TRAVELLER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveTraveller(params);
            context.dispatch(FETCH_TRAVELLERS);
            toast("Traveller saved successfully", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            //console.log(error);
            toast(error.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [UPDATE_TRAVELLER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateTraveller(params);
            //console.log(response.data);
            context.dispatch(FETCH_TRAVELLERS);
            toast("Traveller has been updated successfully.", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [DELETE_TRAVELLER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteTraveller(params);
            context.dispatch(FETCH_TRAVELLERS);
            toast("Traveller has been deleted successfully.", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [ASSIGN_TICKET_NUMBER]    (context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.assignTicketNumber(params);
            toast("Ticket number assigned successfully.", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
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
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            //console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_TRAVELLERS](state, data) {
        state.travellers = data;
    },
    [SET_TRAVELLER](state, data) {
        state.traveller = data;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};