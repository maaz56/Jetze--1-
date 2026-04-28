import apiService from "./apiService";
import {
    DELETE_GROUP_TICKET,
    FETCH_GROUP_TICKETS,
    SAVE_GROUP_TICKET,
    UPDATE_GROUP_TICKET,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_GROUP_TICKETS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    flights: [],
    flight: {},
    isLoading: false,
    validationErrors: [],
};

const getters = {
    tickets(state) {
        return state.flights;
    },
    ticket: (state) => (id) => {
        if (state.tickets != null) {
            var ticket = state.tickets.find((ticket) => ticket.id == id);
            if (ticket) {
                return ticket;
            }
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
    async [FETCH_GROUP_TICKETS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getGsabroupTickets(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_GROUP_TICKETS, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_GROUP_TICKET](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveGroupTicket(params);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_GROUP_TICKETS);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_GROUP_TICKET](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveGroupTicket(params);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_GROUP_TICKETS);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_GROUP_TICKET](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteGroupTickets(params);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_GROUP_TICKETS);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
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
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            //console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_GROUP_TICKETS](state, data) {
        state.flights = data.flights;
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
