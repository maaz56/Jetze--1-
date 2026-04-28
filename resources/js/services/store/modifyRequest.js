import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    FETCH_CITIES,
    FETCH_MODIFY_REQUEST_DATA,
    FETCH_REQUESTS,
    SAVE_CONVERSATION,
    SAVE_REQUEST,
    SEND_REPLY,
    UPDATE_STATUS,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_REQUESTS,
    SET_REQUEST_DATA,
} from "./mutations.type";

const state = {
    cities: [],
    requests: [],
    requestData: null,
    isLoading: false,
    apiErrors: [],
};

const getters = {
    cities(state) {
        return state.cities;
    },
    requests(state) {
        return state.requests;
    },
    requestData(state) {
        return state.requestData;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [SAVE_REQUEST](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveRequest(params);
            //console.log('Cities' + JSON.stringify(response.data));
            toast("Request sent successfully.", {
                type: "success",
            });
            context.commit(NOT_IS_LOADING);
            
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_REQUESTS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.fetchRequests(params);
            context.commit(SET_REQUESTS, response.data);
            context.commit(NOT_IS_LOADING);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_MODIFY_REQUEST_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.fetchModifyRequestData(params);
            context.commit(SET_REQUEST_DATA, response.data.data);
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [SEND_REPLY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendReply(params);
            context.commit(NOT_IS_LOADING);
            toast("Reply sent successfully.", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_STATUS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateStatus(params);
            context.commit(NOT_IS_LOADING);
            toast("Status updated successfully.", {
                type: "success",
            });
            return response.data;
        } catch (error) {
            console.log(error);
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
            //console.log(error.response.data.errors)
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_REQUESTS](state, data) {
        state.requests = data.data;
        state.isLoading = false;
    },
    [SET_REQUEST_DATA](state, data) {
        state.requestData = data;
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
